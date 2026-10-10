<?php
declare(strict_types=1);

namespace CorianderCore\Tests;

use CorianderCore\Core\Http\ResponseEmitter;
use CorianderCore\Core\Router\Router;
use CorianderCore\Core\Support\OutputBuffer;
use Nyholm\Psr7\Response;
use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

class HeadRequestTest extends TestCase
{
    public function testGetFallbackPreservesHomepageStatusAndHeaders(): void
    {
        foreach ([200, 302, 404, 500] as $status) {
            $router = new Router();
            $router->get('home', fn($request) => new Response($status,
                ['Content-Type' => 'text/plain', 'Location' => '/next', 'X-Method' => $request->getMethod()], 'Hello'));
            $response = $router->dispatch(new ServerRequest('HEAD', '/'));
            $this->assertSame($status, $response->getStatusCode());
            $this->assertSame('HEAD', $response->getHeaderLine('X-Method'));
            $this->assertSame('text/plain', $response->getHeaderLine('Content-Type'));
            $this->assertSame('/next', $response->getHeaderLine('Location'));
            $this->assertSame('Hello', (string) $response->getBody());
        }
    }

    public function testExplicitHeadWinsInBothRegistrationOrders(): void
    {
        foreach ([['GET', 'HEAD'], ['HEAD', 'GET']] as $methods) {
            $router = new Router();
            $calls = [];
            foreach ($methods as $method) {
                $router->add($method, '/resource/{id}', function ($request) use ($method, &$calls) {
                    $calls[] = $method;
                    return new Response(200, ['X-Handler' => $method], $request->getAttribute('id'));
                });
            }
            $response = $router->dispatch(new ServerRequest('HEAD', '/resource/42'));
            $this->assertSame(['HEAD'], $calls);
            $this->assertSame('HEAD', $response->getHeaderLine('X-Handler'));
            $this->assertSame('42', (string) $response->getBody());
        }
    }

    public function testFallbackKeepsParametersAndRunsMiddlewareOnce(): void
    {
        $makeMiddleware = static fn(string $name) => new class($name) implements MiddlewareInterface {
            public array $methods = [];
            public function __construct(private string $name) {}
            public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
            {
                $this->methods[] = $request->getMethod();
                return $handler->handle($request)->withHeader($this->name, 'applied');
            }
        };
        $global = $makeMiddleware('X-Global');
        $route = $makeMiddleware('X-Route');
        $router = new Router();
        $router->addMiddleware($global);
        $router->get('/resource/{id:\d+}', fn($request) => new Response(200, [], $request->getAttribute('id')), [$route]);
        $response = $router->dispatch(new ServerRequest('HEAD', '/resource/42'));
        $this->assertSame('42', (string) $response->getBody());
        $this->assertSame(['HEAD'], $global->methods);
        $this->assertSame(['HEAD'], $route->methods);
        $this->assertSame('applied', $response->getHeaderLine('X-Global'));
        $this->assertSame('applied', $response->getHeaderLine('X-Route'));
        $this->assertSame(404, $router->dispatch(new ServerRequest('HEAD', '/resource/abc'))->getStatusCode());
    }

    public function testHeadSupportDoesNotEnableOtherMethods(): void
    {
        $router = new Router();
        $router->get('/resource', fn() => new Response(200));
        $router->post('/resource', fn() => new Response(201));
        $router->post('/write-only', fn() => new Response(201));
        $response = $router->dispatch(new ServerRequest('PUT', '/resource'));
        $this->assertSame(405, $response->getStatusCode());
        $this->assertSame('GET, HEAD, POST', $response->getHeaderLine('Allow'));
        $this->assertSame(201, $router->dispatch(new ServerRequest('POST', '/resource'))->getStatusCode());
        $response = $router->dispatch(new ServerRequest('HEAD', '/write-only'));
        $this->assertSame(405, $response->getStatusCode());
        $this->assertSame('POST', $response->getHeaderLine('Allow'));
    }

    #[\PHPUnit\Framework\Attributes\RunInSeparateProcess]
    public function testEmitterSuppressesEveryHeadBodyAndPreservesStatus(): void
    {
        foreach ([200, 302, 403, 404, 405, 500] as $status) {
            $response = new Response($status, ['Content-Type' => 'text/plain', 'Content-Length' => '5'], 'Hello');
            [, $output] = OutputBuffer::capture(fn() => ResponseEmitter::emit($response, 'HEAD'));
            $this->assertSame('', $output);
            $this->assertSame($status, http_response_code());
            $this->assertSame('5', $response->getHeaderLine('Content-Length'));
            $this->assertSame(0, $response->getBody()->tell());
        }
    }

    #[\PHPUnit\Framework\Attributes\RunInSeparateProcess]
    public function testEmitterMethodArgumentOverridesGlobalFallback(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'HEAD';
        $response = new Response(200, [], 'Hello');
        [, $output] = OutputBuffer::capture(fn() => ResponseEmitter::emit($response));
        $this->assertSame('', $output);
        [, $output] = OutputBuffer::capture(fn() => ResponseEmitter::emit($response, 'GET'));
        $this->assertSame('Hello', $output);
        $_SERVER['REQUEST_METHOD'] = 'GET';
        [, $output] = OutputBuffer::capture(fn() => ResponseEmitter::emit($response, 'head'));
        $this->assertSame('', $output);
    }
}
