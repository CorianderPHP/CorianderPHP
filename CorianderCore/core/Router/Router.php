<?php

namespace CorianderCore\Core\Router;

use CorianderCore\Core\Router\RouteDispatcher;
use CorianderCore\Core\Router\RouteRegistry;
use CorianderCore\Core\Router\NotFoundHandler;

/**
 * Entry point for route dispatching.
 */
class Router
{
    private static ?Router $instance = null;
    private RouteRegistry $registry;
    private RouteDispatcher $dispatcher;

    private function __construct()
    {
        $this->registry = new RouteRegistry();
        $this->dispatcher = new RouteDispatcher(
            $this->registry,
            new WebControllerHandler(),
            new ApiControllerHandler(),
            new ViewRenderer(),
            new NotFoundHandler()
        );
    }

    /**
     * Retrieve the singleton Router instance.
     *
     * @return self The global Router instance.
     */
    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Register a custom route handler.
     *
     * @param string   $method  HTTP method for the route.
     * @param string   $pattern URI pattern, e.g. '/user/{id}'.
     * @param callable $callback Callback executed when the route is matched.
     * @return void
     */
    public function add(string $method, string $pattern, callable $callback): void
    {
        $pattern = trim($pattern, '/');
        $regex = preg_quote($pattern, '#');
        $regex = preg_replace('#\\\{([^/]+)\\\}#', '([^/]+)', $regex);
        $regex = '#^' . $regex . '$#';

        $this->registry->add(strtoupper($method), $regex, $callback);
    }

    /**
     * Define a callback to execute when no route matches.
     *
     * @param callable $callback Fallback handler for unmatched routes.
     * @return void
     */
    public function setNotFound(callable $callback): void
    {
        $this->registry->setNotFound($callback);
    }

    /**
     * Dispatch the current HTTP request to the appropriate handler.
     *
     * Uses the global request URI and method to determine the target.
     *
     * @return void
     */
    public function dispatch(): void
    {
        $this->dispatcher->dispatch($_SERVER['REQUEST_URI'], $_SERVER['REQUEST_METHOD']);
    }
    
}
