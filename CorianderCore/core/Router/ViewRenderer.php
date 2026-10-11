<?php
declare(strict_types=1);

namespace CorianderCore\Core\Router;

use CorianderCore\Core\Http\Responses;
use CorianderCore\Core\Support\OutputBuffer;
use CorianderCore\Core\Support\PublicUrl;
use Psr\Http\Message\{ResponseInterface, ServerRequestInterface};

/**
 * Renders views with escaped string data and independently inherited layouts.
 */
class ViewRenderer
{
    private ?string $scriptDirectory;

    /** Custom view roots may supply their matching compiled pages directory. */
    public function __construct(private string $directory = PROJECT_ROOT . '/src/Views', ?string $scriptDirectory = null)
    {
        $canonical = realpath(PROJECT_ROOT . '/src/Views');
        $this->scriptDirectory = $scriptDirectory ?? ($canonical !== false && realpath($directory) === $canonical
            ? PROJECT_ROOT . '/public/assets/js/pages' : null);
    }

    /** Execute a method file once; only GET files may render HTML directly. */
    public function dispatch(string $name, ServerRequestInterface $request): ResponseInterface
    {
        $file = SafePath::resolveFile($this->directory, $name);
        $metadata = str_ends_with($name, '.get.php') ? $this->escapeData($this->metadata($name)) : [];
        [$result, $html] = OutputBuffer::capture(static function () use ($file, $request, $metadata) {
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
        return Responses::html($this->renderFiles(array_filter([$header]), ['metadata' => $metadata], $request) . $html
            . $this->scriptTags($name)
            . $this->renderFiles(array_filter([$footer]), ['metadata' => $metadata], $request));
    }

    /**
     * Use the nearest existing header and footer independently; omit absent parts.
     * The metadata data key overrides inherited defaults and must be an array; page is ordinary application data.
     *
     * @param array<string,mixed> $data Variables exposed to all rendered files; strings are HTML-escaped recursively.
     * @throws \InvalidArgumentException For reserved or invalid view names.
     * @throws \RuntimeException For missing views or files outside the view root.
     */
    public function response(string $view, array $data = [], int $status = 200, bool $layout = true): \Psr\Http\Message\ResponseInterface
    {
        $name = SafePath::normalizeRelativePath($view);
        if ($name === null || in_array(pathinfo(basename($name), PATHINFO_FILENAME), ['_header', '_footer', '_middleware', '_metadata'], true)) {
            throw new \InvalidArgumentException('Invalid view name');
        }
        $name = str_ends_with($name, '.php') ? $name : $name . '.php';
        if (in_array(pathinfo(substr($name, 0, -4), PATHINFO_EXTENSION), RouteMap::METHODS, true)) {
            throw new \InvalidArgumentException('Method files must be dispatched by the router');
        }
        $file = SafePath::resolveFile($this->directory, $name);
        if (array_key_exists('metadata', $data) && !is_array($data['metadata'])) {
            throw new \InvalidArgumentException('View metadata must be an array');
        }
        $data['metadata'] = array_replace($this->metadata($name), $data['metadata'] ?? []);
        $files = [$file];
        if ($layout) {
            [$header, $footer] = $this->layoutFiles($name);
            $files = array_filter([$header, $file, $footer], static fn(?string $path): bool => $path !== null);
        }
        return Responses::html($this->renderFiles($files, $this->escapeData($data),
            after: $layout ? [$file => $this->scriptTags($name)] : []), $status);
    }

    private function scriptTags(string $name): string
    {
        if ($this->scriptDirectory === null || !is_dir($this->scriptDirectory)) {
            return '';
        }
        $scripts = [];
        $ancestor = dirname($name);
        while (true) {
            $scripts[] = ($ancestor === '.' ? '' : $ancestor . '/') . '_script.js';
            if ($ancestor === '.') {
                break;
            }
            $ancestor = dirname($ancestor);
        }
        $scripts = array_reverse($scripts);
        if (str_ends_with($name, '.get.php') && !preg_match('#(?:^|/)[_.]#', $name)) {
            $scripts[] = substr($name, 0, -8) . '.js';
        }
        $html = '';
        foreach (array_unique($scripts) as $relative) {
            if (!is_file($this->scriptDirectory . '/' . $relative)) {
                continue;
            }
            $file = SafePath::resolveFile($this->scriptDirectory, $relative);
            $url = PublicUrl::versionedAsset('assets/js/pages/' . $relative, $file);
            $html .= '<script type="module" src="' . htmlspecialchars($url, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '"></script>';
        }
        return $html;
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

    /** @return array<string|int,mixed> Parent defaults with shallow child overrides. */
    private function metadata(string $name): array
    {
        $ancestors = [];
        $ancestor = dirname($name);
        while (true) {
            $ancestors[] = $ancestor;
            if ($ancestor === '.') {
                break;
            }
            $ancestor = dirname($ancestor);
        }
        $metadata = [];
        foreach (array_reverse($ancestors) as $ancestor) {
            $relative = ($ancestor === '.' ? '' : $ancestor . '/') . '_metadata.php';
            clearstatcache(true, $this->directory . '/' . $relative);
            if (!file_exists($this->directory . '/' . $relative)) {
                continue;
            }
            $file = SafePath::resolveFile($this->directory, $relative);
            [$data, $output] = OutputBuffer::capture(static function () use ($file) {
                return require $file;
            });
            if (!is_array($data) || $output !== '') {
                throw new \UnexpectedValueException('Metadata must return an array without printing output: ' . $relative);
            }
            $metadata = array_replace($metadata, $data);
        }
        return $metadata;
    }

    /**
     * @param array<int,string> $files
     * @param array<string,mixed> $data
     * @param array<string,string> $after HTML inserted after its corresponding template.
     */
    private function renderFiles(array $files, array $data, ?ServerRequestInterface $request = null, array $after = []): string
    {
        if ($files === []) {
            return '';
        }
        $render = static function (array $__files, array $__data, ?ServerRequestInterface $__request, array $__after): void {
            if ($__request !== null) {
                $request = $__request;
            }
            // Application data must not replace the renderer's file list.
            extract($__data, EXTR_SKIP);
            foreach ($__files as $__file) {
                require $__file;
                echo $__after[$__file] ?? '';
            }
        };
        [, $html] = OutputBuffer::capture(fn() => $render($files, $data, $request, $after));
        return $html;
    }

    /**
     * @param array<string|int,mixed> $data
     * @return array<string|int,mixed>
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
