<?php

namespace CorianderCore\Tests\Make;

use PHPUnit\Framework\TestCase;
use CorianderCore\Console\Commands\Make\Sitemap\MakeSitemap;
use CorianderCore\Utils\DirectoryHandler;

class MakeSitemapTest extends TestCase
{
    /**
     * @var MakeSitemap Holds the instance of the MakeSitemap class for testing.
     */
    protected $makeSitemap;

    /**
     * @var string Path to the temporary directory for testing.
     */
    protected static $testPath;

    /**
     * This method is executed once before any tests are run.
     * It ensures that the PROJECT_ROOT constant is defined
     * and sets the test path to a temporary folder.
     */
    public static function setUpBeforeClass(): void
    {
        // Define PROJECT_ROOT if it hasn't been defined already
        if (!defined('PROJECT_ROOT')) {
            define('PROJECT_ROOT', dirname(__DIR__, 3)); // Set PROJECT_ROOT to the project's root directory.
        }

        // Set the path to the temporary test directory
        self::$testPath = PROJECT_ROOT . "/CorianderCore/tests/_tmp";
    }

    /**
     * Sets up the necessary conditions before each test.
     * It initializes the MakeSitemap class and ensures the test path exists.
     */
    protected function setUp(): void
    {
        // Ensure the test path exists
        if (!is_dir(self::$testPath)) {
            mkdir(self::$testPath, 0777, true);
        }

        // Initialize the MakeSitemap class
        $this->makeSitemap = new MakeSitemap(self::$testPath . '/sitemap.php');
    }

    /**
     * This method runs once after all tests in the class have completed.
     * It cleans up the test environment by removing the temporary test directory and its contents.
     */
    public static function tearDownAfterClass(): void
    {
        // Cleanup: Remove test files and directories if they exist
        if (is_dir(self::$testPath)) {
            DirectoryHandler::deleteDirectory(self::$testPath); // Cleanup the temporary directory.
        }
    }

    /**
     * Tests the successful creation of a sitemap when it doesn't already exist.
     * Checks that the correct success message is output and the sitemap is created at the specified path.
     */
    public function testCreateSitemapSuccessfully(): void
    {
        // Run the 'execute' method to trigger sitemap creation
        $this->makeSitemap->execute();

        // Check if the success message is printed
        $this->expectOutputRegex("/Success/");
        $this->expectOutputRegex("/Sitemap created successfully at/");
    }

    /**
     * Tests the scenario where the sitemap already exists and checks that the appropriate error message is displayed.
     */
    public function testSitemapAlreadyExists(): void
    {
        // Run the 'execute' method to attempt creating a sitemap that already exists
        $this->makeSitemap->execute();

        // Check if the error message is printed
        $this->expectOutputRegex("/Error/");
        $this->expectOutputRegex("/Sitemap already exists at/");
    }
}
