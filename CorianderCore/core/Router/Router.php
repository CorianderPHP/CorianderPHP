<?php

namespace CorianderCore\Router;

/**
 * Router class responsible for handling page routing and automatic view loading.
 *
 * This class manages incoming requests by routing them to the correct view or
 * custom callback. It also handles the inclusion of metadata files and defines
 * a 404 error page if the requested view is unavailable.
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
     * This method allows the addition of custom routes. When a request matches
     * the specified route pattern, the associated callback function is executed.
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
     * This method specifies a function to be called when the requested route or view
     * is not found, typically used to display a custom 404 error page.
     *
     * @param callable $callback The function to call when a 404 error occurs.
     */
    public function setNotFound($callback)
    {
        $this->notFoundCallback = $callback;
    }

    /**
     * Dispatch the current request to load the appropriate view or execute a callback.
     *
     * This method identifies the requested route from the URL, attempts to load the
     * corresponding view and metadata, and includes the header and footer for the page.
     * If no matching route or view is found, a 404 error is triggered.
     */
    public function dispatch()
    {
        // Extract the requested route from the URL
        $request = trim($_SERVER['REQUEST_URI'], '/');

        // Default to 'home' if the request is empty or explicitly requesting 'index.php'
        if ($request === '' || $request === 'index.php') {
            $request = 'home'; // Default route is 'home'
        }

        define('REQUESTED_VIEW', $request);
        $REQUESTED_VIEW = $request;

        // Check if the request matches any custom routes
        if (isset($this->routes[$request])) {
            call_user_func($this->routes[$request]);
            return;
        }

        // Load the appropriate view and its metadata, or trigger a 404 if not found
        $this->loadViewWithMetadata($request);
    }

    /**
     * Load the requested view and its associated metadata if available.
     *
     * This method looks for the view in the /public/public_views/ directory. If a
     * metadata.php file is found in the same directory as the view, it is used to override
     * the default meta title and description for the page.
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
            // If no view found, call the 404 callback or display a default 404 message
            if ($this->notFoundCallback) {
                call_user_func($this->notFoundCallback);
            } else {
                header('HTTP/1.0 404 Not Found');
                echo "404 Not Found";
            }
        }
    }
}