<?php
declare(strict_types=1);
namespace CorianderCore\Core\Sitemap;

/** Public URLs are explicit; routes and views never imply sitemap inclusion. */
class SitemapHandler
{
    private array $pages = [];

    public function addDynamicPage(string $url, float $priority = 0.5, ?string $lastmod = null): void
    {
        if ($url === '') {
            return;
        }
        $this->pages[] = ['url' => $url, 'priority' => $priority, 'lastmod' => $lastmod ?? date('Y-m-d')];
    }

    public function toXml(): string
    {
        $xml = new \SimpleXMLElement('<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"/>');
        foreach ($this->pages as $page) {
            $element = $xml->addChild('url');
            $element->addChild('loc', htmlspecialchars($page['url'], ENT_XML1, 'UTF-8'));
            $element->addChild('lastmod', htmlspecialchars($page['lastmod'], ENT_XML1, 'UTF-8'));
            $element->addChild('priority', (string) $page['priority']);
        }
        return $xml->asXML();
    }

    public function generateSitemap(string $outputDir = PROJECT_ROOT . '/public'): void
    {
        if (file_put_contents(rtrim($outputDir, '/\\') . '/sitemap.xml', $this->toXml()) === false) {
            throw new \RuntimeException('Cannot write sitemap');
        }
    }
}
