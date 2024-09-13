<?php

use PHPUnit\Framework\TestCase;
use CorianderCore\Console\Commands\View\MakeView;

class MakeViewTest extends TestCase
{
    /**
     * @var MakeView Holds the instance of the MakeView class for testing.
     */
    /** @var MakeView|\PHPUnit\Framework\MockObject\MockObject $mockMakeView */
    protected $makeView;

    /**
     * This method is executed once before any tests are run.
     * It ensures that the PROJECT_ROOT constant is defined,
     * which is essential for resolving paths during the test.
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
     * It creates a mock of the MakeView class to mock out methods related to file system operations.
     * The goal is to avoid actual creation of directories or files during testing.
     */
    protected function setUp(): void
    {
        // Create a partial mock for the MakeView class, mocking out 'createDirectory', 'createFileFromTemplate', and 'viewExists'
        $this->makeView = $this->getMockBuilder(MakeView::class)
            ->onlyMethods(['createDirectory', 'createFileFromTemplate', 'viewExists']) // Only mock the filesystem-related methods
            ->getMock();
    }

    /**
     * Tests the successful creation of a view when it doesn't already exist.
     * It mocks the necessary file system operations and checks for correct output.
     */
    public function testCreateViewSuccessfully()
    {
        // Define the expected view path based on PROJECT_ROOT and the view name
        $viewPath = PROJECT_ROOT . '/public/public_views/newview';

        // Mock the 'viewExists' method to return false, simulating that the view does not exist
        $this->makeView->expects($this->once())
            ->method('viewExists')
            ->with($viewPath)
            ->willReturn(false);

        // Mock the creation of the directory where the view will be stored
        $this->makeView->expects($this->once())
            ->method('createDirectory')
            ->with($viewPath);

        // Expect the output to indicate successful creation of the view
        $this->expectOutputString("View 'newview' created successfully at '{$viewPath}'." . PHP_EOL);

        // Run the 'execute' method to trigger view creation
        $this->makeView->execute(['newview']);
    }

    /**
     * Tests the scenario where the view already exists and cannot be created again.
     * It checks if the appropriate error message is displayed.
     */
    public function testViewAlreadyExists()
    {
        // Define the expected view path for an existing view
        $viewPath = PROJECT_ROOT . '/public/public_views/home';

        // Mock the 'viewExists' method to return true, simulating that the view already exists
        $this->makeView->expects($this->once())
            ->method('viewExists')
            ->with($viewPath)
            ->willReturn(true);

        // Expect the output to indicate that the view already exists
        $this->expectOutputString("Error: View 'home' already exists." . PHP_EOL);

        // Run the 'execute' method with a view name that already exists
        $this->makeView->execute(['home']);
    }

    /**
     * Tests the scenario where no view name is provided to the 'execute' method.
     * It checks if the appropriate error message is displayed.
     */
    public function testNoViewNameProvided()
    {
        // Expect the output to indicate that a view name must be specified
        $this->expectOutputString("Error: Please specify a view name." . PHP_EOL);

        // Run the 'execute' method without providing any arguments
        $this->makeView->execute([]);
    }
}
