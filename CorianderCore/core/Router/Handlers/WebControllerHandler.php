<?php
declare(strict_types=1);

namespace CorianderCore\Core\Router\Handlers;

use CorianderCore\Core\Router\NameFormatter;
use CorianderCore\Core\Router\Services\ControllerCacheService;
use Nyholm\Psr7\Response;
use Psr\Http\Message\ResponseInterface;
use ReflectionMethod;
use ReflectionException;

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
     * Service used to resolve controller class paths from a cached PHP file.
     */
    private ControllerCacheService $cacheService;


    /**
     * @var callable(string):object
     */
    private $controllerFactory;
    /**
     * Cache for existence checks to avoid redundant filesystem lookups.
     *
     * @var array<string, bool>
     */
    private array $controllerExistenceCache = [];

    public function __construct(?ControllerCacheService $cacheService = null, ?callable $controllerFactory = null)
    {
        $this->cacheService = $cacheService ?? new ControllerCacheService();
        $this->controllerFactory = $controllerFactory ?? static fn(string $className): object => new $className();
    }

    /**
     * Handle the request and attempt to dispatch to a web controller.
     *
     * @param string $path The request URI path (e.g., 'user/edit/1').
     * @param string $method The HTTP method used (e.g., GET, POST).
     * @return bool True if the request was successfully dispatched, false otherwise.
     */
    public function handle(string $path, string $method): bool
    {
        return $this->dispatch($path, $method) !== null;
    }

    /**
     * Dispatch a web controller action and return a response when handled.
     */
    public function dispatch(string $path, string $method): ?ResponseInterface
    {
        $segments = explode('/', $path);
        $controllerClass = $this->resolveControllerClass($segments[0] ?? '');

        if (!$this->controllerExists($controllerClass)) {
            return null;
        }

        $factory = $this->controllerFactory;
        $controller = $factory($controllerClass);

        ob_start();
        [$handled, $result] = $this->dispatchAction($controller, $segments, $method);
        $content = (string) ob_get_clean();

        if (!$handled) {
            return null;
        }

        if ($result instanceof ResponseInterface) {
            return $result;
        }

        if (is_string($result) || $result instanceof \Stringable) {
            $content .= (string) $result;
        }

        return new Response(200, [], $content);
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
     * @return array{0:bool,1:mixed} [handled, action result]
     */
    private function dispatchAction(object $controller, array $segments, string $method): array
    {
        $action = $segments[1] ?? 'index';
        if (!$this->isValidActionName($action)) {
            return [false, null];
        }

        $params = array_slice($segments, 2);

        if ($action !== 'index' && $this->isPublicInvokableAction($controller, $action)) {
            return [true, call_user_func_array([$controller, $action], $params)];
        }

        if ($method === 'POST' && $this->isPublicInvokableAction($controller, 'store')) {
            return [true, call_user_func_array([$controller, 'store'], $params)];
        }

        if ($this->isPublicInvokableAction($controller, $action)) {
            return [true, call_user_func_array([$controller, $action], $params)];
        }

        return [false, null];
    }

    private function isValidActionName(string $action): bool
    {
        if ($action === '' || str_starts_with($action, '_')) {
            return false;
        }

        return preg_match('/^[a-zA-Z][a-zA-Z0-9_]*$/', $action) === 1;
    }

    private function isPublicInvokableAction(object $controller, string $action): bool
    {
        if (!method_exists($controller, $action)) {
            return false;
        }

        try {
            $method = new ReflectionMethod($controller, $action);
        } catch (ReflectionException) {
            return false;
        }

        return $method->isPublic() && !$method->isStatic();
    }

    /**
     * Checks whether a given controller class exists and includes its file if needed.
     *
     * @param string $controllerClass The fully qualified controller class name.
     * @return bool True if the controller class exists, false otherwise.
     */
    private function controllerExists(string $controllerClass): bool
    {
        if (isset($this->controllerExistenceCache[$controllerClass])) {
            return $this->controllerExistenceCache[$controllerClass];
        }

        $cachedFile = $this->cacheService->get($controllerClass);
        if ($cachedFile !== null) {
            if (!class_exists($controllerClass) && file_exists($cachedFile)) {
                require_once $cachedFile;
            }
            return $this->controllerExistenceCache[$controllerClass] = class_exists($controllerClass);
        }

        if (class_exists($controllerClass)) {
            return $this->controllerExistenceCache[$controllerClass] = true;
        }

        $controllerFile = $this->resolveControllerFile($controllerClass);
        if (file_exists($controllerFile)) {
            require_once $controllerFile;
            return $this->controllerExistenceCache[$controllerClass] = class_exists($controllerClass);
        }

        return $this->controllerExistenceCache[$controllerClass] = false;
    }
}

