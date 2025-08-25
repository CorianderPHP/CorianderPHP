<?php

namespace CorianderCore\Core\Router;

use CorianderCore\Core\Router\NameFormatter;

/**
 * Handles dispatching to web controllers by resolving controller class and action from a URL path.
 *
 * This class is responsible for:
 * - Parsing the request path to identify the controller and action.
 * - Loading the appropriate controller file if needed.
 * - Invoking the controller action method based on HTTP method and URI segments.
 *
 * @package CorianderCore\Core\Router
 */
class WebControllerHandler
{
    /**
     * Cache for class existence checks to avoid redundant filesystem and reflection calls.
     *
     * @var array<string, bool>
     */
    private array $controllerExistenceCache = [];

    /**
     * Handle the request and attempt to dispatch to a web controller.
     *
     * @param string $path The request URI path (e.g., 'user/edit/1').
     * @param string $method The HTTP method used (e.g., GET, POST).
     * @return bool True if the request was successfully dispatched, false otherwise.
     */
    public function handle(string $path, string $method): bool
    {
        $segments = explode('/', $path);
        $controllerClass = $this->resolveControllerClass($segments[0] ?? '');
        $controllerFile = $this->resolveControllerFile($controllerClass);

        if (!$this->controllerExists($controllerClass, $controllerFile)) {
            return false;
        }

        $controller = new $controllerClass();

        return $this->dispatchAction($controller, $segments, $method);
    }

    /**
     * Resolves the full controller class name based on the first URI segment.
     *
     * @param string $segment The controller segment from the URI.
     * @return string Fully qualified controller class name.
     */
    private function resolveControllerClass(string $segment): string
    {
        $name = NameFormatter::toPascalCase($segment);
        $class = str_ends_with($name, 'Controller') ? $name : $name . 'Controller';
        return 'Controllers\\' . $class;
    }

    /**
     * Resolves the controller file path from its class name.
     *
     * @param string $controllerClass The fully qualified controller class name.
     * @return string The full file path to the controller.
     */
    private function resolveControllerFile(string $controllerClass): string
    {
        $shortName = substr(strrchr($controllerClass, '\\'), 1);
        return PROJECT_ROOT . '/src/Controllers/' . $shortName . '.php';
    }

    /**
     * Dispatches the action method on the given controller.
     *
     * @param object $controller The controller instance.
     * @param array<int, string> $segments The URI path segments.
     * @param string $method The HTTP method (GET, POST, etc.).
     * @return bool True if the action was successfully dispatched.
     */
    private function dispatchAction(object $controller, array $segments, string $method): bool
    {
        $action = $segments[1] ?? 'index';
        $params = array_slice($segments, 2);

        if ($method === 'POST' && method_exists($controller, 'store')) {
            call_user_func_array([$controller, 'store'], $params);
            return true;
        }

        if (method_exists($controller, $action)) {
            call_user_func_array([$controller, $action], $params);
            return true;
        }

        return false;
    }

    /**
     * Checks and caches whether a given controller class exists and includes its file if needed.
     *
     * @param string $controllerClass The fully qualified controller class name.
     * @param string $controllerFile The file path of the controller.
     * @return bool True if the controller class exists, false otherwise.
     */
    private function controllerExists(string $controllerClass, string $controllerFile): bool
    {
        if (isset($this->controllerExistenceCache[$controllerClass])) {
            return $this->controllerExistenceCache[$controllerClass];
        }

        if (class_exists($controllerClass)) {
            return $this->controllerExistenceCache[$controllerClass] = true;
        }

        if (file_exists($controllerFile)) {
            require_once $controllerFile;
            return $this->controllerExistenceCache[$controllerClass] = class_exists($controllerClass);
        }

        return $this->controllerExistenceCache[$controllerClass] = false;
    }
}
