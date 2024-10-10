<?php

use PHPUnit\Framework\TestCase;
use CorianderCore\Console\Commands\Make\Sitemap\MakeSitemap;
use CorianderCore\Console\ConsoleOutput;

class MakeSitemapTest extends TestCase
{
    /**
     * @var MakeSitemap|\PHPUnit\Framework\MockObject\MockObject $makeSitemap
     * Holds the instance of the mocked MakeSitemap class for testing.
     */
    protected $makeSitemap;

    /**
     * This method is executed once before any tests are run.
     * It ensures that the PROJECT_ROOT constant is defined,
     * which is essential for resolving paths during the test.
     */
    public static function setUpBeforeClass(): void
    {
        // Define PROJECT_ROOT if it hasn't been defined already
        if (!defined('PROJECT_ROOT')) {
            define('PROJECT_ROOT', dirname(__DIR__, 3)); // Set PROJECT_ROOT to the project's root directory.
        }
    }

    /**
     * This method is executed before each test.
     * It creates a mock of the MakeSitemap class to mock out methods related to file system operations,
     * such as 'createFileFromTemplate' and 'sitemapExists'.
     * This prevents actual changes to the file system during testing.
     */
    protected function setUp(): void
    {
        // Create a partial mock for the MakeSitemap class, mocking only the file system methods
        $this->makeSitemap = $this->getMockBuilder(MakeSitemap::class)
            ->onlyMethods(['createFileFromTemplate', 'sitemapExists']) // Mock filesystem-related methods
            ->getMock();
    }

    /**
     * Tests the successful creation of a sitemap when it doesn't already exist.
     * It mocks the necessary file system operations and checks that the correct success message is output.
     */
    public function testCreateSitemapSuccessfully(): void
    {
        // Define the expected sitemap file path
        $sitemapFilePath = PROJECT_ROOT . '/public/sitemap.php';

        // Mock 'sitemapExists' to return false, simulating that the sitemap does not exist
        $this->makeSitemap->expects($this->once())
            ->method('sitemapExists')
            ->with($sitemapFilePath)
            ->willReturn(false);

        // Mock the creation of the sitemap file from the template
        $this->makeSitemap->expects($this->once())
            ->method('createFileFromTemplate')
            ->with('sitemap.php', $sitemapFilePath);

        // Mock the ConsoleOutput to check if the success message is printed
        $this->expectOutputRegex("/Success/");
        $this->expectOutputRegex("/Sitemap created successfully at/");

        // Run the 'execute' method to trigger sitemap creation
        $this->makeSitemap->execute([]);
    }

    /**
     * Tests the scenario where the sitemap already exists and cannot be created again.
     * It mocks the 'sitemapExists' method and checks that the appropriate error message is displayed.
     */
    public function testSitemapAlreadyExists(): void
    {
        // Define the expected sitemap file path
        $sitemapFilePath = PROJECT_ROOT . '/public/sitemap.php';

        // Mock 'sitemapExists' to return true, simulating that the sitemap already exists
        $this->makeSitemap->expects($this->once())
            ->method('sitemapExists')
            ->with($sitemapFilePath)
            ->willReturn(true);

        // Mock the ConsoleOutput to check if the error message is printed
        $this->expectOutputRegex("/Error/");
        $this->expectOutputRegex("/Sitemap already exists at/");

        // Run the 'execute' method to attempt creating a sitemap that already exists
        $this->makeSitemap->execute([]);
    }

    /**
     * Tests the scenario where the template file does not exist, leading to an exception.
     * It mocks the 'createFileFromTemplate' method to throw an exception.
     */
    public function testTemplateFileNotFound(): void
    {
        // Define the expected sitemap file path
        $sitemapFilePath = PROJECT_ROOT . '/public/sitemap.php';

        // Mock 'sitemapExists' to return false, simulating that the sitemap does not exist
        $this->makeSitemap->expects($this->once())
            ->method('sitemapExists')
            ->with($sitemapFilePath)
            ->willReturn(false);

        // Mock 'createFileFromTemplate' to throw an exception, simulating a missing template
        $this->makeSitemap->expects($this->once())
            ->method('createFileFromTemplate')
            ->with('sitemap.php', $sitemapFilePath)
            ->willThrowException(new \Exception("Error: Template 'sitemap.php' not found."));

        // Mock the ConsoleOutput to check if the error message is printed
        $this->expectOutputRegex("/Error/");
        $this->expectOutputRegex("/Template 'sitemap.php' not found/");

        // Run the 'execute' method to trigger the error
        $this->makeSitemap->execute([]);
    }
}