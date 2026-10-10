<?php
declare(strict_types=1);

namespace CorianderCore\Tests;

use CorianderCore\Core\Router\Router;
use Nyholm\Psr7\ServerRequest;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

class FixtureMiddleware implements MiddlewareInterface
{
    public function __construct(private string $name, private bool $reject = false) {}
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $trace = $request->getAttribute('trace', '') . $this->name;
        return $this->reject ? new \Nyholm\Psr7\Response(403, [], $trace)
            : $handler->handle($request->withAttribute('trace', $trace))->withHeader('X-' . $this->name, 'applied');
    }
}

class DirectoryMiddlewareTest extends RouteFixtureTestCase
{
    private function middleware(string $directory, string $name, bool $reject = false): void
    {
        $this->put(ltrim($directory . '/_middleware.php', '/'), 'return [new \\CorianderCore\\Tests\\FixtureMiddleware(' . var_export($name, true) . ', ' . ($reject ? 'true' : 'false') . ')];');
    }

    public function testParentsAccumulateAndParametersAreAvailableForEveryMethod(): void
    {
        $this->middleware('', 'root');
        $this->middleware('admin', 'admin');
        $this->middleware('admin/[id]', 'user');
        foreach (['get', 'post'] as $method) {
            $this->put('admin/[id]/index.' . $method . '.php', 'return static fn($r) => new \\Nyholm\\Psr7\\Response(200, [], $r->getAttribute("trace") . ":" . $r->getAttribute("id"));');
        }
        $router = new Router($this->directory);
        foreach (['GET', 'POST', 'HEAD'] as $method) {
            $response = $router->handle(new ServerRequest($method, '/admin/42'));
            self::assertSame('rootadminuser:42', (string) $response->getBody());
            self::assertSame('applied', $response->getHeaderLine('X-root'));
        }
    }

    public function testRejectionPreventsHandlerLoadingAndChildCannotRemoveParent(): void
    {
        $this->middleware('admin', 'auth', true);
        $this->put('admin/users/_middleware.php', 'return [];');
        $this->put('admin/users/index.get.php', 'throw new \\RuntimeException("must not load");');
        $response = (new Router($this->directory))->handle(new ServerRequest('GET', '/admin/users'));
        self::assertSame(403, $response->getStatusCode());
        self::assertSame('auth', (string) $response->getBody());
    }

    public function testGlobalMiddlewareAlsoWraps404AndProtected405(): void
    {
        $this->middleware('', 'root');
        $this->response('admin/index.get.php');
        $router = new Router($this->directory);
        self::assertSame('applied', $router->handle(new ServerRequest('GET', '/missing'))->getHeaderLine('X-root'));
        $this->middleware('admin', 'auth', true);
        self::assertSame(403, $router->handle(new ServerRequest('POST', '/admin'))->getStatusCode());
    }

    public function testMalformedMiddlewareFailsClearly(): void
    {
        $this->response('index.get.php');
        $this->put('_middleware.php', 'return ["auth"];');
        $this->expectExceptionMessage('Expected PSR-15');
        (new Router($this->directory))->handle(new ServerRequest('GET', '/'));
    }
}
