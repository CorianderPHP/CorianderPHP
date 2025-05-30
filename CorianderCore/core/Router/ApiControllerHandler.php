<?php

namespace CorianderCore\Router;

/**
 * Handles dispatching to API controllers based on RESTful methods and subpaths.
 */
class ApiControllerHandler
{
    public function handle(string $path, string $method): bool
    {
        $segments = explode('/', $path);
        array_shift($segments); // Remove 'api'

        $controllerName = $this->formatControllerName($segments[0] ?? '');
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

    private function formatControllerName(string $name): string
    {
        return str_replace(' ', '', ucwords(str_replace(['-', '_'], ' ', $name)));
    }
}
