<?php

use PHPUnit\Framework\TestCase;
use CorianderCore\Router\Router;

class RouterTest extends TestCase
{
    protected $router;
    protected $srcCreatedDuringTest = false; // Flag to track if 'src' was created during the test
    protected $controllersCreatedDuringTest = false; // Flag to track if 'Controllers' was created

    public static function setUpBeforeClass(): void
    {
        // Define the PROJECT_ROOT constant if it isn't already defined.
        // This is required for resolving paths correctly in the router.
        if (!defined('PROJECT_ROOT')) {
            define('PROJECT_ROOT', dirname(__DIR__, 2));
        }
    }

    protected function setUp(): void
    {
        // Instantiate a new Router object before each test to ensure a clean state.
        $this->router = new Router();

        // Reset the global $_SERVER array to default values for consistency across tests.
        $_SERVER = [];
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = '/';

        // Check if the 'src' directory exists; if not, create it and set a flag for cleanup.
        if (!is_dir(PROJECT_ROOT . '/src')) {
            mkdir(PROJECT_ROOT . '/src', 0777, true);
            $this->srcCreatedDuringTest = true;
        }

        // Check if the 'src/Controllers' directory exists; if not, create it and set a flag for cleanup.
        if (!is_dir(PROJECT_ROOT . '/src/Controllers')) {
            mkdir(PROJECT_ROOT . '/src/Controllers', 0777, true);
            $this->controllersCreatedDuringTest = true;
        }
    }

