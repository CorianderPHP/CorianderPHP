<?php

namespace CorianderCore\Core\Router;

/**
 * Renders public view pages.
 */
class ViewRenderer
{
    public function render(string $viewPath, array $data = []): bool
    {
        extract($data);

        $fullViewPath = PROJECT_ROOT . '/public/public_views/' . $viewPath . '/index.php';

        if (file_exists($fullViewPath)) {
            require_once PROJECT_ROOT . '/public/public_views/header.php';
            require_once $fullViewPath;
            require_once PROJECT_ROOT . '/public/public_views/footer.php';
            return true;
        }

        return false;
    }
}
