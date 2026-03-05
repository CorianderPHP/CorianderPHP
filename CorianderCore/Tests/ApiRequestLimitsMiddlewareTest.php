<?php
declare(strict_types=1);

namespace CorianderCore\Tests;

use CorianderCore\Core\Security\ApiRequestLimitsMiddleware;
use Nyholm\Psr7\Response;
use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\StreamInterface;
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

    public function testNonSeekableUnknownSizeBodyPassesAndIsNotConsumed(): void
    {
        $middleware = new ApiRequestLimitsMiddleware(10, 5);
        $stream = new class('stream-data') implements StreamInterface {
            private string $data;
            private int $position = 0;

            public function __construct(string $data)
            {
                $this->data = $data;
            }

            public function __toString(): string { return $this->data; }
            public function close(): void {}
            public function detach() { return null; }
            public function getSize(): ?int { return null; }
            public function tell(): int { return $this->position; }
            public function eof(): bool { return $this->position >= strlen($this->data); }
            public function isSeekable(): bool { return false; }
            public function seek($offset, $whence = SEEK_SET): void { throw new \RuntimeException('Not seekable'); }
            public function rewind(): void { throw new \RuntimeException('Not seekable'); }
            public function isWritable(): bool { return false; }
            public function write($string): int { throw new \RuntimeException('Not writable'); }
            public function isReadable(): bool { return true; }
            public function read($length): string
            {
                $chunk = substr($this->data, $this->position, $length);
                $this->position += strlen($chunk);
                return $chunk;
            }
            public function getContents(): string
            {
                $chunk = substr($this->data, $this->position);
                $this->position = strlen($this->data);
                return $chunk;
            }
            public function getMetadata($key = null)
            {
                $meta = ['seekable' => false];
                return $key === null ? $meta : ($meta[$key] ?? null);
            }
        };

        $request = new ServerRequest('POST', '/api/items', [], $stream);

        $capturedBody = null;
        $response = $middleware->process($request, new class($capturedBody) implements RequestHandlerInterface {
            private ?string $capturedBody;

            public function __construct(?string &$capturedBody)
            {
                $this->capturedBody = &$capturedBody;
            }

            public function handle(\Psr\Http\Message\ServerRequestInterface $request): \Psr\Http\Message\ResponseInterface
            {
                $this->capturedBody = $request->getBody()->getContents();
                return new Response(200, [], 'ok');
            }
        });

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('stream-data', $capturedBody);
    }
}

