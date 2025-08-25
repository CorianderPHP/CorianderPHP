<?php

namespace CorianderCore\Tests\Make;

use PHPUnit\Framework\TestCase;
use CorianderCore\Core\Console\Commands\Make\Database\SQLite\MakeSQLite;
use CorianderCore\Core\Console\ConsoleOutput;
use CorianderCore\Core\Utils\DirectoryHandler;

class MakeSQLiteTest extends TestCase
{
    /**
     * @var MakeSQLite
     */
    protected $makeSQLite;

    /**
     * @var string Path to the temporary directory for testing.
     */
    protected static $testPath;

    /**
     * This method is executed once before any tests are run.
     * It ensures that the PROJECT_ROOT constant is defined, 
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
     */
    protected function setUp(): void
    {
        // Ensure the test path exists
        if (!is_dir(self::$testPath)) {
            mkdir(self::$testPath, 0777, true);
        }

        // Initialize the MakeSQLite class with custom paths
        $this->makeSQLite = new MakeSQLite(self::$testPath . '/config', self::$testPath . '/database');

        // Mock the ConsoleOutput class to suppress actual output during tests
        $consoleOutputMock = $this->getMockBuilder(ConsoleOutput::class)
            ->onlyMethods(['print', 'hr'])
            ->getMock();

        // Simulate output behavior
        $consoleOutputMock->expects($this->any())
            ->method('print')
            ->willReturnCallback(function ($message) {
                echo $message;
            });
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
     * Test the execution of the MakeSQLite process, ensuring it handles SQLite configuration creation.
     */
    public function testExecuteSqlite()
    {
        // Execute the MakeSQLite process
        $this->makeSQLite->execute('test');

        // Expect the output to indicate success
        $this->expectOutputRegex("/\[Success\].*Database test.sqlite created in folder/");
        // Check if the SQLite files and config file were created
        $this->assertFileExists(self::$testPath . '/database/clean_test.sqlite');
        $this->assertFileExists(self::$testPath . '/database/test.sqlite');
        $this->assertFileExists(self::$testPath . '/config/database.php');
    }
}
