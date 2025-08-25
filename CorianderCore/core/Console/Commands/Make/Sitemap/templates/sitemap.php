<?php
// Set the path to the sitemap.xml file
$sitemapPath = PROJECT_ROOT . '/public/sitemap.xml';

// Check if the sitemap.xml file exists and if it was generated today
if (!file_exists($sitemapPath) || date('Y-m-d', filemtime($sitemapPath)) !== date('Y-m-d')) {
    // Initialize the SitemapHandler
    $sitemapHandler = new \CorianderCore\Core\Sitemap\SitemapHandler();

    // Fetch and add static pages to the sitemap
    $sitemapHandler->fetchStaticPages();

    // Add dynamic pages to the sitemap (Example)
    // $sitemapHandler->addDynamicPage(PROJECT_URL . '/blog/post-1', 0.8, '2024-05-01');
    // $sitemapHandler->addDynamicPage(PROJECT_URL . '/blog/post-1/more-informations', 0.6, '2024-06-15');

    // Generate the sitemap (both static and dynamic pages)
    $sitemapHandler->generateSitemap();
}

// Set the content type to XML
header('Content-Type: application/xml');
// Output the sitemap.xml file
readfile($sitemapPath);
