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

class HeadRequestTest extends RouteFixtureTestCase
{
    #[\PHPUnit\Framework\Attributes\RunInSeparateProcess]
    public function testBodylessStatusesDoNotEmitOrReadTheBody(): void
    {
        foreach ([100, 103, 204, 205, 304] as $status) {
            $response = new Response($status, [], 'must not be emitted');
            [, $output] = OutputBuffer::capture(fn() => ResponseEmitter::emit($response, 'GET'));
            self::assertSame('', $output);
            self::assertSame(0, $response->getBody()->tell());
            self::assertSame($status, http_response_code());
        }
    }

    public function testGetFallbackPreservesStatusHeadersAndOriginalMethod(): void
    {
        foreach ([200, 302, 404, 500] as $status) {
            $this->put('index.get.php', 'return static fn($r) => new \\Nyholm\\Psr7\\Response(' . $status . ', ["X-Method" => $r->getMethod(), "Location" => "/next"], "Hello");');
            $response = (new Router($this->directory))->handle(new ServerRequest('HEAD', '/'));
            self::assertSame($status, $response->getStatusCode());
            self::assertSame('HEAD', $response->getHeaderLine('X-Method'));
            self::assertSame('/next', $response->getHeaderLine('Location'));
            self::assertSame('Hello', (string) $response->getBody());
        }
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
