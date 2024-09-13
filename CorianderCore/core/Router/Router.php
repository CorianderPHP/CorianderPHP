<?php

namespace CorianderCore\Router;

/**
 * Router class for handling page routing and automatic view loading.
 *
 * This class handles the routing of requests, automatically including
 * the correct view and associated metadata (if available). It also handles
 * custom routes and a 404 not found page when the requested view is unavailable.
 */
class Router
{
    /**
     * @var array Array of manually defined custom routes.
     */
    protected $routes = [];

    /**
     * @var callable Callback function for handling 404 (not found) requests.
     */
    protected $notFoundCallback;

    /**
     * Add a custom route with a specific callback function.
     *
     * This method allows the addition of custom routes. If the requested
     * route matches, the associated callback function will be executed.
     *
     * @param string $route The route pattern (e.g., 'home', 'about').
     * @param callable $callback The function to call when the route matches.
     */
    public function add($route, $callback)
    {
        $this->routes[$route] = $callback;
    }

    /**
     * Set the callback function for 404 (not found) errors.
     *
     * This method sets a function to be executed when a request is made
     * for a route or view that does not exist.
     *
     * @param callable $callback The function to call for a 404 response.
     */
    public function setNotFound($callback)
    {
        $this->notFoundCallback = $callback;
    }

    /**
     * Dispatch the request and load the appropriate view and metadata.
     *
     * This method determines the requested route, attempts to load the
     * corresponding view and metadata, and automatically includes the
     * header and footer for the page.
     */
    public function dispatch()
{
    // Capture the requested route (e.g., 'home', 'about')
    $request = trim($_SERVER['REQUEST_URI'], '/');

    // If the request is empty, assume it's the homepage
    if ($request === '' || $request === 'index.php') {
        $request = 'home'; // Default to 'home' route
    }

    // Attempt to load the view and its associated metadata
    $this->loadViewWithMetadata($request);
}

    /**
     * Load the view and its metadata if available.
     *
     * This method attempts to load the view from the /public/public_views/ directory,
     * and if a metadata.php file exists in the view directory, it will override
     * the default meta title and description.
     *
     * @param string $request The requested route (e.g., 'home', 'about').
     */
    private function loadViewWithMetadata($request)
    {
        define('REQUESTED_VIEW', $request);
        // Paths to the view file and optional metadata file
        $viewPath = PROJECT_ROOT . '/public/public_views/' . $request . '/index.php';
        $metaDataFile = PROJECT_ROOT . '/public/public_views/' . $request . '/metadata.php';

        // Set default meta tags
        $lang = 'en';
        $metaTitle = 'No configured title';
        $metaDescription = 'No configured description.';

        // If a metadata.php file exists, include it to override defaults
        if (file_exists($metaDataFile)) {
            include $metaDataFile;
        }

        // Automatically include the header, view, and footer
        if (file_exists($viewPath)) {
            require_once  PROJECT_ROOT . '/public/public_views/header.php';
            require_once  $viewPath;
            require_once  PROJECT_ROOT . '/public/public_views/footer.php';
        } else {
            // If no view found, execute the 404 callback
            if ($this->notFoundCallback) {
                call_user_func($this->notFoundCallback);
            } else {
                header('HTTP/1.0 404 Not Found');
                echo "404 Not Found";
            }
        }
    }
}
