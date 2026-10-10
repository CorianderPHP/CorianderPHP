<?php

namespace CorianderCore\Tests\Make;

use PHPUnit\Framework\TestCase;
use CorianderCore\Core\Console\Commands\Make\Controller\MakeController;
use CorianderCore\Core\Utils\DirectoryHandler;

/**
 * The MakeControllerTest class contains unit tests for the MakeController class.
 * It verifies the functionality of creating controller files based on predefined templates.
 */
class MakeControllerTest extends TestCase
{
    #[\PHPUnit\Framework\Attributes\RunInSeparateProcess]
    public function testGeneratedControllerResponsesPreserveCsrfErrorsAndJsonHeaders(): void
    {
        $_POST = ['csrf_token' => 'invalid'];
        foreach ([false, true] as $api) {
            $args = $api ? ['GeneratedResponse', '--api'] : ['GeneratedResponse'];
            ob_start();
            try {
                $this->assertSame(0, $this->makeController->execute($args));
            } finally {
                ob_end_clean();
            }
            $namespace = $api ? 'ApiControllers' : 'Controllers';
            require self::$testPath . 'src/' . $namespace . '/GeneratedResponseController.php';
            $handler = $api
                ? new \CorianderCore\Core\Router\Handlers\ApiControllerHandler()
                : new \CorianderCore\Core\Router\Handlers\WebControllerHandler();
            $path = $api ? 'api/generated-response' : 'generated-response/store';
            $response = $handler->dispatch($path, 'POST');
            $this->assertSame(403, $response->getStatusCode());
            $this->assertStringContainsString('Invalid CSRF token', (string) $response->getBody());
            $this->assertSame($api ? 'application/json; charset=utf-8' : 'text/plain; charset=utf-8',
                $response->getHeaderLine('Content-Type'));
            if ($api) {
                $getResponse = $handler->dispatch($path, 'GET');
                $this->assertSame(200, $getResponse->getStatusCode());
                $this->assertSame('application/json; charset=utf-8', $getResponse->getHeaderLine('Content-Type'));
                $this->assertSame(['status' => 'OK', 'data' => []], json_decode((string) $getResponse->getBody(), true));
                $router = new \CorianderCore\Core\Router\Router();
                $router->get($path, fn() => (new \ApiControllers\GeneratedResponseController())->get());
                $router->post($path, fn() => (new \ApiControllers\GeneratedResponseController())->post());
                $this->assertSame((string) $getResponse->getBody(), (string) $router->dispatch(
                    new \Nyholm\Psr7\ServerRequest('GET', '/' . $path))->getBody());
                $this->assertSame(403, $router->dispatch(
                    new \Nyholm\Psr7\ServerRequest('POST', '/' . $path))->getStatusCode());
            }
        }
    }

    public function testGeneratedControllersDeclareDistinctLoadableClasses(): void
    {
        foreach ([false, true] as $api) {
            foreach (['GeneratedReviewAlpha', 'GeneratedReviewBeta'] as $name) {
                $args = $api ? [$name, '--api'] : [$name];
                ob_start();
                try {
                    $this->assertSame(0, $this->makeController->execute($args));
                } finally {
                    ob_end_clean();
                }
                $namespace = $api ? 'ApiControllers' : 'Controllers';
                $class = $namespace . '\\' . $name . 'Controller';
                require self::$testPath . 'src/' . $namespace . '/' . $name . 'Controller.php';
                $this->assertTrue(class_exists($class, false));
                $this->assertInstanceOf($class, new $class());
            }
        }
    }

    /**
     * @var MakeController Holds the instance of the MakeController class for testing.
     */
    protected $makeController;

    /**
     * @var string Path to the temporary directory where test files will be created.
     */
    protected static $testPath;

    /**
     * This method is executed once before any tests are run.
     * It ensures that the PROJECT_ROOT constant is defined,
     * and sets the temporary test path.
     */
    public static function setUpBeforeClass(): void
    {
        // Define PROJECT_ROOT if it hasn't been defined already
        if (!defined('PROJECT_ROOT')) {
            define('PROJECT_ROOT', dirname(__DIR__, 3)); // Set PROJECT_ROOT to the project's root directory.
        }

        // Set the path to the temporary test directory.
        self::$testPath = PROJECT_ROOT . "/CorianderCore/Tests/_tmp/";
    }

    /**
     * Sets up the necessary conditions before each test.
     * It ensures that the test directory exists and initializes the MakeController class
     * with the test path as the base path for controller creation.
     */
    protected function setUp(): void
    {
        // Ensure the test directory exists
        if (!is_dir(self::$testPath)) {
            mkdir(self::$testPath, 0755, true);
        }

        // Initialize the MakeController class with the test path as the base path
        $this->makeController = new MakeController(self::$testPath . 'src/Controllers/');
    }

    /**
     * tearDownAfterClass
     *
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
     * Tests the successful creation of a controller when it doesn't already exist.
     * It checks that the correct success message is output and the controller file is created in the specified location.
     */
    public function testCreateControllerSuccessfully()
    {
        $controllerName = "new";

        // Run the 'execute' method to trigger controller creation.
        $this->makeController->execute([$controllerName]);

        // Expect the output to include a success message indicating controller creation.
        $this->expectOutputRegex("/Success/");
        $this->expectOutputRegex("/Controller 'NewController' created successfully at /");

        // Optionally, assert that the controller file exists in the test directory.
        $expectedControllerPath = self::$testPath . "src/Controllers/NewController.php";
        $this->assertFileExists($expectedControllerPath, "Controller file was not created at the expected path.");
    }

    /**
     * Tests the scenario where a controller with the same name already exists.
     * It ensures that the appropriate error message is displayed and no new controller is created.
     */
    public function testControllerAlreadyExists()
    {
        $controllerName = "new";

        // First, create the controller to simulate its existence.
        $this->makeController->execute([$controllerName]);

        // Capture the output of the second execution attempt.
        $this->expectOutputRegex("/Error/");
        $this->expectOutputRegex("/Controller 'NewController' already exists./");

        // Run the 'execute' method again with the same controller name to trigger the existence check.
        $this->makeController->execute([$controllerName]);

        // Optionally, assert that only one controller file exists.
        $expectedControllerPath = self::$testPath . "src/Controllers/NewController.php";
        $this->assertFileExists($expectedControllerPath, "Controller file should exist.");
        // Ensure no duplicate file was created (this is more conceptual as PHP can't create duplicate files).
    }

    /**
     * Tests the scenario where no controller name is provided to the 'execute' method.
     * It checks if the appropriate error message is displayed when no arguments are passed.
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
     * Tests that the controller name is formatted correctly (PascalCase).
     * It ensures that even if the input is given in kebab-case, the controller is created in PascalCase.
     */
    public function testControllerNameFormatting()
    {
        $controllerName = "admin-user";

        // Run the 'execute' method to trigger controller creation with kebab-case input.
        $this->makeController->execute([$controllerName]);

        // Expect the output to include a success message with the correctly formatted controller name.
        $this->expectOutputRegex("/Success/");
        $this->expectOutputRegex("/Controller 'AdminUserController' created successfully at /");

        // Optionally, assert that the controller file exists with the PascalCase name.
        $expectedControllerPath = self::$testPath . "src/Controllers/AdminUserController.php";
        $this->assertFileExists($expectedControllerPath, "Controller file was not created with the correct PascalCase name.");
    }
}
