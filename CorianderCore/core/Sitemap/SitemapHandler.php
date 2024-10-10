<?php

namespace CorianderCore\Sitemap;

use SimpleXMLElement;

/**
 * SitemapHandler is responsible for generating a sitemap by fetching static
 * views and handling dynamic URLs.
 */
class SitemapHandler
{
    /**
     * Path to the public views directory.
     *
     * @var string
     */
    protected string $viewsPath;

    /**
     * List to store dynamic pages to be added to the sitemap.
     *
     * @var array
     */
    protected array $dynamicPages = [];

    /**
     * Constructor to initialize paths.
     */
    public function __construct()
    {
        // Set the path to the public views directory.
        $this->viewsPath = PROJECT_ROOT . '/public/public_views';
    }

    /**
     * Fetch all static pages from the public_views directory.
     *
     * This function scans the public views directory and retrieves information 
     * about each static page, including its metadata, last modified date, and 
     * whether it should be included in the sitemap.
     *
     * @return array List of static pages with metadata for sitemap generation.
     */
    public function fetchStaticPages(): array
    {
        $staticPages = [];

        // Guard clause: Ensure the directory exists before proceeding.
        if (!is_dir($this->viewsPath)) {
            return $staticPages;
        }

        // Scan the public_views directory for view folders.
        foreach (scandir($this->viewsPath) as $viewDir) {
            // Guard clause: Skip non-directories and system folders.
            if ($this->isInvalidDirectory($viewDir)) {
                continue;
            }

            // Check if the metadata.php file exists in the view folder.
            $metadataFile = "{$this->viewsPath}/{$viewDir}/metadata.php";
            if (!file_exists($metadataFile)) {
                continue; // Skip views without metadata.
            }

            // Extract metadata information from the metadata.php file.
            $metadata = $this->extractMetadata($metadataFile);

            // Guard clause: Skip pages that shouldn't be added to the sitemap.
            if (!$metadata['addViewInSitemap']) {
                continue;
            }

            // Add the static page data to the sitemap list.
            $staticPages[] = [
                'url' => PROJECT_URL . "/{$viewDir}",
                'priority' => $metadata['sitemapPriority'],
                'lastmod' => $this->getLastModifiedDate("{$this->viewsPath}/{$viewDir}/index.php"),
            ];
        }

        return $staticPages;
    }

    /**
     * Add dynamic pages to the sitemap.
     *
     * @param string $url The URL of the dynamic page.
     * @param float $priority The priority of the dynamic page (0.0 - 1.0).
     * @param string|null $lastmod The last modified date (optional).
     */
    public function addDynamicPage(string $url, float $priority = 0.5, ?string $lastmod = null): void
    {
        // Guard clause: Ensure valid URLs are added to the sitemap.
        if (empty($url)) {
            return;
        }

        // Add the dynamic page to the list.
        $this->dynamicPages[] = [
            'url' => $url,
            'priority' => $priority,
            'lastmod' => $lastmod ?? date('Y-m-d'),
        ];
    }

    /**
     * Generate the sitemap by combining static and dynamic pages.
     *
     * This function generates a sitemap.xml file containing both static and
     * dynamic pages. It first fetches static pages and then adds dynamic
     * pages before saving the sitemap to the project directory.
     */
    public function generateSitemap(): void
    {
        // Create the root element for the XML sitemap.
        $sitemapXml = new SimpleXMLElement('<urlset/>');
        $sitemapXml->addAttribute('xmlns', 'http://www.sitemaps.org/schemas/sitemap/0.9');

        // Fetch and add static pages.
        $staticPages = $this->fetchStaticPages();
        foreach ($staticPages as $page) {
            $this->addPageToSitemap($sitemapXml, $page);
        }

        // Add dynamic pages to the sitemap.
        foreach ($this->dynamicPages as $page) {
            $this->addPageToSitemap($sitemapXml, $page);
        }

        // Save the generated sitemap.xml file to the public directory.
        $sitemapXml->asXML(PROJECT_ROOT . '/public/sitemap.xml');
    }

    /**
     * Add a page (static or dynamic) to the sitemap XML structure.
     *
     * @param SimpleXMLElement $sitemapXml The XML element for the sitemap.
     * @param array $page The page data (url, priority, lastmod).
     */
    protected function addPageToSitemap(SimpleXMLElement $sitemapXml, array $page): void
    {
        $urlElement = $sitemapXml->addChild('url');
        $urlElement->addChild('loc', $page['url']);
        $urlElement->addChild('lastmod', $page['lastmod']);
        $urlElement->addChild('priority', (string)$page['priority']);
    }

    /**
     * Extract metadata from a metadata.php file.
     *
     * This function retrieves metadata information for each view, including
     * whether the view should be included in the sitemap and its priority.
     *
     * @param string $metadataFile Path to the metadata file.
     * @return array Metadata information for the sitemap.
     */
    protected function extractMetadata(string $metadataFile): array
    {
        include $metadataFile;

        // Return metadata with sensible defaults.
        return [
            'addViewInSitemap' => $addViewInSitemap ?? false,
            'sitemapPriority' => $sitemapPriority ?? 0.5,
        ];
    }

    /**
     * Get the last modified date of a file.
     *
     * This function retrieves the last modified date of a file and returns it
     * in 'Y-m-d' format for use in the sitemap.
     *
     * @param string $filePath Path to the file.
     * @return string Date in 'Y-m-d' format.
     */
    protected function getLastModifiedDate(string $filePath): string
    {
        return date('Y-m-d', filemtime($filePath));
    }

    /**
     * Check if a directory is invalid for sitemap generation.
     *
     * This function checks if a directory should be skipped, such as system
     * directories or non-directory files.
     *
     * @param string $dir The directory name.
     * @return bool True if invalid, false otherwise.
     */
    protected function isInvalidDirectory(string $dir): bool
    {
        // Guard clause to check for non-directories and system directories.
        return $dir === '.' || $dir === '..' || !is_dir($this->viewsPath . '/' . $dir);
    }
}