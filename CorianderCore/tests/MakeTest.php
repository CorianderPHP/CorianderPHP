<?php

namespace CorianderCore\Tests;

use PHPUnit\Framework\TestCase;
use CorianderCore\Core\Console\Commands\Make;
use CorianderCore\Core\Console\Commands\Make\View\MakeView;

class MakeTest extends TestCase
{
    /**
     * @var Make $make
     * Holds the instance of the Make command for testing.
     */
    protected $make;

    /**
     * This method is executed once before any tests are run.
     * It ensures that the PROJECT_ROOT constant is defined, 
     * which is essential for path resolution within the framework.
     */
    public static function setUpBeforeClass(): void
    {
        // Define PROJECT_ROOT if it hasn't been defined already
        if (!defined('PROJECT_ROOT')) {
            define('PROJECT_ROOT', dirname(__DIR__, 2)); // Set PROJECT_ROOT to the project's root directory.
        }
    }

    /**
     * This method is executed before each test.
     */
    protected function setUp(): void
    {
        // Initialize the Make class with the mocked MakeView
        $this->make = new Make();
    }

    /**
     * Tests the output when an invalid make command is passed.
     * This test checks that the appropriate error message is displayed when the command is unknown.
     */
    public function testInvalidMakeCommand()
    {
        // Expect the output to contain the error message for an invalid make command
        $this->expectOutputRegex("/Unknown make command: make:invalid/");

        // Run the make command with an invalid subcommand 'invalid'
        $this->make->execute(['invalid']);
    }

    /**
     * Tests the output when no arguments are passed to the make command.
     * This test verifies that the error message listing available commands is displayed.
     */
    public function testNoArgumentsPassedToMakeCommand()
    {
        // Expect the output to contain the message listing available make commands
        $this->expectOutputRegex("/Available make commands:/");

        // Run the make command with no arguments
        $this->make->execute([]);
    }

    /**
     * Tests the output when the 'make:view' command is executed without providing a view name.
     * This test ensures that an appropriate error message is displayed indicating a missing view name.
     */
    public function testMakeViewWithoutViewName()
    {
        // Expect an error message indicating that no view name was provided
        $this->expectOutputRegex("/Please specify a view name, e.g., 'make:view agenda'/");

        // Run the 'make:view' command without providing a view name
        $this->make->execute(['view']);
    }
}
