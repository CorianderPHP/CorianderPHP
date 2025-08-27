<?php
declare(strict_types=1);

namespace CorianderCore\Core\Router;

/**
 * ViewRenderer
 *
 * Handles locating and rendering public views with sanitized data.
 * Workflow:
 * 1. Escape string data for safe HTML output.
 * 2. Extract the sanitized variables into the view scope.
 * 3. Include shared header and footer around the view.
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
        $escapedData = $this->escapeData($data);
        extract($escapedData, EXTR_OVERWRITE);

        $fullViewPath = PROJECT_ROOT . '/public/public_views/' . $viewPath . '/index.php';

        if (file_exists($fullViewPath)) {
            require_once PROJECT_ROOT . '/public/public_views/header.php';
            require_once $fullViewPath;
            require_once PROJECT_ROOT . '/public/public_views/footer.php';
            return true;
        }

        return false;
    }

    /**
     * Escape data recursively for safe HTML output.
     *
     * @param array $data Data to escape.
     * @return array Escaped data.
     */
    private function escapeData(array $data): array
    {
        array_walk_recursive($data, function (&$value): void {
            if (is_string($value)) {
                $value = htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            }
        });

        return $data;
    }
}
