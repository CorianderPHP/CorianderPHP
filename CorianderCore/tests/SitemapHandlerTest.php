<?php

namespace CorianderCore\Tests;

use PHPUnit\Framework\TestCase;
use CorianderCore\Sitemap\SitemapHandler;
use CorianderCore\Utils\DirectoryHandler;
use ReflectionClass;
use SimpleXMLElement;

/**
 * Class SitemapHandlerTest
 *
 * This test class verifies the functionality of the SitemapHandler class, 
 * including fetching static pages, adding dynamic pages, and generating a sitemap XML file.
 */
class SitemapHandlerTest extends TestCase
{
    private static string $testPath;
    private static string $viewsPath;
    private static string $outputDir;

    /**
     * setUpBeforeClass
     *
     * This method runs once before all tests in the class. It sets up the necessary
     * directory paths and ensures the required files are created.
     */
    public static function setUpBeforeClass(): void
    {
        // Define PROJECT_ROOT if it's not already defined
        if (!defined('PROJECT_ROOT')) {
            define('PROJECT_ROOT', dirname(__DIR__, 2));
        }
        if (!defined('PROJECT_URL')) {
            define('PROJECT_URL', '');
        }

        self::$testPath = PROJECT_ROOT . "/CorianderCore/tests/_tmp/";
        self::$viewsPath = self::$testPath . 'public_views';
        self::$outputDir = self::$testPath . 'output/';

        // Create test directories if they don't exist
        if (!is_dir(self::$viewsPath)) {
            mkdir(self::$viewsPath, 0755, true);
        }

        if (!is_dir(self::$outputDir)) {
            mkdir(self::$outputDir, 0755, true);
        }

        // Create a sample view with metadata for testing
        $viewDir = self::$viewsPath . '/sampleView';
        if (!is_dir($viewDir)) {
            mkdir($viewDir, 0755, true);
        }

        file_put_contents($viewDir . '/metadata.php', "<?php\n\$addViewInSitemap = true;\n\$sitemapPriority = 0.8;");
        file_put_contents($viewDir . '/index.php', "<h1>Sample View</h1>");
    }
    
    /**
     * tearDownAfterClass
     *
     * This method runs once after all tests in the class have completed.
     * It cleans up the test environment by removing test files and directories.
     */
    public static function tearDownAfterClass(): void
    {
        // Cleanup: Remove test files and directories if they exist
        if (is_dir(self::$testPath)) {
            DirectoryHandler::deleteDirectory(self::$testPath); // Cleanup the temporary directory.
        }
    }

    /**
     * testFetchStaticPages
     *
     * Tests the fetching of static pages from the public views directory.
     */
    public function testFetchStaticPages(): void
    {
        $sitemapHandler = new SitemapHandler(self::$viewsPath);
        $staticPages = $sitemapHandler->fetchStaticPages();

        $this->assertCount(1, $staticPages, 'Static pages count does not match expected value.');
        $this->assertEquals(PROJECT_URL . '/sampleView', $staticPages[0]['url'], 'Static page URL is incorrect.');
        $this->assertEquals(0.8, $staticPages[0]['priority'], 'Static page priority is incorrect.');
    }

    /**
     * testAddDynamicPage
     *
     * Tests adding a dynamic page to the sitemap.
     */
    public function testAddDynamicPage(): void
    {
        $sitemapHandler = new SitemapHandler(self::$viewsPath);
        $sitemapHandler->addDynamicPage('https://example.com/dynamic-page', 0.7, '2024-10-01');

        $reflection = new ReflectionClass($sitemapHandler);
        $dynamicPagesProperty = $reflection->getProperty('dynamicPages');
        $dynamicPagesProperty->setAccessible(true);
        $dynamicPages = $dynamicPagesProperty->getValue($sitemapHandler);

        $this->assertCount(1, $dynamicPages, 'Dynamic pages count does not match expected value.');
        $this->assertEquals('https://example.com/dynamic-page', $dynamicPages[0]['url'], 'Dynamic page URL is incorrect.');
        $this->assertEquals(0.7, $dynamicPages[0]['priority'], 'Dynamic page priority is incorrect.');
        $this->assertEquals('2024-10-01', $dynamicPages[0]['lastmod'], 'Dynamic page last modified date is incorrect.');
    }

    /**
     * testGenerateSitemap
     *
     * Tests the generation of the sitemap.xml file.
     */
    public function testGenerateSitemap(): void
    {
        $sitemapHandler = new SitemapHandler(self::$viewsPath);
        $sitemapHandler->addDynamicPage('https://example.com/dynamic-page', 0.7, '2024-10-01');
        $sitemapHandler->generateSitemap(self::$outputDir);

        $sitemapFilePath = self::$outputDir . 'sitemap.xml';
        $this->assertFileExists($sitemapFilePath, 'Sitemap file was not created.');

        $sitemapXml = simplexml_load_file($sitemapFilePath);
        $this->assertInstanceOf(SimpleXMLElement::class, $sitemapXml, 'Sitemap XML could not be loaded.');
        $this->assertCount(2, $sitemapXml->url, 'Sitemap XML does not contain the expected number of URLs.');
    }
}
