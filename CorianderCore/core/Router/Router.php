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

    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function add(string $route, callable $callback): void
    {
        $this->registry->add($route, $callback);
    }

    public function setNotFound(callable $callback): void
    {
        $this->registry->setNotFound($callback);
    }

    public function dispatch(): void
    {
        $this->dispatcher->dispatch($_SERVER['REQUEST_URI'], $_SERVER['REQUEST_METHOD']);
    }
    
    /**
     * Convert a raw route name to PascalCase controller name.
     *
     * @param string $name The raw controller segment (e.g., 'user-profile').
     * @return string PascalCase format (e.g., 'UserProfile').
     */
    public function formatControllerName(string $name): string
    {
        return str_replace(' ', '', ucwords(str_replace(['-', '_'], ' ', $name)));
    }
}
