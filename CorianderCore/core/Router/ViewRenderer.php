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
    public function __construct(private string $directory = PROJECT_ROOT . '/src/Views') {}

    public function response(string $view, array $data = [], int $status = 200, bool $layout = true): \Psr\Http\Message\ResponseInterface
    {
        $name = SafePath::normalizeRelativePath($view);
        if ($name === null || in_array(basename($name), ['_header', '_footer', '_header.php', '_footer.php'], true)) {
            throw new \InvalidArgumentException('Invalid view name');
        }
        $name = str_ends_with($name, '.php') ? $name : $name . '.php';
        $file = SafePath::resolveFile($this->directory, $name);
        $files = [$file];
        if ($layout) {
            $ancestor = dirname($name);
            while (true) {
                $prefix = $ancestor === '.' ? '' : $ancestor . '/';
                $header = $prefix . '_header.php';
                $footer = $prefix . '_footer.php';
                $hasHeader = file_exists($this->directory . '/' . $header);
                $hasFooter = file_exists($this->directory . '/' . $footer);
                if ($hasHeader !== $hasFooter) {
                    throw new \RuntimeException('Incomplete view layout in: ' . $ancestor);
                }
                if ($hasHeader) {
                    $files = [SafePath::resolveFile($this->directory, $header), $file, SafePath::resolveFile($this->directory, $footer)];
                    break;
                }
                if ($ancestor === '.') {
                    break;
                }
                $ancestor = dirname($ancestor);
            }
        }
        $render = static function (array $__files, array $__data): void {
            extract($__data, EXTR_SKIP);
            foreach ($__files as $__file) {
                require $__file;
            }
        };
        [, $html] = \CorianderCore\Core\Support\OutputBuffer::capture(fn() => $render($files, $this->escapeData($data)));
        return \CorianderCore\Core\Http\Responses::html($html, $status);
    }

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
        $normalizedPath = SafePath::normalizeRelativePath($viewPath);
        if ($normalizedPath === null) {
            return false;
        }

        $escapedData = $this->escapeData($data);
        extract($escapedData, EXTR_OVERWRITE);

        $viewsRoot = PROJECT_ROOT . '/public/public_views';
        $fullViewPath = $viewsRoot . '/' . $normalizedPath . '/index.php';

        if (!file_exists($fullViewPath)) {
            return false;
        }

        $realViewsRoot = realpath($viewsRoot);
        $realViewPath = realpath($fullViewPath);
        if ($realViewsRoot === false || $realViewPath === false) {
            return false;
        }

        $prefix = rtrim($realViewsRoot, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
        if (!str_starts_with($realViewPath, $prefix)) {
            return false;
        }

        $__corianderRequestedView = $normalizedPath;

        require $viewsRoot . '/header.php';
        require $fullViewPath;
        require $viewsRoot . '/footer.php';

        return true;
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