    protected function tearDown(): void
    {
        // Unset the router instance after each test to prevent state leakage between tests.
        unset($this->router);

        // Clean up: Delete the 'src/Controllers' directory if it was created during the test.
        if ($this->controllersCreatedDuringTest) {
            $this->deleteDirectory(PROJECT_ROOT . '/src/Controllers');
        }

        // Clean up: Delete the 'src' directory if it was created during the test.
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
     * Test that the home page route ('/') loads the correct view.
     * This test simulates a GET request to the homepage and verifies 
     * that the router correctly loads the expected view and outputs the right content.
     */
    #[\PHPUnit\Framework\Attributes\RunInSeparateProcess]
    public function testHomePageRouteLoadsCorrectView()
    {
        // Simulate a request to the home page
        $_SERVER['REQUEST_URI'] = '/';

        // Capture the output generated by the dispatch method
        ob_start();
        $this->router->dispatch();
        $output = ob_get_clean();

        // Assert that the output contains expected content from the home page
        $this->assertStringContainsString('Installation Successful!', $output);
        // Assert that the requested view is correctly set to 'home'
        $this->assertSame('home', REQUESTED_VIEW);
    }

    /**
     * Test that the router triggers the 404 callback for a non-existent route.
     * This test simulates a GET request to a non-existent route and verifies that the 
     * custom 404 callback is executed, displaying the appropriate error message.
     */
    #[\PHPUnit\Framework\Attributes\RunInSeparateProcess]
    public function testNotFoundRoute()
    {
        // Simulate a request to a non-existent route
        $_SERVER['REQUEST_URI'] = '/non-existent-route';

        // Set a custom 404 callback that echoes a custom "Not Found" message
        $this->router->setNotFound(function () {
            echo '404 Custom Not Found';
        });

        // Capture the output generated by the dispatch method
        ob_start();
        $this->router->dispatch();
        $output = ob_get_clean();

        // Assert that the custom 404 message is displayed
        $this->assertStringContainsString('404 Custom Not Found', $output);
        // Assert that the requested view is correctly set to the non-existent route
        $this->assertSame('non-existent-route', REQUESTED_VIEW);
    }

    /**
     * Test that a custom route is added and executed properly.
     * This test simulates a GET request to the 'about' route and verifies 
     * that the correct output is generated based on the custom route's callback function.
     */
    #[\PHPUnit\Framework\Attributes\RunInSeparateProcess]
    public function testCustomRouteExecution()
    {
        // Simulate a request to the 'about' route
        $_SERVER['REQUEST_URI'] = '/about';

        // Add a custom route for 'about' that echoes a specific message
        $this->router->add('about', function () {
            echo 'About Page Content';
        });

        // Capture the output generated by the dispatch method
        ob_start();
        $this->router->dispatch();
        $output = ob_get_clean();

        // Assert that the output contains the correct content for the 'about' page
        $this->assertStringContainsString('About Page Content', $output);
        // Assert that the requested view is correctly set to 'about'
        $this->assertSame('about', REQUESTED_VIEW);
    }

    /**
     * Test that the router defaults to the home route when the request URI is empty.
     * This test simulates a request with an empty URI and verifies that the router
     * loads the home view by default, ensuring correct behavior for base URL access.
     */
    #[\PHPUnit\Framework\Attributes\RunInSeparateProcess]
    public function testDispatchEmptyRequestDefaultsToHome()
    {
        // Simulate an empty request URI
        $_SERVER['REQUEST_URI'] = '';

        // Capture the output generated by the dispatch method
        ob_start();
        $this->router->dispatch();
        $output = ob_get_clean();

        // Assert that the home page content is loaded by default
        $this->assertStringContainsString('Installation Successful!', $output);
        // Assert that the requested view is correctly set to 'home'
        $this->assertSame('home', REQUESTED_VIEW);
    }

    /**
     * Test that the router handles a request to a controller where the action method does not exist.
     * This test verifies that a 404 error is triggered when the specified action is not found in the controller.
     */
    #[\PHPUnit\Framework\Attributes\RunInSeparateProcess]
    public function testControllerRouteActionMethodDoesNotExist()
    {
        // Simulate a request to '/test-controller/non-existent-action'
        $_SERVER['REQUEST_URI'] = '/test-controller/non-existent-action';

        // Define the controller class code without the 'nonExistentAction' method
        $controllerCode = <<<PHP
        <?php
        namespace Controllers;
        class TestController
        {
            public function index()
            {
                echo 'Index action output';
            }
        }
        PHP;

        // Write the controller code to the file
        $controllerFile = PROJECT_ROOT . '/src/Controllers/TestController.php';
        file_put_contents($controllerFile, $controllerCode);

        // Set a custom 404 callback to capture the 404 output
        $this->router->setNotFound(function () {
            echo '404 Custom Not Found';
        });

        // Capture the output generated by the dispatch method
        ob_start();
        $this->router->dispatch();
        $output = ob_get_clean();

        // Assert that the custom 404 message is displayed
        $this->assertStringContainsString('404 Custom Not Found', $output);
    }

    /**
     * Test that the router falls back to the 'index' method when the action is not specified.
     * This test simulates a request to a controller without specifying an action and verifies
     * that the router calls the 'index' method of the controller.
     */
    #[\PHPUnit\Framework\Attributes\RunInSeparateProcess]
    public function testControllerRouteDefaultsToIndexMethod()
    {
        // Simulate a request to '/test-controller'
        $_SERVER['REQUEST_URI'] = '/test-controller';

        // Define the controller class code with an 'index' method
        $controllerCode = <<<PHP
        <?php
        namespace Controllers;
        class TestController
        {
            public function index()
            {
                echo 'Index action output';
            }
        }
        PHP;

        // Write the controller code to the file
        $controllerFile = PROJECT_ROOT . '/src/Controllers/TestController.php';
        file_put_contents($controllerFile, $controllerCode);

        // Capture the output generated by the dispatch method
        ob_start();
        $this->router->dispatch();
        $output = ob_get_clean();

        // Assert that the output contains the expected content from the 'index' method
        $this->assertStringContainsString('Index action output', $output);
    }

    /**
     * Test that the router attempts to load a view when the controller does not exist.
     * This test verifies that the router falls back to view loading when no controller is found.
     */
    #[\PHPUnit\Framework\Attributes\RunInSeparateProcess]
    public function testRouterFallsBackToViewWhenControllerDoesNotExist()
    {
        // Simulate a request to '/non-existent-controller'
        $_SERVER['REQUEST_URI'] = '/non-existent-controller';

        // Ensure that the controller does not exist
        $controllerFile = PROJECT_ROOT . '/src/Controllers/NonExistentController.php';
        if (file_exists($controllerFile)) {
            unlink($controllerFile);
        }

        // Create a view file for 'non-existent-controller'
        $viewDir = PROJECT_ROOT . '/public/public_views/non-existent-controller';
        if (!is_dir($viewDir)) {
            mkdir($viewDir, 0777, true);
        }
        $viewFile = $viewDir . '/index.php';
        file_put_contents($viewFile, 'View content output');

        // Capture the output generated by the dispatch method
        ob_start();
        $this->router->dispatch();
        $output = ob_get_clean();

        // Assert that the output contains the expected content from the view
        $this->assertStringContainsString('View content output', $output);

        // Clean up: Delete the view file and directory
        unlink($viewFile);
        rmdir($viewDir);
    }
}
