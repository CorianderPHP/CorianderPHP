<?php

declare(strict_types=1);

namespace CorianderCore\Tests;

use CorianderCore\Core\Router\Router;
use Nyholm\Psr7\Response;
use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\TestCase;

class RouteDispatcherTest extends TestCase
{
    #[\PHPUnit\Framework\Attributes\RunInSeparateProcess]
    public function testCustomRouteStringReturnBecomesResponseBody(): void
    {
        $router = new Router();
        $router->add('GET', '/plain-string', fn (ServerRequest $request) => 'plain body');

        $response = $router->dispatch(new ServerRequest('GET', '/plain-string'));

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('plain body', (string) $response->getBody());
    }

    #[\PHPUnit\Framework\Attributes\RunInSeparateProcess]
    public function testCustomRouteMergesEchoedOutputAndReturnedString(): void
    {
        $router = new Router();
        $router->add('GET', '/mixed-output', function (ServerRequest $request): string {
            echo 'echoed-';
            return 'returned';
        });

        $response = $router->dispatch(new ServerRequest('GET', '/mixed-output'));

        $this->assertSame('echoed-returned', (string) $response->getBody());
    }

    #[\PHPUnit\Framework\Attributes\RunInSeparateProcess]
    public function testReturns405WhenRoutePathMatchesButMethodDiffers(): void
    {
        $router = new Router();
        $router->add('GET', '/resource', fn (ServerRequest $request) => 'ok');

        $response = $router->dispatch(new ServerRequest('POST', '/resource'));

        $this->assertSame(405, $response->getStatusCode());
        $this->assertSame('GET', $response->getHeaderLine('Allow'));
        $this->assertSame('Method Not Allowed', (string) $response->getBody());
    }

    #[\PHPUnit\Framework\Attributes\RunInSeparateProcess]
    public function testCustomRouteCanReturnExplicit404Response(): void
    {
        $router = new Router();
        $router->add('GET', '/sitemap.xml', fn (ServerRequest $request) => new Response(404, [], 'missing'));

        $response = $router->dispatch(new ServerRequest('GET', '/sitemap.xml'));

        $this->assertSame(404, $response->getStatusCode());
        $this->assertSame('missing', (string) $response->getBody());
    }
}
