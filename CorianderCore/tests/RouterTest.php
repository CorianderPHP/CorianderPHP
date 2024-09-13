<?php

use PHPUnit\Framework\TestCase;
use CorianderCore\Router\Router;

class RouterTest extends TestCase
{
    protected $router;

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
    }

    protected function tearDown(): void
    {
        // Unset the router instance after each test to prevent state leakage between tests.
        unset($this->router);
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
}
