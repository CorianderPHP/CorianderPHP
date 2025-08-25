<?php

namespace CorianderCore\Core\Router;

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
     * @param string $uri    The incoming request URI.
     * @param string $method The HTTP method (GET, POST, etc.).
     * @return void
     */
    public function dispatch(string $uri, string $method): void
    {
        $path = trim(parse_url($uri, PHP_URL_PATH), '/');
        if ($path === '') {
            $path = 'home';
        }

        defined('REQUESTED_VIEW') || define('REQUESTED_VIEW', $path);

        $method = strtoupper($method);

        foreach ($this->registry->getRoutes() as [$routeMethod, $pattern, $callback]) {
            if ($routeMethod === $method && preg_match($pattern, $path, $matches)) {
                array_shift($matches);
                call_user_func_array($callback, $matches);
                return;
            }
        }

        if (str_starts_with($path, 'api/')) {
            if (!$this->apiHandler->handle($path, $method)) {
                $this->notFoundHandler->handle($this->registry);
            }
            return;
        }

        if (!$this->webHandler->handle($path, $method)) {
            $this->viewRenderer->render($path) ?: $this->notFoundHandler->handle($this->registry);
        }
    }
}
