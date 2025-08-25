<?php

namespace CorianderCore\Core\Router;

/**
 * Manages custom routes and 404 callback.
 */
class RouteRegistry
{
    /**
     * @var array<string, callable>
     */
    private array $routes = [];

    /**
     * @var callable|null
     */
    private $notFoundCallback = null;

    public function add(string $route, callable $callback): void
    {
        $this->routes[$route] = $callback;
    }

    public function get(string $route): ?callable
    {
        return $this->routes[$route] ?? null;
    }

    public function setNotFound(callable $callback): void
    {
        $this->notFoundCallback = $callback;
    }

    public function getNotFound(): ?callable
    {
        return $this->notFoundCallback;
    }
}
