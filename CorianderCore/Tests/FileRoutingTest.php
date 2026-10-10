<?php
declare(strict_types=1);

namespace CorianderCore\Tests;

use CorianderCore\Core\Router\Router;
use CorianderCore\Core\Router\RouteMap;
use CorianderCore\Core\Router\RouteCache;
use CorianderCore\Core\Http\ResponseEmitter;
use CorianderCore\Core\Support\OutputBuffer;
use Nyholm\Psr7\ServerRequest;

class FileRoutingTest extends RouteFixtureTestCase
{
    public function testRootNestedParametersAndSingleUrlDecode(): void
    {
        $this->response('index.get.php', 'root');
        $this->put('teams/[teamId]/users/[id].get.php', 'return static fn($r) => new \\Nyholm\\Psr7\\Response(200, [], $r->getAttribute("teamId") . ":" . $r->getAttribute("id"));');
        $router = new Router($this->directory);
        self::assertSame('root', (string) $router->handle(new ServerRequest('GET', '/'))->getBody());
        self::assertSame('7:Alice Smith', (string) $router->handle(new ServerRequest('GET', '/teams/7/users/Alice%20Smith/'))->getBody());
        self::assertSame('7:%2F', (string) $router->handle(new ServerRequest('GET', '/teams/7/users/%252F'))->getBody());
    }

    public function testStaticPathCannotFallThroughToDynamicOnMethodMismatch(): void
    {
        $this->response('users/[id].get.php', 'dynamic');
        $this->response('users/new.post.php', 'static');
        $router = new Router($this->directory);
        $response = $router->handle(new ServerRequest('GET', '/users/new'));
        self::assertSame(405, $response->getStatusCode());
        self::assertSame('POST', $response->getHeaderLine('Allow'));
        self::assertSame('dynamic', (string) $router->handle(new ServerRequest('GET', '/users/42'))->getBody());
        self::assertSame('static', (string) $router->handle(new ServerRequest('POST', '/users/new'))->getBody());
    }

    public function testHeadOverrideFallbackAndEmitter(): void
    {
        $this->response('index.get.php', 'body', 202);
        $router = new Router($this->directory, new RouteCache($this->directory, production: false));
        $response = $router->handle(new ServerRequest('HEAD', '/'));
        self::assertSame(202, $response->getStatusCode());
        [, $output] = OutputBuffer::capture(fn() => ResponseEmitter::emit($response, 'HEAD'));
        self::assertSame('', $output);
        $this->response('index.head.php', 'override', 204);
        self::assertSame(204, $router->handle(new ServerRequest('HEAD', '/'))->getStatusCode());
        $response = $router->handle(new ServerRequest('DELETE', '/'));
        self::assertSame(405, $response->getStatusCode());
        self::assertSame('GET, HEAD', $response->getHeaderLine('Allow'));
    }

    public function testDiscoveryAndUnmatchedDispatchNeverExecuteHandlers(): void
    {
        $this->put('index.get.php', 'throw new \\RuntimeException("must not load");');
        $map = (new RouteMap())->discover($this->directory);
        self::assertCount(1, $map['static']);
        self::assertSame(404, (new Router($this->directory))->handle(new ServerRequest('GET', '/missing'))->getStatusCode());
    }

    public function testAmbiguousParameterNamesAndDuplicateIndexAreRejected(): void
    {
        $this->response('users/[id].get.php');
        $this->response('users/[slug].post.php');
        try {
            (new RouteMap())->discover($this->directory);
            self::fail('Expected ambiguity error');
        } catch (\RuntimeException $e) {
            self::assertStringContainsString('Ambiguous', $e->getMessage());
        }
        unlink($this->directory . '/users/[slug].post.php');
        $this->response('users.get.php');
        $this->response('users/index.get.php');
        $this->expectExceptionMessage('Duplicate');
        (new RouteMap())->discover($this->directory);
    }

    public function testUnsafePathsAreRejectedAndViewsAreNotRoutes(): void
    {
        $this->response('users/[id].get.php');
        $this->put('_header.php', 'throw new \\RuntimeException("not a handler");');
        $router = new Router($this->directory);
        foreach (['/users/%2F', '/users/%5C', '/users/%00', '/users/%2E%2E', '/users//42'] as $path) {
            self::assertSame(400, $router->handle(new ServerRequest('GET', $path))->getStatusCode(), $path);
        }
        self::assertNull(RouteMap::requestPath('/users/%zz'));
        self::assertSame(404, $router->handle(new ServerRequest('GET', '/_header'))->getStatusCode());
    }

    public function testHandlerContractAndOutputBufferCleanup(): void
    {
        $this->put('index.get.php', 'return static function () { echo "leak"; throw new \\RuntimeException("failure"); };');
        $level = ob_get_level();
        try {
            (new Router($this->directory))->handle(new ServerRequest('GET', '/'));
            self::fail('Expected handler failure');
        } catch (\RuntimeException $e) {
            self::assertSame('failure', $e->getMessage());
            self::assertSame($level, ob_get_level());
        }
        $this->put('index.get.php', 'return static fn() => "invalid";');
        $this->expectException(\UnexpectedValueException::class);
        (new Router($this->directory))->handle(new ServerRequest('GET', '/'));
    }

    public function testNestedOutputCannotBypassHandlerResponseContract(): void
    {
        $this->put('index.get.php', 'return static function () { echo "outer"; ob_start(); echo "inner"; return new \\Nyholm\\Psr7\\Response(200); };');
        $level = ob_get_level();
        try {
            (new Router($this->directory))->handle(new ServerRequest('GET', '/'));
            self::fail('Printed output accepted');
        } catch (\UnexpectedValueException $e) {
            self::assertStringContainsString('without printing', $e->getMessage());
            self::assertSame($level, ob_get_level());
        }
    }
}
