<?php

namespace CorianderCore\Core\Router;

/**
 * Renders public view pages.
 */
class ViewRenderer
{
    /**
     * Render a public view page.
     *
     * Looks for an index.php in the specified view directory and includes
     * header and footer templates if found.
     *
     * @param string $viewPath Path relative to public/public_views.
     * @param array  $data     Variables extracted into the view scope.
     * @return bool True if the view was rendered, otherwise false.
     */
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
