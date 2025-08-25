<?php

namespace CorianderCore\Core\Router;

/**
 * Handles 404 not found logic.
 */
class NotFoundHandler
{
    /**
     * Execute the not-found callback or output a default 404 response.
     *
     * @param RouteRegistry $registry Registry containing an optional 404 handler.
     * @return void
     */
    public function handle(RouteRegistry $registry): void
    {
        $callback = $registry->getNotFound();
        if ($callback) {
            call_user_func($callback);
        } else {
            header('HTTP/1.0 404 Not Found');
            echo "404 Not Found";
        }
    }
}
