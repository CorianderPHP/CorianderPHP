<?php
declare(strict_types=1);

namespace CorianderCore\Core\Router;

use CorianderCore\Core\Router\Handlers\ApiControllerHandler;
use CorianderCore\Core\Router\Handlers\NotFoundHandler;
use CorianderCore\Core\Router\Handlers\WebControllerHandler;
use CorianderCore\Core\Router\ViewRenderer;
use CorianderCore\Core\Router\Middleware\MiddlewareQueue;
use Nyholm\Psr7\Response;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Determines whether to dispatch a Web, API, or custom route.
 */
class RouteDispatcher
{
    public function __construct(
        private RouteRegistry $registry,
        private WebControllerHandler $webHandler,
        private ApiControllerHandler $apiHandler,
        private ViewRenderer $viewRenderer,
        private NotFoundHandler $notFoundHandler
    ) {}

    /**
     * Dispatch a request to the appropriate handler.
     *
     * Resolves custom routes first, then API controllers, and finally
     * web controllers or view rendering.
     *
     * @param ServerRequestInterface $request The incoming request.
     * @return ResponseInterface Generated response.
     */
    public function dispatch(ServerRequestInterface $request): ResponseInterface
    {
        $path = trim($request->getUri()->getPath(), '/');
        if ($path === '') {
            $path = 'home';
        }

        defined('REQUESTED_VIEW') || define('REQUESTED_VIEW', $path);

        $method = strtoupper($request->getMethod());

        foreach ($this->registry->getRoutes() as [$routeMethod, $pattern, $params, $callback, $middleware]) {
            if ($routeMethod === $method && preg_match($pattern, $path, $matches)) {
                array_shift($matches);
                $requestWithAttributes = $request;
                foreach ($params as $i => $name) {
                    $requestWithAttributes = $requestWithAttributes->withAttribute($name, $matches[$i] ?? null);
                }

                $handler = new class($callback) implements RequestHandlerInterface {
                    public function __construct(private $callback) {}

                    public function handle(ServerRequestInterface $request): ResponseInterface
                    {
                        ob_start();
                        $result = call_user_func($this->callback, $request);
                        $content = ob_get_clean();
                        if ($result instanceof ResponseInterface) {
                            return $result;
                        }
                        return new Response(200, [], $content);
                    }
                };

                $queue = new MiddlewareQueue($middleware, $handler);
                return $queue->handle($requestWithAttributes);
            }
        }

        ob_start();
        if (str_starts_with($path, 'api/')) {
            if (!$this->apiHandler->handle($path, $method)) {
                ob_end_clean();
                return $this->notFoundHandler->handle($this->registry);
            }
            $content = ob_get_clean();
            return new Response(200, [], $content);
        }

        if ($this->webHandler->handle($path, $method)) {
            $content = ob_get_clean();
            return new Response(200, [], $content);
        }

        if ($this->viewRenderer->render($path)) {
            $content = ob_get_clean();
            return new Response(200, [], $content);
        }

        ob_end_clean();
        return $this->notFoundHandler->handle($this->registry);
    }
}
