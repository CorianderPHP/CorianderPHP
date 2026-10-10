<?php
declare(strict_types=1);
namespace CorianderCore\Tests;

use CorianderCore\Core\Sitemap\SitemapHandler;

class SitemapHandlerTest extends RouteFixtureTestCase
{
    public function testOnlyExplicitPublicUrlsAreIncludedAndXmlIsEscaped(): void
    {
        $sitemap = new SitemapHandler();
        self::assertCount(0, simplexml_load_string($sitemap->toXml())->url);
        $url = 'https://example.com/search?a=1&b=<tag>';
        $sitemap->addDynamicPage($url, 0.8, '2026-10-10');
        $sitemap->generateSitemap($this->directory);
        $xml = simplexml_load_file($this->directory . '/sitemap.xml');
        self::assertCount(1, $xml->url);
        self::assertSame($url, (string) $xml->url->loc);
        self::assertSame('0.8', (string) $xml->url->priority);
        self::assertSame('2026-10-10', (string) $xml->url->lastmod);
    }
}
