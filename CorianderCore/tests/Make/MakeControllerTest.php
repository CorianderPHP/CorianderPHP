<?php

use PHPUnit\Framework\TestCase;
use CorianderCore\Console\Commands\Make\Controller\MakeController;

class MakeControllerTest extends TestCase
{
    /**
     * @var MakeController|\PHPUnit\Framework\MockObject\MockObject $makeController 
     * Holds the mock instance of MakeController for testing.
     */
    protected $makeController;

    /**
     * @var bool $srcCreatedDuringTest
     * Flag to track if the 'src' directory was created during the test.
     */
    protected $srcCreatedDuringTest = false;

    /**
     * @var bool $controllersCreatedDuringTest
     * Flag to track if the 'Controllers' directory was created during the test.
     */
    protected $controllersCreatedDuringTest = false;

    /**
     * This method is executed once before any tests are run.
     * It ensures that the PROJECT_ROOT constant is defined,
     * which is essential for resolving paths during the test.
     */
    public static function setUpBeforeClass(): void
    {
        if (!defined('PROJECT_ROOT')) {
            define('PROJECT_ROOT', dirname(__DIR__, 3)); // Set PROJECT_ROOT to the project's root directory.
        }
    }

    /**
     * This method is executed before each test.
     * It creates a mock of the MakeController class and sets up the testing environment.
     * It also checks if the 'src' and 'Controllers' directories need to be created
     * during the test and sets flags accordingly.
     */
    protected function setUp(): void
    {
        // Check if the 'src' directory exists; set flag if not.
        if (!is_dir(PROJECT_ROOT . '/src')) {
            $this->srcCreatedDuringTest = true;
        }

        // Check if the 'Controllers' directory exists; set flag if not.
        if (!is_dir(PROJECT_ROOT . '/src/Controllers')) {
            $this->controllersCreatedDuringTest = true;
        }

        // Create a mock of MakeController with 'controllerExists' and 'createFileFromTemplate' methods mocked.
        $this->makeController = $this->getMockBuilder(MakeController::class)
            ->onlyMethods(['controllerExists', 'createFileFromTemplate']) // Mock only relevant methods for testing.
            ->getMock();
    }

    /**
     * This method is executed after each test.
     * It cleans up by deleting the 'src/Controllers' and 'src' directories if they were created during the test.
     */
    protected function tearDown(): void
    {
        if ($this->controllersCreatedDuringTest) {
            $this->deleteDirectory(PROJECT_ROOT . '/src/Controllers'); // Cleanup Controllers directory.
        }
        if ($this->srcCreatedDuringTest) {
            $this->deleteDirectory(PROJECT_ROOT . '/src'); // Cleanup src directory.
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

        $files = array_diff(scandir($dirPath), ['.', '..']); // Exclude '.' and '..' directories

        // Recursively delete files and subdirectories.
        foreach ($files as $file) {
            $filePath = "$dirPath/$file";
            is_dir($filePath) ? $this->deleteDirectory($filePath) : unlink($filePath); // Delete file or recurse for directory.
        }

        rmdir($dirPath); // Remove the directory itself.
    }

    /**
     * Test if a controller is created successfully when it doesn't already exist.
     * This checks that the correct success message is output and the controller is created in the correct location.
     */
    public function testCreateControllerSuccessfully()
    {
        // Define the expected controller file path.
        $controllerPath = PROJECT_ROOT . '/src/Controllers/TestController.php';

        // Mock 'controllerExists' to return false, simulating that the controller does not exist.
        $this->makeController->expects($this->once())
            ->method('controllerExists')
            ->with($controllerPath)
            ->willReturn(false);

        // Expect the output to include a success message.
        $this->expectOutputRegex("/Success/");
        $this->expectOutputRegex("/Controller 'TestController' created successfully at /");

        // Run the 'execute' method to trigger controller creation.
        $this->makeController->execute(['test']);
    }

    /**
     * Test if an error is displayed when a controller with the same name already exists.
     * This ensures that the correct error message is displayed and no new controller is created.
     */
    public function testControllerAlreadyExists()
    {
        // Define the expected controller file path.
        $controllerPath = PROJECT_ROOT . '/src/Controllers/ExistingController.php';

        // Mock 'controllerExists' to return true, simulating the controller already exists.
        $this->makeController->expects($this->once())
            ->method('controllerExists')
            ->with($controllerPath)
            ->willReturn(true);

        // Expect the output to include an error message indicating the controller already exists.
        $this->expectOutputRegex("/Error/");
        $this->expectOutputRegex("/Controller 'ExistingController' already exists./");

        // Run the 'execute' method with an existing controller name.
        $this->makeController->execute(['existing']);
    }

    /**
     * Test if an error is shown when no controller name is provided.
     * This ensures that the appropriate error message is shown when the controller name is missing.
     */
    public function testNoControllerNameProvided()
    {
        // Expect the output to include an error message for missing controller name.
        $this->expectOutputRegex("/Error/");
        $this->expectOutputRegex("/Please specify a controller name./");

        // Run the 'execute' method without providing any arguments.
        $this->makeController->execute([]);
    }

    /**
     * Test that the controller name is formatted correctly (PascalCase).
     * When a name in kebab-case is provided, the controller should be created in PascalCase.
     */
    public function testControllerNameFormatting()
    {
        // Define the expected controller file path for a kebab-case input.
        $controllerPath = PROJECT_ROOT . '/src/Controllers/AdminUserController.php';

        // Mock 'controllerExists' to return false, indicating the controller does not exist.
        $this->makeController->expects($this->once())
            ->method('controllerExists')
            ->with($controllerPath)
            ->willReturn(false);

        // Expect the output to include a success message with the correctly formatted controller name.
        $this->expectOutputRegex("/Success/");
        $this->expectOutputRegex("/Controller 'AdminUserController' created successfully at /");

        // Run the 'execute' method with a kebab-case name to test the formatting.
        $this->makeController->execute(['admin-user']);
    }
}
