<?php

namespace CorianderCore\Core\Router\Handlers;

use CorianderCore\Core\Router\RouteRegistry;
use Nyholm\Psr7\Response;
use Psr\Http\Message\ResponseInterface;

/**
 * Handles 404 not found logic.
 */
class NotFoundHandler
{
    /**
     * Execute the not-found callback or return a default 404 response.
     *
     * @param RouteRegistry $registry Registry containing an optional 404 handler.
     * @return ResponseInterface 404 response.
     */
    public function handle(RouteRegistry $registry): ResponseInterface
    {
        $callback = $registry->getNotFound();
        if ($callback) {
            ob_start();
            $result = call_user_func($callback);
            $content = ob_get_clean();
            if ($result instanceof ResponseInterface) {
                return $result;
            }
            return new Response(404, [], $content);
        }

        return new Response(404, [], '404 Not Found');
    }
}
