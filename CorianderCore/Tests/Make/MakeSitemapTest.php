<?php
declare(strict_types=1);
namespace CorianderCore\Tests\Make;

use CorianderCore\Core\Console\Commands\Make\Sitemap\MakeSitemap;
use CorianderCore\Core\Router\Router;
use CorianderCore\Core\Support\OutputBuffer;
use CorianderCore\Tests\RouteFixtureTestCase;
use Nyholm\Psr7\ServerRequest;

class MakeSitemapTest extends RouteFixtureTestCase
{
    public function testGeneratedSitemapIsADiscoveredXmlResponse(): void
    {
        [$code] = OutputBuffer::capture(fn() => (new MakeSitemap($this->directory))->execute());
        self::assertSame(0, $code);
        $response = (new Router($this->directory))->handle(new ServerRequest('GET', '/sitemap.xml'));
        self::assertSame(200, $response->getStatusCode());
        self::assertSame('application/xml; charset=utf-8', $response->getHeaderLine('Content-Type'));
        self::assertStringContainsString('<loc>' . rtrim(PROJECT_URL, '/') . '/</loc>', (string) $response->getBody());
    }

    public function testExistingSitemapAndConflictingAliasesAreNotOverwritten(): void
    {
        $this->response('sitemap.xml/index.get.php', 'existing');
        [$code, $output] = OutputBuffer::capture(fn() => (new MakeSitemap($this->directory))->execute());
        self::assertSame(1, $code);
        self::assertStringContainsString('Duplicate GET route', $output);
        self::assertFileDoesNotExist($this->directory . '/sitemap.xml.get.php');
        $this->put('sitemap.xml.get.php', 'return "existing";');
        [$code, $output] = OutputBuffer::capture(fn() => (new MakeSitemap($this->directory))->execute());
        self::assertSame(1, $code);
        self::assertStringContainsString('already exists', $output);
        self::assertSame('<?php return "existing";', file_get_contents($this->directory . '/sitemap.xml.get.php'));
    }
}
