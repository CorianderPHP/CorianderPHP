<?php
declare(strict_types=1);
namespace CorianderCore\Tests;

use CorianderCore\Core\Router\Router;
use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\TestCase;

class StarterTest extends TestCase
{
    public function testCustom404PreservesRequestHeadersButDropsStaleRepresentationHeaders(): void
    {
        $handler = new class implements \Psr\Http\Server\RequestHandlerInterface {
            public function handle(\Psr\Http\Message\ServerRequestInterface $request): \Psr\Http\Message\ResponseInterface
            {
                return new \Nyholm\Psr7\Response(404, ['X-Request-ID' => 'fixture', 'Vary' => 'Accept-Language',
                    'Set-Cookie' => 'app=value; HttpOnly', 'Content-Length' => '3', 'Transfer-Encoding' => 'chunked',
                    'Content-Encoding' => 'gzip', 'Content-Range' => 'bytes 0-2/3', 'ETag' => 'stale', 'Last-Modified' => 'stale'], 'old');
            }
        };
        $response = (new \App\Middleware\NotFoundMiddleware())->process(new ServerRequest('GET', '/missing'), $handler);
        self::assertSame(404, $response->getStatusCode());
        self::assertSame('fixture', $response->getHeaderLine('X-Request-ID'));
        self::assertSame('Accept-Language', $response->getHeaderLine('Vary'));
        self::assertSame('app=value; HttpOnly', $response->getHeaderLine('Set-Cookie'));
        self::assertStringContainsString('<title>Page not found</title>', (string) $response->getBody());
        foreach (['Content-Length', 'Transfer-Encoding', 'Content-Encoding', 'Content-Range', 'ETag', 'Last-Modified'] as $name) {
            self::assertFalse($response->hasHeader($name));
        }
    }

    public function testHomepageErrorAndSitemapUseNewWorkflow(): void
    {
        $router = new Router();
        $home = $router->handle(new ServerRequest('GET', '/'));
        self::assertSame(200, $home->getStatusCode());
        self::assertStringContainsString('Welcome to CorianderPHP', (string) $home->getBody());
        self::assertSame('nosniff', $home->getHeaderLine('X-Content-Type-Options'));
        $missing = $router->handle(new ServerRequest('GET', '/missing'));
        self::assertSame(404, $missing->getStatusCode());
        self::assertStringContainsString('<title>Page not found</title>', (string) $missing->getBody());
        $sitemap = $router->handle(new ServerRequest('GET', '/sitemap.xml'));
        self::assertSame(200, $sitemap->getStatusCode());
        self::assertStringStartsWith('application/xml', $sitemap->getHeaderLine('Content-Type'));
        self::assertStringContainsString('<loc>' . rtrim(PROJECT_URL, '/') . '/</loc>', (string) $sitemap->getBody());
        self::assertSame(404, $router->handle(new ServerRequest('GET', '/_notfound'))->getStatusCode());
        self::assertFileDoesNotExist(PROJECT_ROOT . '/src/Views/home.php');
    }
}
