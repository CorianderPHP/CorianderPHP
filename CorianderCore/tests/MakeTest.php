<?php

use PHPUnit\Framework\TestCase;
use CorianderCore\Console\Commands\Make;
use CorianderCore\Console\Commands\View\MakeView;

class MakeTest extends TestCase
{
    /**
     * @var Make Holds the instance of the Make command for testing.
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
            define('PROJECT_ROOT', dirname(__DIR__, 2));
        }
    }

    /**
     * This method is executed before each test.
     * It creates a mock of the MakeView class to avoid actual filesystem operations 
     * and initializes the Make class with the mock.
     */
    protected function setUp(): void
    {
        // Create a mock for MakeView, only mocking the 'execute' method
        $mockMakeView = $this->getMockBuilder(MakeView::class)
            ->onlyMethods(['execute']) // Mock only the 'execute' method
            ->getMock();

        // Initialize the Make class with the mocked MakeView
        $this->make = new Make($mockMakeView);
    }

    /**
     * Tests whether the 'make:view' command properly delegates the task 
     * of view creation to the MakeView class's 'execute' method.
     */
    public function testMakeViewCommandDelegatesToMakeView()
    {
        // Create a mock for MakeView, only mocking the 'execute' method
        $mockMakeView = $this->getMockBuilder(MakeView::class)
            ->onlyMethods(['execute'])
            ->getMock();

        // Expect that the 'execute' method in the mock will be called once,
        // with the argument 'home' when make:view is executed
        $mockMakeView->expects($this->once())
            ->method('execute')
            ->with($this->equalTo(['home'])); // Expect 'home' as the argument

        // Reinitialize the Make class with the mocked MakeView
        $this->make = new Make($mockMakeView);

        // Simulate running the command: 'php coriander make:view home'
        $this->make->execute(['view', 'home']);
    }

    /**
     * Tests the output when an invalid make command is passed.
     * It checks that the error message starts with the correct string.
     */
    public function testInvalidMakeCommand()
    {
        // Expect the error message to start with the specified string
        $this->expectOutputRegex("/^Error: Unknown make command 'invalid'. Valid commands are:/");

        // Run the make command with an invalid subcommand
        $this->make->execute(['invalid']);
    }

    /**
     * Tests the output when no arguments are passed to the make command.
     * It checks that an error message is displayed indicating the need for arguments.
     */
    public function testNoArgumentsPassedToMakeCommand()
    {
        // Expect an error message indicating that no arguments were passed
        $this->expectOutputString("Error: Invalid make command. Use 'make:view', 'make:controller', etc." . PHP_EOL);

        // Run the make command with no arguments
        $this->make->execute([]);
    }

    /**
     * Tests the output when the 'make:view' command is executed without providing a view name.
     * It checks that the appropriate error message is displayed.
     */
    public function testMakeViewWithoutViewName()
    {
        // Expect an error message indicating that no view name was provided
        $this->expectOutputString("Error: Please specify a view name, e.g., 'make:view home'." . PHP_EOL);

        // Run the 'make:view' command without providing a view name
        $this->make->execute(['view']);
    }
}
