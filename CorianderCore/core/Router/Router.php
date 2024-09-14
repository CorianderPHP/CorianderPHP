<?php

namespace CorianderCore\Router;

/**
 * Router class responsible for handling page routing, including routing to controllers and views.
 *
 * This class manages incoming requests by routing them to the appropriate controller action
 * or view. It also handles 404 errors if the requested controller or view is unavailable.
 */
class Router
{
    /**
     * @var array List of manually defined custom routes with their corresponding callbacks.
     */
    protected $routes = [];

    /**
     * @var callable Callback function to handle 404 (not found) requests.
     */
    protected $notFoundCallback;

    /**
     * Add a custom route with a corresponding callback function.
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
     * or a view, then executes the corresponding logic.
     */
    public function dispatch()
    {
        // Extract the requested route from the URL, ignoring query parameters
        $requestUri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
        $request = trim($requestUri, '/');

        // Default to 'home' if the request is empty
        if ($request === '') {
            $request = 'home';
        }

        define('REQUESTED_VIEW', $request);

        // Check if the request matches any custom routes
        if (isset($this->routes[$request])) {
            call_user_func($this->routes[$request]);
            return;
        }

        // Split the request into segments for controller, action, and parameters
        $segments = explode('/', $request);

        // Determine the controller name
        $controllerSegment = $segments[0];
        $controllerName = $this->formatControllerName($controllerSegment) . 'Controller';
        $controllerClass = 'Controllers\\' . $controllerName;

        // Determine the action name (default to 'index')
        $action = isset($segments[1]) && $segments[1] !== '' ? $segments[1] : 'index';

        // Collect any additional parameters
        $params = array_slice($segments, 2);

        // Check if the controller class exists
        if (class_exists($controllerClass)) {
            $controller = new $controllerClass();

            // Check if the method exists in the controller
            if (method_exists($controller, $action)) {
                call_user_func_array([$controller, $action], $params);
            } else {
                // No suitable method found, handle 404
                $this->handleNotFound();
            }
            return;
        }

        // If no controller found, attempt to load the view
        $this->loadViewWithMetadata($request);
    }

    /**
     * Formats the controller name to PascalCase.
     *
     * Converts names like 'admin_user', 'admin-user', or 'adminUser' into 'AdminUser'.
     *
     * @param string $name The original controller name.
     * @return string The formatted controller name in PascalCase.
     */
    private function formatControllerName(string $name): string
    {
        return str_replace(' ', '', ucwords(str_replace(['-', '_'], ' ', $name)));
    }

    /**
     * Handle a 404 Not Found error by invoking the notFoundCallback or displaying a default message.
     */
    private function handleNotFound()
    {
        if ($this->notFoundCallback) {
            call_user_func($this->notFoundCallback);
        } else {
            header('HTTP/1.0 404 Not Found');
            echo "404 Not Found";
        }
    }

    /**
     * Load the requested view and its associated metadata if available.
     *
     * @param string $request The requested route (e.g., 'home', 'about').
     */
    private function loadViewWithMetadata($request)
    {
        // Define paths to the view file and optional metadata file
        $viewPath = PROJECT_ROOT . '/public/public_views/' . $request . '/index.php';
        $metaDataFile = PROJECT_ROOT . '/public/public_views/' . $request . '/metadata.php';

        // Default metadata values
        $lang = 'en';
        $metaTitle = 'No configured title';
        $metaDescription = 'No configured description.';

        // Override the default metadata if a metadata.php file exists
        if (file_exists($metaDataFile)) {
            include $metaDataFile;
        }

        // Include the header, view, and footer files if the view exists
        if (file_exists($viewPath)) {
            require_once PROJECT_ROOT . '/public/public_views/header.php';
            require_once $viewPath;
            require_once PROJECT_ROOT . '/public/public_views/footer.php';
        } else {
            // If no view found, handle 404
            $this->handleNotFound();
        }
    }
}
