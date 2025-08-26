<?php
declare(strict_types=1);

namespace CorianderCore\Core\Router;

use CorianderCore\Core\Router\Handlers\ApiControllerHandler;
use CorianderCore\Core\Router\Handlers\NotFoundHandler;
use CorianderCore\Core\Router\Handlers\WebControllerHandler;
use CorianderCore\Core\Router\Middleware\MiddlewareQueue;
use CorianderCore\Core\Router\RouteDispatcher;
use CorianderCore\Core\Router\RouteRegistry;
use CorianderCore\Core\Router\ViewRenderer;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Entry point for route dispatching.
 *
 * Workflow:
 * 1. Routes, route groups and PSR-15 middleware are registered on the Router
 *    instance (typically retrieved from a service container).
 * 2. A PSR-7 request is passed to {@see Router::dispatch()}.
 * 3. Global middleware run first, followed by any route-specific middleware.
 *    The request finally reaches {@see RouteDispatcher} which resolves the
 *    target and produces a response.
 */
class Router
{
    private RouteRegistry $registry;
    private RouteDispatcher $dispatcher;
    private RequestHandlerInterface $finalHandler;
    /**
     * @var MiddlewareInterface[] Middleware executed before route dispatch.
     */
    private array $middleware = [];

    /**
     * @var array<int, array{prefix:string,middleware:MiddlewareInterface[]}>
     *      Stack of active route groups.
     */
    private array $groupStack = [];

    public function __construct()
    {
        $this->registry = new RouteRegistry();
        $this->dispatcher = new RouteDispatcher(
            $this->registry,
            new WebControllerHandler(),
            new ApiControllerHandler(),
            new ViewRenderer(),
            new NotFoundHandler()
        );

        $this->finalHandler = new class($this->dispatcher) implements RequestHandlerInterface {
            public function __construct(private RouteDispatcher $dispatcher) {}

            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                return $this->dispatcher->dispatch($request);
            }
        };
    }

    /**
     * Register a custom route handler.
     *
     * @param string                $method     HTTP method for the route.
     * @param string                $pattern    URI pattern, e.g. '/user/{id}'.
     * @param callable              $callback   Callback executed when matched.
     * @param MiddlewareInterface[] $middleware Route-specific middleware.
     * @return void
     */
    public function add(string $method, string $pattern, callable $callback, array $middleware = []): void
    {
        $pattern = trim($pattern, '/');

        $prefix = trim($this->getGroupPrefix(), '/');
        if ($prefix !== '') {
            $pattern = trim($prefix . '/' . $pattern, '/');
        }

        $paramNames = [];
        if (preg_match_all('#\{([^}]+)\}#', $pattern, $matches)) {
            $paramNames = $matches[1];
        }

        $regex = preg_quote($pattern, '#');
        $regex = preg_replace('#\\\{([^/]+)\\\}#', '([^/]+)', $regex);
        $regex = '#^' . $regex . '$#';

        $middleware = array_merge($this->getGroupMiddleware(), $middleware);

        $this->registry->add(strtoupper($method), $regex, $paramNames, $callback, $middleware);
    }

    /**
     * Group routes under a common prefix and middleware set.
     *
     * @param string                $prefix     Path prefix for the group.
     * @param MiddlewareInterface[] $middleware Middleware applied to routes.
     * @param callable              $callback   Callback that registers routes.
     * @return void
     */
    public function group(string $prefix, array $middleware, callable $callback): void
    {
        $this->groupStack[] = [
            'prefix' => trim($prefix, '/'),
            'middleware' => $middleware,
        ];

        $callback($this);

        array_pop($this->groupStack);
    }

    /**
     * Retrieve the concatenated prefix from active groups.
     */
    private function getGroupPrefix(): string
    {
        $segments = [];
        foreach ($this->groupStack as $group) {
            if ($group['prefix'] !== '') {
                $segments[] = $group['prefix'];
            }
        }
        return implode('/', $segments);
    }

    /**
     * Retrieve combined middleware from active groups.
     *
     * @return MiddlewareInterface[]
     */
    private function getGroupMiddleware(): array
    {
        $middleware = [];
        foreach ($this->groupStack as $group) {
            $middleware = [...$middleware, ...$group['middleware']];
        }
        return $middleware;
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
     * Add a PSR-15 middleware to the execution queue.
     *
     * @param MiddlewareInterface $middleware Middleware instance to execute.
     * @return void
     */
    public function addMiddleware(MiddlewareInterface $middleware): void
    {
        $this->middleware[] = $middleware;
    }

    /**
     * Dispatch an incoming PSR-7 request and return a response.
     *
     * @param ServerRequestInterface $request Incoming request to handle.
     * @return ResponseInterface Response produced by the route dispatcher.
     */
    public function dispatch(ServerRequestInterface $request): ResponseInterface
    {
        $pipeline = new MiddlewareQueue($this->middleware, $this->finalHandler);
        return $pipeline->handle($request);
    }
    
}
