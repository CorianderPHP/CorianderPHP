<?php

use PHPUnit\Framework\TestCase;
use CorianderCore\Console\Commands\Make\Database\MySQL\MakeMySQL;
use CorianderCore\Console\ConsoleOutput;

class MakeMySQLTest extends TestCase
{
    /**
     * @var MakeMySQL
     */
    protected $makeMySQL;

    /**
     * This method is executed once before any tests are run.
     * It ensures that the PROJECT_ROOT constant is defined, 
     * which is essential for path resolution within the framework.
     */
    public static function setUpBeforeClass(): void
    {
        // Define PROJECT_ROOT if it hasn't been defined already
        if (!defined('PROJECT_ROOT')) {
            define('PROJECT_ROOT', dirname(__DIR__, 3)); // Set PROJECT_ROOT to the project's root directory.
        }
    }

    /**
     * Sets up the necessary conditions before each test.
     * Initializes the MakeMySQL class and mocks ConsoleOutput to suppress actual output.
     */
    protected function setUp(): void
    {
        // Initialize the MakeMySQL class
        $this->makeMySQL = new MakeMySQL();

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
     * Tests the failure scenario when MySQL connection fails, 
     * and ensures that the appropriate error and warning messages are displayed.
     */
    public function testMysqlConnectionFailure()
    {
        // Create a temporary directory for the test
        $tempDir = PROJECT_ROOT . '/CorianderCore/tests/test_mysql_config';
        mkdir($tempDir, 0777, true);

        // Use reflection to set the protected properties
        $this->setProtectedProperty($this->makeMySQL, 'configPath', $tempDir);

        // Expect the output to indicate an error and warning due to a failed connection
        $this->expectOutputRegex("/\[Error\].*Connection failed/");
        $this->expectOutputRegex("/\[Warning\].*Database configuration file not created./");

        // Execute the MakeMySQL process (will fail to connect to the database)
        $this->makeMySQL->execute();

        // Clean up the temporary directory
        $this->removeDirectory($tempDir);
    }

    /**
     * Clean up the environment after each test.
     */
    protected function tearDown(): void
    {
        parent::tearDown();
    }

    /**
     * Recursively remove a directory and its contents.
     *
     * @param string $dir The directory to remove.
     */
    protected function removeDirectory($dir)
    {
        if (!is_dir($dir)) {
            return;
        }
        $items = scandir($dir);
        foreach ($items as $item) {
            if ($item == '.' || $item == '..') {
                continue;
            }
            $itemPath = $dir . '/' . $item;
            if (is_dir($itemPath)) {
                $this->removeDirectory($itemPath);
            } else {
                unlink($itemPath);
            }
        }
        rmdir($dir);
    }

    /**
     * Set a protected or private property on an object via reflection.
     *
     * @param object $object The object on which to set the property.
     * @param string $propertyName The name of the property to set.
     * @param mixed $value The value to set on the property.
     */
    protected function setProtectedProperty($object, $propertyName, $value)
    {
        $reflection = new \ReflectionClass($object);
        $property = $reflection->getProperty($propertyName);
        $property->setAccessible(true);
        $property->setValue($object, $value);
    }
}
