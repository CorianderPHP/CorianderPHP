<?php
declare(strict_types=1);

namespace CorianderCore\Core\Router\Handlers;

use CorianderCore\Core\Router\NameFormatter;
use Nyholm\Psr7\Response;
use Psr\Http\Message\ResponseInterface;
use ReflectionException;
use ReflectionMethod;

/**
 * Handles dispatching to API controllers based on RESTful methods and subpaths.
 */
class ApiControllerHandler
{
    /**
     * @var callable(string):object
     */
    private $controllerFactory;

    public function __construct(?callable $controllerFactory = null)
    {
        $this->controllerFactory = $controllerFactory ?? static fn(string $className): object => new $className();
    }

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
        return $this->dispatch($path, $method) !== null;
    }

    /**
     * Dispatch an API request and return a response when handled.
     */
    public function dispatch(string $path, string $method): ?ResponseInterface
    {
        $segments = explode('/', $path);
        array_shift($segments); // Remove 'api'

        $controllerName = NameFormatter::toPascalCase($segments[0] ?? '');
        $controllerClass = 'ApiControllers\\' . $controllerName . 'Controller';

        if (!$this->controllerExists($controllerClass)) {
            return null;
        }

        $factory = $this->controllerFactory;
        $controller = $factory($controllerClass);

        $action = strtolower($method);
        if (isset($segments[1]) && $segments[1] !== '') {
            $subAction = strtolower(str_replace('-', '_', $segments[1]));
            $action .= '_' . $subAction;
        }

        $params = array_slice($segments, 2);

        if (!$this->isPublicInvokableAction($controller, $action)) {
            return null;
        }

        ob_start();
        $result = call_user_func_array([$controller, $action], $params);
        $content = (string) ob_get_clean();

        if ($result instanceof ResponseInterface) {
            return $result;
        }

        if (is_array($result)) {
            $encoded = json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            if (!is_string($encoded)) {
                $encoded = '{}';
            }
            return new Response(200, ['Content-Type' => 'application/json; charset=utf-8'], $encoded);
        }

        if (is_string($result) || $result instanceof \Stringable) {
            $content .= (string) $result;
        }

        if ($content === '') {
            return new Response(204, ['Content-Type' => 'application/json; charset=utf-8']);
        }

        $trimmedContent = trim($content);
        if ($this->isJsonPayload($trimmedContent)) {
            return new Response(200, ['Content-Type' => 'application/json; charset=utf-8'], $trimmedContent);
        }

        $encoded = json_encode(['data' => $content], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if (!is_string($encoded)) {
            $encoded = '{"data":""}';
        }

        return new Response(200, ['Content-Type' => 'application/json; charset=utf-8'], $encoded);
    }


    private function isJsonPayload(string $payload): bool
    {
        if ($payload === '') {
            return false;
        }

        json_decode($payload, true);
        return json_last_error() === JSON_ERROR_NONE;
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

    private function controllerExists(string $controllerClass): bool
    {
        if (class_exists($controllerClass)) {
            return true;
        }

        $controllerFile = $this->resolveControllerFile($controllerClass);
        if (file_exists($controllerFile)) {
            require_once $controllerFile;
        }

        return class_exists($controllerClass);
    }

    private function resolveControllerFile(string $controllerClass): string
    {
        $shortName = substr(strrchr($controllerClass, '\\'), 1);
        return PROJECT_ROOT . '/src/ApiControllers/' . $shortName . '.php';
    }
}
