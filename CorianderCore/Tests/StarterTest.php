<?php
declare(strict_types=1);
namespace CorianderCore\Tests;

use CorianderCore\Core\Router\Router;
use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\TestCase;

class StarterTest extends TestCase
{
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
    }
}
