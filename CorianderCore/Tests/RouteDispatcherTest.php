<?php

declare(strict_types=1);

namespace CorianderCore\Tests;

use CorianderCore\Core\Router\Router;
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
}
