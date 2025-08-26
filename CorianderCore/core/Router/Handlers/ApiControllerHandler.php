<?php
declare(strict_types=1);

namespace CorianderCore\Core\Router\Handlers;

use CorianderCore\Core\Router\NameFormatter;

/**
 * Handles dispatching to API controllers based on RESTful methods and subpaths.
 */
class ApiControllerHandler
{
    /**
     * Dispatch an API request to the appropriate controller action.
     *
     * Parses the path to determine the controller and action method based on
     * REST conventions and the HTTP verb.
     *
     * @param string $path   The request path including the leading 'api/'.
     * @param string $method The HTTP method (GET, POST, etc.).
     * @return bool True if the request was handled, otherwise false.
     */
    public function handle(string $path, string $method): bool
    {
        $segments = explode('/', $path);
        array_shift($segments); // Remove 'api'

        $controllerName = NameFormatter::toPascalCase($segments[0] ?? '');
        $controllerClass = 'ApiControllers\\' . $controllerName . 'Controller';

        if (!class_exists($controllerClass)) {
            return false;
        }

        $controller = new $controllerClass();

        // Build action method name
        $action = strtolower($method);
        if (isset($segments[1]) && $segments[1] !== '') {
            $subAction = strtolower(str_replace('-', '_', $segments[1]));
            $action .= '_' . $subAction;
        }

        // Remaining segments are parameters
        $params = array_slice($segments, 2);

        if (!method_exists($controller, $action)) {
            return false;
        }

        call_user_func_array([$controller, $action], $params);
        return true;
    }
}
