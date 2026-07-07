<?php
declare(strict_types=1);

namespace CorianderCore\Tests;

use CorianderCore\Core\Security\SecurityHeadersMiddleware;
use Nyholm\Psr7\Response;
use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\TestCase;
use Psr\Http\Server\RequestHandlerInterface;

class SecurityHeadersMiddlewareTest extends TestCase
{
    public function testAddsDefaultHeadersToResponse(): void
    {
        $middleware = new SecurityHeadersMiddleware();
        $request = new ServerRequest('GET', 'https://example.test/api/ping');

        $response = $middleware->process($request, new class implements RequestHandlerInterface {
            public function handle(\Psr\Http\Message\ServerRequestInterface $request): \Psr\Http\Message\ResponseInterface
            {
                return new Response(200, [], 'ok');
            }
        });

        $this->assertSame('nosniff', $response->getHeaderLine('X-Content-Type-Options'));
        $this->assertSame('DENY', $response->getHeaderLine('X-Frame-Options'));
        $this->assertSame('max-age=31536000; includeSubDomains', $response->getHeaderLine('Strict-Transport-Security'));
    }

    public function testKeepsExistingHeadersUnchanged(): void
    {
        $middleware = new SecurityHeadersMiddleware();
        $request = new ServerRequest('GET', 'http://example.test/path');

        $response = $middleware->process($request, new class implements RequestHandlerInterface {
            public function handle(\Psr\Http\Message\ServerRequestInterface $request): \Psr\Http\Message\ResponseInterface
            {
                return new Response(200, ['X-Frame-Options' => 'SAMEORIGIN'], 'ok');
            }
        });

        $this->assertSame('SAMEORIGIN', $response->getHeaderLine('X-Frame-Options'));
    }

    public function testUsesFirstForwardedProtoWhenMultipleValuesAreProvided(): void
    {
        $middleware = new SecurityHeadersMiddleware();
        $request = new ServerRequest('GET', 'http://example.test/path', ['X-Forwarded-Proto' => 'https, http']);

        $response = $middleware->process($request, new class implements RequestHandlerInterface {
            public function handle(\Psr\Http\Message\ServerRequestInterface $request): \Psr\Http\Message\ResponseInterface
            {
                return new Response(200, [], 'ok');
            }
        });

        $this->assertSame('max-age=31536000; includeSubDomains', $response->getHeaderLine('Strict-Transport-Security'));
    }

    public function testUsesConstructorConfiguredHeaders(): void
    {
        $middleware = new SecurityHeadersMiddleware([
            'Content-Security-Policy' => "default-src 'self'; script-src 'self'; img-src 'self' data: https://cdn.discordapp.com/",
            'X-Content-Type-Options' => 'nosniff',
        ]);
        $request = new ServerRequest('GET', 'https://example.test/path');

        $response = $middleware->process($request, new class implements RequestHandlerInterface {
            public function handle(\Psr\Http\Message\ServerRequestInterface $request): \Psr\Http\Message\ResponseInterface
            {
                return new Response(200, [], 'ok');
            }
        });

        $this->assertSame(
            "default-src 'self'; script-src 'self'; img-src 'self' data: https://cdn.discordapp.com/",
            $response->getHeaderLine('Content-Security-Policy')
        );
        $this->assertSame('nosniff', $response->getHeaderLine('X-Content-Type-Options'));
    }

    public function testCanBeDisabledFromConstructor(): void
    {
        $middleware = new SecurityHeadersMiddleware(enabled: false);
        $request = new ServerRequest('GET', 'https://example.test/path');

        $response = $middleware->process($request, new class implements RequestHandlerInterface {
            public function handle(\Psr\Http\Message\ServerRequestInterface $request): \Psr\Http\Message\ResponseInterface
            {
                return new Response(200, [], 'ok');
            }
        });

        $this->assertSame('', $response->getHeaderLine('X-Content-Type-Options'));
    }
}
