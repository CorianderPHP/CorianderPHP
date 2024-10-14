<?php

use PHPUnit\Framework\TestCase;
use CorianderCore\Console\Commands\Make\View\MakeView;
use CorianderCore\Utils\DirectoryHandler;

class MakeViewTest extends TestCase
{
    /**
     * @var MakeView Holds the instance of the MakeView class for testing.
     */
    protected $makeView;

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
        self::$testPath = PROJECT_ROOT . "/CorianderCore/tests/_tmp/";
    }

    /**
     * Sets up the necessary conditions before each test.
     * It ensures the test directory exists and initializes the MakeView class
     * with the test path as the view path.
     */
    protected function setUp(): void
    {
        // Ensure the test path exists
        if (!is_dir(self::$testPath)) {
            mkdir(self::$testPath, 0777, true);
        }

        // Initialize the MakeView class with the test path as the view path
        $this->makeView = new MakeView(self::$testPath);
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
     * Tests the successful creation of a view when it doesn't already exist.
     * Checks that the correct success message is output after the view creation.
     */
    public function testCreateViewSuccessfully()
    {
        $viewName = "newview";

        // Run the 'execute' method to trigger view creation
        $this->makeView->execute([$viewName]);

        // Expect the output to indicate successful creation of the view
        $this->expectOutputRegex("/Success/");
        $this->expectOutputRegex("/View 'newview' created successfully at/");
    }

    /**
     * Tests the scenario where the view already exists and cannot be created again.
     * It simulates the creation of a view, then checks that the appropriate error message is displayed 
     * when attempting to create the same view again.
     */
    public function testViewAlreadyExists()
    {
        $viewName = "newview";

        // Simulate the first creation of the view
        $this->makeView->execute([$viewName]);

        // Expect the output to indicate that the view already exists
        $this->expectOutputRegex("/Error/");
        $this->expectOutputRegex("/'newview' already exists./");
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
     * Tests that the view name is formatted correctly as kebab-case.
     * It ensures that even if the input is given in PascalCase, the view is created in kebab-case.
     */
    public function testViewNameFormatting()
    {
        $viewName = "AdminUser";

        // Run the 'execute' method to trigger view creation with PascalCase input
        $this->makeView->execute([$viewName]);

        // Expect the output to indicate successful creation of the view
        $this->expectOutputRegex("/Success/");
        $this->expectOutputRegex("/View 'admin-user' created successfully at /");
    }
}
