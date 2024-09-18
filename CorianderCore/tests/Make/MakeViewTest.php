<?php

use PHPUnit\Framework\TestCase;
use CorianderCore\Console\Commands\Make\View\MakeView;

class MakeViewTest extends TestCase
{
    /**
     * @var MakeView|\PHPUnit\Framework\MockObject\MockObject $makeView
     * Holds the instance of the mocked MakeView class for testing.
     * The methods related to file system operations will be mocked to avoid actual file creation.
     */
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
            define('PROJECT_ROOT', dirname(__DIR__, 3)); // Set PROJECT_ROOT to the project's root directory.
        }
    }

    /**
     * This method is executed before each test.
     * It creates a mock of the MakeView class to mock out methods related to file system operations,
     * such as 'createDirectory', 'createFileFromTemplate', and 'viewExists'. 
     * This prevents actual changes to the file system during testing.
     */
    protected function setUp(): void
    {
        // Create a partial mock for the MakeView class, mocking only the file system methods
        $this->makeView = $this->getMockBuilder(MakeView::class)
            ->onlyMethods(['createDirectory', 'createFileFromTemplate', 'viewExists']) // Mock filesystem-related methods
            ->getMock();
    }

    /**
     * Tests the successful creation of a view when it doesn't already exist.
     * It mocks the necessary file system operations and checks that the correct success message is output.
     */
    public function testCreateViewSuccessfully()
    {
        // Define the expected view path based on PROJECT_ROOT and the view name in kebab-case
        $viewPath = PROJECT_ROOT . '/public/public_views/newview';

        // Mock 'viewExists' to return false, simulating that the view does not exist
        $this->makeView->expects($this->once())
            ->method('viewExists')
            ->with($viewPath)
            ->willReturn(false);

        // Mock the creation of the directory where the view will be stored
        $this->makeView->expects($this->once())
            ->method('createDirectory')
            ->with($viewPath);

        // Expect the output to indicate successful creation of the view
        $this->expectOutputRegex("/Success/");
        $this->expectOutputRegex("/View 'newview' created successfully at/");

        // Run the 'execute' method to trigger view creation
        $this->makeView->execute(['newview']);
    }

    /**
     * Tests the scenario where the view already exists and cannot be created again.
     * It mocks the 'viewExists' method and checks that the appropriate error message is displayed.
     */
    public function testViewAlreadyExists()
    {
        // Define the expected view path for an existing view
        $viewPath = PROJECT_ROOT . '/public/public_views/home';

        // Mock 'viewExists' to return true, simulating that the view already exists
        $this->makeView->expects($this->once())
            ->method('viewExists')
            ->with($viewPath)
            ->willReturn(true);

        // Expect the output to indicate that the view already exists
        $this->expectOutputRegex("/Error/");
        $this->expectOutputRegex("/'home' already exists./");

        // Run the 'execute' method with a view name that already exists
        $this->makeView->execute(['home']);
    }

    /**
     * Tests the scenario where no view name is provided to the 'execute' method.
     * It checks if the appropriate error message is displayed when no arguments are passed.
     */
    public function testNoViewNameProvided()
    {
        // Expect the output to indicate that a view name must be specified
        $this->expectOutputRegex("/Error/");
        $this->expectOutputRegex("/Please specify a view name./");

        // Run the 'execute' method without providing any arguments
        $this->makeView->execute([]);
    }

    /**
     * Tests that the view name is formatted correctly (kebab-case).
     * It ensures that even if the input is given in PascalCase, the view is created in kebab-case.
     */
    public function testViewNameFormatting()
    {
        // Define the expected view path with a kebab-case name
        $viewPath = PROJECT_ROOT . '/public/public_views/admin-user';

        // Mock 'viewExists' to return false, simulating that the view does not exist
        $this->makeView->expects($this->once())
            ->method('viewExists')
            ->with($viewPath)
            ->willReturn(false);

        // Mock the creation of the directory
        $this->makeView->expects($this->once())
            ->method('createDirectory')
            ->with($viewPath);

        // Expect the output to indicate successful creation of the view
        $this->expectOutputRegex("/Success/");
        $this->expectOutputRegex("/View 'admin-user' created successfully at /");

        // Run the 'execute' method to trigger view creation with PascalCase input
        $this->makeView->execute(['AdminUser']);
    }
}
