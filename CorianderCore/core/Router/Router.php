<?php

namespace CorianderCore\Router;

/**
 * Router class responsible for handling page routing, including routing to controllers and views.
 *
 * This class manages incoming requests by routing them to the appropriate controller action
 * or view. It also handles 404 errors if the requested controller or view is unavailable.
 *
 * It uses the Singleton pattern to ensure that only one instance of the Router is created.
 * 
 * @package CorianderCore\Router
 */
class Router
{
    /**
     * Static instance of the Router.
     *
     * @var Router
     */
    private static $instance;

    /**
     * List of manually defined custom routes with their corresponding callbacks.
     *
     * @var array<string, callable>
     */
    protected $routes = [];

    /**
     * Callback function to handle 404 (not found) requests.
     *
     * @var callable|null
     */
    protected $notFoundCallback;

    /**
     * Router constructor.
     * Initializes the router and sets the static instance.
     * 
     * This is a private constructor to enforce the singleton pattern.
     */
    private function __construct()
    {
        // Set the static instance
        self::$instance = $this;
    }

    /**
     * Get the static instance of the router.
     *
     * Ensures that only one instance of the router exists throughout the application.
     * If no instance exists, it creates one.
     *
     * @return Router The singleton instance of the Router.
     */
    public static function getInstance(): Router
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    /**
     * Add a custom route with a corresponding callback function.
     *
     * Adds a route to the router that can be matched against incoming requests.
     *
     * @param string $route The route pattern (e.g., 'home', 'about').
     * @param callable $callback The function to execute when the route matches.
     */
    public function add($route, $callback)
    {
        $this->routes[$route] = $callback;
    }

    /**
     * Set the callback function for handling 404 (not found) errors.
     *
     * @param callable $callback The function to call when a 404 error occurs.
     */
    public function setNotFound($callback)
    {
        $this->notFoundCallback = $callback;
    }

    /**
     * Dispatch the current request to the appropriate controller action or view.
     *
     * This method parses the request URI to determine whether to route to a controller
     * or a view, then executes the corresponding logic. It attempts to match the request
     * with any custom routes first, then falls back to a controller or view.
     */
    public function dispatch()
    {
        // Extract the requested route from the URL, ignoring query parameters
        $requestUri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
        $request = trim($requestUri, '/');
        $requestMethod = $_SERVER['REQUEST_METHOD'];

        // Default to 'home' if the request is empty
        if ($request === '') {
            $request = 'home';
        }

        defined('REQUESTED_VIEW') ?: define('REQUESTED_VIEW', $request);

        // Check if the request matches any custom routes
        if (isset($this->routes[$request])) {
            call_user_func($this->routes[$request]);
            return;
        }

        // Split the request into segments for controller, action, and parameters
        $segments = explode('/', $request);

        $controllerSegment = $segments[0];
        $controllerName = $this->formatControllerName($controllerSegment);

        // Ensure the controller name ends with 'Controller' only if it doesn't already
        if (!str_ends_with($controllerName, 'Controller')) {
            $controllerName .= 'Controller';
        }

        // Build the path to the controller file
        $controllerFilePath = PROJECT_ROOT . '/src/Controllers/' . $controllerName . '.php';

        // Check if the controller file exists
        if (file_exists($controllerFilePath)) {
            // Include the controller file
            require_once $controllerFilePath;

            $controllerClass = 'Controllers\\' . $controllerName;

            // Check if the class exists
            if (class_exists($controllerClass)) {
                $controller = new $controllerClass();

                // Determine the action name (default to 'index')
                $action = isset($segments[1]) && $segments[1] !== '' ? $segments[1] : 'index';

                // Collect any additional parameters
                $params = array_slice($segments, 2);

                if ($requestMethod === 'POST' && method_exists($controller, 'store')) {
                    call_user_func_array([$controller, 'store'], $params);
                } elseif (method_exists($controller, $action)) {
                    call_user_func_array([$controller, $action], $params);
                } else {
                    $this->handleNotFound();
                }
                return;
            }
        }

        // If no controller file found, attempt to load the view
        $this::renderView($request);
    }

    /**
     * Formats the controller name to PascalCase.
     *
     * Converts names like 'admin_user', 'admin-user', or 'adminUser' into 'AdminUser'.
     * It ensures that the controller class names follow a consistent naming convention.
     *
     * @param string $name The original controller name.
     * @return string The formatted controller name in PascalCase.
     */
    public function formatControllerName(string $name): string
    {
        // Handle cases where $name might be empty or invalid
        if (empty($name)) {
            return ''; // return an empty string instead of null
        }

        // Convert kebab-case or snake_case to PascalCase
        return str_replace(' ', '', ucwords(str_replace(['-', '_'], ' ', $name)));
    }

    /**
     * Handle a 404 Not Found error by invoking the notFoundCallback or displaying a default message.
     *
     * This method is called when no matching route, controller, or view is found for the request.
     */
    private static function handleNotFound()
    {
        if (self::$instance && self::$instance->notFoundCallback) {
            call_user_func(self::$instance->notFoundCallback);
        } else {
            header('HTTP/1.0 404 Not Found');
            echo "404 Not Found";
        }
    }

    /**
     * Render a view with optional metadata and data.
     *
     * This method loads the requested view, includes its metadata if available,
     * and passes optional data to the view. It is used by both direct view rendering
     * and routing via the dispatch method.
     *
     * @param string $viewPath The path to the view relative to the public_views directory.
     * @param array<string, mixed> $data Optional associative array of data to pass to the view.
     */
    public static function renderView(string $viewPath, array $data = [])
    {
        // Extract the data array to variables for use in the view
        extract($data);

        // Define paths to the view file and optional metadata file
        $fullViewPath = PROJECT_ROOT . '/public/public_views/' . $viewPath . '/index.php';

        // Include the header, view, and footer files if the view exists
        if (file_exists($fullViewPath)) {
            require_once PROJECT_ROOT . '/public/public_views/header.php';
            require_once $fullViewPath;
            require_once PROJECT_ROOT . '/public/public_views/footer.php';
        } else {
            // If no view found, handle 404
            self::handleNotFound();
        }
    }
}

