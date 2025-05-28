<?php

namespace CorianderCore\Router;

/**
 * Handles 404 not found logic.
 */
class NotFoundHandler
{
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
