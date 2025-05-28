<?php

namespace CorianderCore\Router;

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

    public function dispatch(string $uri, string $method): void
    {
        $path = trim(parse_url($uri, PHP_URL_PATH), '/');
        if ($path === '') {
            $path = 'home';
        }

        defined('REQUESTED_VIEW') || define('REQUESTED_VIEW', $path);

        if ($callback = $this->registry->get($path)) {
            call_user_func($callback);
            return;
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
