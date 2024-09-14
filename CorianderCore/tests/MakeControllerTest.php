<?php

use PHPUnit\Framework\TestCase;
use CorianderCore\Console\Commands\Controller\MakeController;

class MakeControllerTest extends TestCase
{
    /**
     * @var MakeController|\PHPUnit\Framework\MockObject\MockObject $makeController Holds the instance of MakeController for testing.
     */
    protected $makeController;
    protected $srcCreatedDuringTest = false; // Flag to track if src was created during the test
    protected $controllersCreatedDuringTest = false; // Flag to track if Controllers were created

    /**
     * This method is executed once before any tests are run.
     * It ensures that the PROJECT_ROOT constant is defined,
     * which is essential for resolving paths during the test.
     */
    public static function setUpBeforeClass(): void
    {
        if (!defined('PROJECT_ROOT')) {
            define('PROJECT_ROOT', dirname(__DIR__, 2));
        }
    }

    /**
     * This method is executed before each test.
     * It creates a mock of the MakeController class to mock out methods related to file system operations.
     */
    protected function setUp(): void
    {
        // Check if the 'src' directory already exists before the test runs
        if (!is_dir(PROJECT_ROOT . '/src')) {
            $this->srcCreatedDuringTest = true;
        }

        // Check if the 'Controllers' directory already exists before the test runs
        if (!is_dir(PROJECT_ROOT . '/src/Controllers')) {
            $this->controllersCreatedDuringTest = true;
        }

        // Mock 'controllerExists' and 'createFileFromTemplate' only, since those are defined in the class.
        $this->makeController = $this->getMockBuilder(MakeController::class)
            ->onlyMethods(['controllerExists', 'createFileFromTemplate']) // Mock the relevant methods
            ->getMock();
    }

    /**
     * This method is executed after each test.
     * It ensures the 'src/Controllers' directory is cleaned up if it was created by the test.
     */
    protected function tearDown(): void
    {
        if ($this->controllersCreatedDuringTest) {
            $this->deleteDirectory(PROJECT_ROOT . '/src/Controllers');
        }
        if ($this->srcCreatedDuringTest) {
            $this->deleteDirectory(PROJECT_ROOT . '/src');
        }
    }

    /**
     * Helper method to recursively delete a directory.
     *
     * @param string $dirPath The path to the directory to delete.
     */
    protected function deleteDirectory(string $dirPath)
    {
        if (!is_dir($dirPath)) {
            return;
        }

        $files = array_diff(scandir($dirPath), ['.', '..']);

        foreach ($files as $file) {
            $filePath = "$dirPath/$file";
            is_dir($filePath) ? $this->deleteDirectory($filePath) : unlink($filePath);
        }

        rmdir($dirPath); // Remove the directory itself
    }

    /**
     * Test if a controller is created successfully when it doesn't already exist.
     */
    public function testCreateControllerSuccessfully()
    {
        // Define the expected controller path
        $controllerPath = PROJECT_ROOT . '/src/Controllers/TestController.php';

        // Mock 'controllerExists' to return false, simulating that the controller doesn't exist
        $this->makeController->expects($this->once())
            ->method('controllerExists')
            ->with($controllerPath)
            ->willReturn(false);

        // Expect success message
        $this->expectOutputString("Controller 'TestController' created successfully at '{$controllerPath}'." . PHP_EOL);

        // Run the 'execute' method to create a new controller
        $this->makeController->execute(['test']);
    }

    /**
     * Test if an error is displayed when a controller with the same name already exists.
     */
    public function testControllerAlreadyExists()
    {
        // Define the expected controller path
        $controllerPath = PROJECT_ROOT . '/src/Controllers/ExistingController.php';

        // Mock 'controllerExists' to return true, simulating the controller already exists
        $this->makeController->expects($this->once())
            ->method('controllerExists')
            ->with($controllerPath)
            ->willReturn(true);

        // Expect error message
        $this->expectOutputString("Error: Controller 'ExistingController' already exists." . PHP_EOL);

        // Run the 'execute' method with a controller that already exists
        $this->makeController->execute(['existing']);
    }

    /**
     * Test if an error is shown when no controller name is provided.
     */
    public function testNoControllerNameProvided()
    {
        // Expect error message for missing controller name
        $this->expectOutputString("Error: Please specify a controller name." . PHP_EOL);

        // Run the 'execute' method without providing any arguments
        $this->makeController->execute([]);
    }

    /**
     * Test that the controller name is formatted correctly (PascalCase).
     */
    public function testControllerNameFormatting()
    {
        // Define the expected controller path for a kebab-case input
        $controllerPath = PROJECT_ROOT . '/src/Controllers/AdminUserController.php';

        // Mock 'controllerExists' to return false
        $this->makeController->expects($this->once())
            ->method('controllerExists')
            ->with($controllerPath)
            ->willReturn(false);

        // Expect success message with properly formatted controller name
        $this->expectOutputString("Controller 'AdminUserController' created successfully at '{$controllerPath}'." . PHP_EOL);

        // Run the 'execute' method with a kebab-case name
        $this->makeController->execute(['admin-user']);
    }
}
