<?php

use PHPUnit\Framework\TestCase;
use CorianderCore\Console\Commands\Make;
use CorianderCore\Console\Commands\Make\View\MakeView;

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
            define('PROJECT_ROOT', dirname(__DIR__, 3)); // Set PROJECT_ROOT to the project's root directory.
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
            ->onlyMethods(['execute']) // Mock only the 'execute' method to simulate its behavior.
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
        // Create a mock for MakeView, mocking only the 'execute' method
        $mockMakeView = $this->getMockBuilder(MakeView::class)
            ->onlyMethods(['execute'])
            ->getMock();

        // Expect that the 'execute' method in the mock will be called exactly once,
        // and that it will be called with the argument 'home' when 'make:view home' is executed
        $mockMakeView->expects($this->once())
            ->method('execute')
            ->with($this->equalTo(['home'])); // Expect 'home' as the argument

        // Reinitialize the Make class with the newly mocked MakeView
        $this->make = new Make($mockMakeView);

        // Simulate running the command: 'php coriander make:view home'
        $this->make->execute(['view', 'home']);
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
