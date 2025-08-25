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

    /**
     * Register a new route and its handler.
     *
     * @param string   $route    Path to register.
     * @param callable $callback Callback executed for the route.
     * @return void
     */
    public function add(string $route, callable $callback): void
    {
        $this->routes[$route] = $callback;
    }

    /**
     * Retrieve the callback for a registered route.
     *
     * @param string $route Path to look up.
     * @return callable|null The matching callback or null if not found.
     */
    public function get(string $route): ?callable
    {
        return $this->routes[$route] ?? null;
    }

    /**
     * Set the callback used when no route matches.
     *
     * @param callable $callback The 404 handler.
     * @return void
     */
    public function setNotFound(callable $callback): void
    {
        $this->notFoundCallback = $callback;
    }

    /**
     * Retrieve the registered not-found callback.
     *
     * @return callable|null The 404 handler or null if none set.
     */
    public function getNotFound(): ?callable
    {
        return $this->notFoundCallback;
    }
}
