<?php

namespace CorianderCore\Tests\Make;

use PHPUnit\Framework\TestCase;
use CorianderCore\Console\Commands\Make\Database\MySQL\MakeMySQL;
use CorianderCore\Console\ConsoleOutput;
use CorianderCore\Utils\DirectoryHandler;

class MakeMySQLTest extends TestCase
{
    /**
     * @var MakeMySQL
     */
    protected $makeMySQL;

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
     * Initializes the MakeMySQL class and mocks ConsoleOutput to suppress actual output.
     */
    protected function setUp(): void
    {
        // Ensure the test path exists
        if (!is_dir(self::$testPath)) {
            mkdir(self::$testPath, 0777, true);
        }

        // Initialize the MakeMySQL class with custom config path
        $this->makeMySQL = new MakeMySQL(self::$testPath . '/config');

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
     * Tests the failure scenario when MySQL connection fails, 
     * and ensures that the appropriate error and warning messages are displayed.
     */
    public function testMysqlConnectionFailure()
    {
        // Execute the MakeMySQL process (will fail to connect to the database)
        $this->makeMySQL->execute();
        
        // Expect the output to indicate an error and warning due to a failed connection
        $this->expectOutputRegex("/\[Error\].*Connection failed/");
        $this->expectOutputRegex("/\[Warning\].*Database configuration file not created./");
    }
}