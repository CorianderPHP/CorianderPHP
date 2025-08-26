<?php

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
 * 1. Routes and PSR-15 middleware are registered on the singleton instance.
 * 2. A PSR-7 request is passed to {@see Router::dispatch()}.
 * 3. The request travels through the middleware queue and finally reaches
 *    {@see RouteDispatcher} which resolves the target and produces a response.
 */
class Router
{
    private static ?Router $instance = null;
    private RouteRegistry $registry;
    private RouteDispatcher $dispatcher;
    private RequestHandlerInterface $finalHandler;
    /**
     * @var MiddlewareInterface[] Middleware executed before route dispatch.
     */
    private array $middleware = [];

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

        $this->finalHandler = new class($this->dispatcher) implements RequestHandlerInterface {
            public function __construct(private RouteDispatcher $dispatcher) {}

            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                return $this->dispatcher->dispatch($request);
            }
        };
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
