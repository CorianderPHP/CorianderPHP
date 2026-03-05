<?php

namespace CorianderCore\Tests;

use CorianderCore\Core\Security\Csrf;
use CorianderCore\Core\Security\CsrfMiddleware;
use Nyholm\Psr7\Response;
use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Unit tests for {@see CsrfMiddleware}.
 *
 * These tests verify that the middleware correctly bypasses non-POST requests,
 * rejects invalid tokens, and allows valid tokens to proceed.
 */
class CsrfMiddlewareTest extends TestCase
{
    /**
     * Ensure non-POST requests bypass CSRF validation and reach the next handler.
     */
    #[\PHPUnit\Framework\Attributes\RunInSeparateProcess]
    public function testBypassesNonPostRequests(): void
    {
        $middleware = new CsrfMiddleware();
        $request = new ServerRequest('GET', '/test');
        $handler = new class implements RequestHandlerInterface {
            public bool $called = false;
            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                $this->called = true;
                return new Response(200, [], 'OK');
            }
        };

        $response = $middleware->process($request, $handler);

        $this->assertTrue($handler->called);
        $this->assertSame(200, $response->getStatusCode());
    }

    /**
     * Ensure POST requests with an invalid token are rejected with a 403 status code.
     */
    #[\PHPUnit\Framework\Attributes\RunInSeparateProcess]
    public function testRejectsInvalidToken(): void
    {
        $middleware = new CsrfMiddleware();
        $request = (new ServerRequest('POST', '/test'))
            ->withParsedBody(['csrf_token' => 'invalid']);
        $handler = new class implements RequestHandlerInterface {
            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                return new Response(200, [], 'OK');
            }
        };

        $response = $middleware->process($request, $handler);

        $this->assertSame(403, $response->getStatusCode());
        $this->assertSame('Invalid CSRF token', (string) $response->getBody());
    }

    /**
     * Ensure POST requests with a valid token are allowed through the middleware.
     */
    #[\PHPUnit\Framework\Attributes\RunInSeparateProcess]
    public function testAllowsValidToken(): void
    {
        $token = Csrf::token();
        $middleware = new CsrfMiddleware();
        $request = (new ServerRequest('POST', '/test'))
            ->withParsedBody(['csrf_token' => $token]);
        $handler = new class implements RequestHandlerInterface {
            public bool $called = false;
            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                $this->called = true;
                return new Response(200, [], 'OK');
            }
        };

        $response = $middleware->process($request, $handler);

        $this->assertTrue($handler->called);
        $this->assertSame(200, $response->getStatusCode());
    }

    /**
     * Ensure URL-encoded POST bodies are parsed when parsedBody is missing.
     */
    #[\PHPUnit\Framework\Attributes\RunInSeparateProcess]
    public function testAllowsValidTokenFromUrlEncodedRawBody(): void
    {
        $token = Csrf::token();
        $middleware = new CsrfMiddleware();
        $request = new ServerRequest(
            'POST',
            '/test',
            ['Content-Type' => 'application/x-www-form-urlencoded'],
            'csrf_token=' . urlencode($token)
        );

        $handler = new class implements RequestHandlerInterface {
            public bool $called = false;
            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                $this->called = true;
                return new Response(200, [], 'OK');
            }
        };

        $response = $middleware->process($request, $handler);

        $this->assertTrue($handler->called);
        $this->assertSame(200, $response->getStatusCode());
    }

    /**
     * Ensure DELETE requests with an invalid token are rejected.
     */
    #[\PHPUnit\Framework\Attributes\RunInSeparateProcess]
    public function testRejectsInvalidTokenOnDelete(): void
    {
        $middleware = new CsrfMiddleware();
        $request = (new ServerRequest('DELETE', '/test'))
            ->withParsedBody(['csrf_token' => 'invalid']);
        $handler = new class implements RequestHandlerInterface {
            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                return new Response(200, [], 'OK');
            }
        };

        $response = $middleware->process($request, $handler);

        $this->assertSame(403, $response->getStatusCode());
        $this->assertSame('Invalid CSRF token', (string) $response->getBody());
    }

    /**
     * Ensure PATCH requests with a valid token are allowed.
     */
    #[\PHPUnit\Framework\Attributes\RunInSeparateProcess]
    public function testAllowsValidTokenOnPatch(): void
    {
        $token = Csrf::token();
        $middleware = new CsrfMiddleware();
        $request = (new ServerRequest('PATCH', '/test'))
            ->withParsedBody(['csrf_token' => $token]);
        $handler = new class implements RequestHandlerInterface {
            public bool $called = false;
            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                $this->called = true;
                return new Response(200, [], 'OK');
            }
        };

        $response = $middleware->process($request, $handler);

        $this->assertTrue($handler->called);
        $this->assertSame(200, $response->getStatusCode());
    }

    #[\PHPUnit\Framework\Attributes\RunInSeparateProcess]
    public function testBypassesApiRoutesByDefaultWhenTokenMissing(): void
    {
        $middleware = new CsrfMiddleware();
        $request = new ServerRequest('POST', '/api/items');
        $handler = new class implements RequestHandlerInterface {
            public bool $called = false;
            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                $this->called = true;
                return new Response(200, [], 'OK');
            }
        };

        $response = $middleware->process($request, $handler);

        $this->assertTrue($handler->called);
        $this->assertSame(200, $response->getStatusCode());
    }

    #[\PHPUnit\Framework\Attributes\RunInSeparateProcess]
    public function testCanEnforceCsrfForApiRoutesWhenEnabled(): void
    {
        $middleware = new CsrfMiddleware(null, true);
        $request = new ServerRequest('POST', '/api/items');
        $handler = new class implements RequestHandlerInterface {
            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                return new Response(200, [], 'OK');
            }
        };

        $response = $middleware->process($request, $handler);

        $this->assertSame(403, $response->getStatusCode());
    }
}
