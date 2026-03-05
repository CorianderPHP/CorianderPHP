<?php
declare(strict_types=1);

namespace CorianderCore\Tests;

use CorianderCore\Core\Security\ApiRequestLimitsMiddleware;
use Nyholm\Psr7\Response;
use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\TestCase;
use Psr\Http\Server\RequestHandlerInterface;

class ApiRequestLimitsMiddlewareTest extends TestCase
{
    public function testRejectsApiRequestOverConfiguredBodyLimit(): void
    {
        $middleware = new ApiRequestLimitsMiddleware(10, 5);
        $request = new ServerRequest('POST', '/api/items', ['Content-Length' => '11'], str_repeat('a', 11));

        $response = $middleware->process($request, new class implements RequestHandlerInterface {
            public function handle(\Psr\Http\Message\ServerRequestInterface $request): \Psr\Http\Message\ResponseInterface
            {
                return new Response(200, [], 'ok');
            }
        });

        $this->assertSame(413, $response->getStatusCode());
        $this->assertSame('application/json; charset=utf-8', $response->getHeaderLine('Content-Type'));
    }

    public function testSkipsNonApiRoutes(): void
    {
        $middleware = new ApiRequestLimitsMiddleware(10, 5);
        $request = new ServerRequest('POST', '/web/form', ['Content-Length' => '200'], str_repeat('a', 200));

        $response = $middleware->process($request, new class implements RequestHandlerInterface {
            public function handle(\Psr\Http\Message\ServerRequestInterface $request): \Psr\Http\Message\ResponseInterface
            {
                return new Response(201, [], 'created');
            }
        });

        $this->assertSame(201, $response->getStatusCode());
        $this->assertSame('created', (string) $response->getBody());
    }
}
