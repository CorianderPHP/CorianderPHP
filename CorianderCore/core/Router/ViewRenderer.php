<?php
declare(strict_types=1);

namespace CorianderCore\Core\Router;

use CorianderCore\Core\Http\Responses;
use CorianderCore\Core\Support\OutputBuffer;
use Psr\Http\Message\{ResponseInterface, ServerRequestInterface};

/**
 * Renders views with escaped string data and independently inherited layouts.
 */
class ViewRenderer
{
    public function __construct(private string $directory = PROJECT_ROOT . '/src/Views') {}

    /** Execute a method file once; only GET files may render HTML directly. */
    public function dispatch(string $name, ServerRequestInterface $request): ResponseInterface
    {
        $file = SafePath::resolveFile($this->directory, $name);
        [$result, $html] = OutputBuffer::capture(static function () use ($file, $request) {
            return require $file;
        });
        if (is_callable($result)) {
            if ($html !== '') {
                throw new \UnexpectedValueException('Method files returning handlers must not print output: ' . $name);
            }
            $response = $result($request);
            if (!$response instanceof ResponseInterface) {
                throw new \UnexpectedValueException('Handler must return a PSR response: ' . $name);
            }
            return $response;
        }
        if (!str_ends_with($name, '.get.php') || $result !== 1) {
            throw new \UnexpectedValueException('Expected a callable handler or a direct GET template: ' . $name);
        }
        [$header, $footer] = $this->layoutFiles($name);
        return Responses::html($this->renderFiles(array_filter([$header]), [], $request) . $html
            . $this->renderFiles(array_filter([$footer]), [], $request));
    }

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
        if (in_array(pathinfo(substr($name, 0, -4), PATHINFO_EXTENSION), RouteMap::METHODS, true)) {
            throw new \InvalidArgumentException('Method files must be dispatched by the router');
        }
        $file = SafePath::resolveFile($this->directory, $name);
        $files = [$file];
        if ($layout) {
            [$header, $footer] = $this->layoutFiles($name);
            $files = array_filter([$header, $file, $footer], static fn(?string $path): bool => $path !== null);
        }
        return Responses::html($this->renderFiles($files, $this->escapeData($data)), $status);
    }

    /** @return array{?string,?string} Nearest header and footer, independently. */
    private function layoutFiles(string $name): array
    {
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
        return [$header, $footer];
    }

    /**
     * @param array<int,string> $files
     * @param array<string,mixed> $data
     */
    private function renderFiles(array $files, array $data, ?ServerRequestInterface $request = null): string
    {
        if ($files === []) {
            return '';
        }
        $render = static function (array $__files, array $__data, ?ServerRequestInterface $__request): void {
            if ($__request !== null) {
                $request = $__request;
            }
            // Application data must not replace the renderer's file list.
            extract($__data, EXTR_SKIP);
            foreach ($__files as $__file) {
                require $__file;
            }
        };
        [, $html] = OutputBuffer::capture(fn() => $render($files, $data, $request));
        return $html;
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
