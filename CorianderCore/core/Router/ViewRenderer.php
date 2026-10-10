<?php
declare(strict_types=1);

namespace CorianderCore\Core\Router;

/**
 * Renders views with escaped string data and independently inherited layouts.
 */
class ViewRenderer
{
    public function __construct(private string $directory = PROJECT_ROOT . '/src/Views') {}

    /**
     * Use the nearest existing header and footer independently; omit absent parts.
     *
     * @param array<string,mixed> $data Variables exposed to all rendered files; strings are HTML-escaped recursively.
     * @throws \InvalidArgumentException For reserved or invalid view names.
     * @throws \RuntimeException For missing views or files outside the view root.
     */
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
            $header = null;
            $footer = null;
            $ancestor = dirname($name);
            while (true) {
                $prefix = $ancestor === '.' ? '' : $ancestor . '/';
                if ($header === null && file_exists($this->directory . '/' . $prefix . '_header.php')) {
                    $header = SafePath::resolveFile($this->directory, $prefix . '_header.php');
                }
                if ($footer === null && file_exists($this->directory . '/' . $prefix . '_footer.php')) {
                    $footer = SafePath::resolveFile($this->directory, $prefix . '_footer.php');
                }
                if (($header !== null && $footer !== null) || $ancestor === '.') {
                    break;
                }
                $ancestor = dirname($ancestor);
            }
            $files = array_filter([$header, $file, $footer], static fn(?string $path): bool => $path !== null);
        }
        $render = static function (array $__files, array $__data): void {
            // Application data must not replace the renderer's file list.
            extract($__data, EXTR_SKIP);
            foreach ($__files as $__file) {
                require $__file;
            }
        };
        [, $html] = \CorianderCore\Core\Support\OutputBuffer::capture(fn() => $render($files, $this->escapeData($data)));
        return \CorianderCore\Core\Http\Responses::html($html, $status);
    }

    /**
     * @param array<string,mixed> $data
     * @return array<string,mixed>
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
