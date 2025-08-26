<?php
declare(strict_types=1);

/*
 * RouteRegistry stores application routes and the fallback handler for
 * unmatched requests, acting as a lightweight router configuration store.
 */

namespace CorianderCore\Core\Router;

use Closure;

/**
 * Manages custom routes and 404 callback.
 */
class RouteRegistry
{
    /**
     * @var array<int, array{0:string,1:string,2:callable}>
     */
    private array $routes = [];

    /**
     * @var Closure|null
     */
    private ?Closure $notFoundCallback = null;

    /**
     * Register a new route and its handler.
     *
     * @param string   $method  HTTP method for the route.
     * @param string   $pattern Regex pattern for the route.
     * @param callable $callback Callback executed for the route.
     * @return void
     */
    public function add(string $method, string $pattern, callable $callback): void
    {
        $this->routes[] = [$method, $pattern, $callback];
    }

    /**
     * Retrieve all registered routes.
     *
     * @return array<int, array{0:string,1:string,2:callable}>
     */
    public function getRoutes(): array
    {
        return $this->routes;
    }

    /**
     * Set the callback used when no route matches.
     *
     * @param callable $callback The 404 handler.
     * @return void
     */
    public function setNotFound(callable $callback): void
    {
        $this->notFoundCallback = $callback instanceof Closure ? $callback : Closure::fromCallable($callback);
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
