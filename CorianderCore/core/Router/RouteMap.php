<?php
declare(strict_types=1);

namespace CorianderCore\Core\Router;

/** Discovers filenames only; PHP handler code is never executed here. */
class RouteMap
{
    public const METHODS = ['get', 'post', 'put', 'patch', 'delete', 'head', 'options'];

    /**
     * @return array{static:array<string,array>,dynamic:list<array>,middleware:list<string>}
     * @throws \RuntimeException For invalid, duplicate or ambiguous method-file paths.
     */
    public function discover(string $directory): array
    {
        $groups = [];
        if (!is_dir($directory)) {
            return ['static' => [], 'dynamic' => [], 'middleware' => []];
        }
        $root = rtrim(str_replace('\\', '/', realpath($directory)), '/');
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS));
        foreach ($iterator as $file) {
            if (!$file->isFile()) {
                continue;
            }
            $relative = substr(str_replace('\\', '/', $file->getPathname()), strlen(rtrim(str_replace('\\', '/', $directory), '/')) + 1);
            $definition = self::definition($relative);
            if ($definition === null) {
                continue;
            }
            SafePath::resolveFile($root, $relative);
            [$method, $route] = $definition;
            $key = $route['regex'];
            self::assertCompatible($groups[$key] ?? null, $route, $method);
            $directories = [''];
            $parent = dirname($relative);
            if ($parent !== '.') {
                $current = '';
                foreach (explode('/', $parent) as $segment) {
                    $current = ltrim($current . '/' . $segment, '/');
                    $directories[] = $current;
                }
            }
            $knownMiddleware = [];
            foreach ($directories as $ancestor) {
                $middleware = ltrim($ancestor . '/_middleware.php', '/');
                if (is_file($root . '/' . $middleware)) {
                    $knownMiddleware[] = $middleware;
                }
            }
            $groups[$key] ??= $route + ['methods' => []];
            $groups[$key]['methods'][$method] = ['file' => $relative, 'directories' => $directories, 'middleware' => $knownMiddleware];
        }
        $map = ['static' => [], 'dynamic' => [], 'middleware' => is_file($root . '/_middleware.php') ? ['_middleware.php'] : []];
        foreach ($groups as $route) {
            if ($route['params'] === []) {
                $map['static'][$route['path']] = $route;
            } else {
                $map['dynamic'][] = $route;
            }
        }
        usort($map['dynamic'], static fn($a, $b) => ($b['specificity'] <=> $a['specificity']) ?: strcmp($a['path'], $b['path']));
        return $map;
    }

    /**
     * Validate a proposed relative method filename against current files without executing them or writing a cache.
     * @throws \InvalidArgumentException For private filenames or invalid route syntax.
     * @throws \RuntimeException For conflicting routes or invalid existing definitions.
     */
    public function validateNewRoute(string $directory, string $relative): void
    {
        try {
            $definition = self::definition($relative);
        } catch (\RuntimeException $e) {
            throw new \InvalidArgumentException($e->getMessage(), previous: $e);
        }
        if ($definition === null) {
            throw new \InvalidArgumentException('Invalid route file name.');
        }
        [$method, $candidate] = $definition;
        $map = $this->discover($directory);
        foreach (array_merge($map['static'], $map['dynamic']) as $route) {
            if ($route['regex'] === $candidate['regex']) {
                self::assertCompatible($route, $candidate, $method);
            }
        }
    }

    /** @return array{string,array{path:string,regex:string,params:list<string>,specificity:list<int>}}|null */
    private static function definition(string $relative): ?array
    {
        if (preg_match('#(^|/)[_.]#', $relative) || !str_ends_with($relative, '.php')) {
            return null;
        }
        $name = substr($relative, 0, -4);
        $method = pathinfo($name, PATHINFO_EXTENSION);
        if (!in_array($method, self::METHODS, true)) {
            return null;
        }
        $segments = explode('/', substr($name, 0, -strlen($method) - 1));
        if (end($segments) === 'index') {
            array_pop($segments);
        }
        $params = [];
        $pattern = [];
        $specificity = [];
        foreach ($segments as $segment) {
            if (preg_match('/^\[([A-Za-z][A-Za-z0-9_]*)\]$/', $segment, $parameter)) {
                if (in_array($parameter[1], $params, true)) {
                    throw new \RuntimeException('Repeated route parameter: ' . $relative);
                }
                $params[] = $parameter[1];
                $pattern[] = '([^/]+)';
                $specificity[] = 0;
            } elseif (preg_match('/^[A-Za-z0-9][A-Za-z0-9._-]*$/', $segment)) {
                $pattern[] = preg_quote($segment, '#');
                $specificity[] = 1;
            } else {
                throw new \RuntimeException('Invalid route segment: ' . $relative);
            }
        }
        return [strtoupper($method), ['path' => implode('/', $segments), 'regex' => '#^' . implode('/', $pattern) . '$#',
            'params' => $params, 'specificity' => $specificity]];
    }

    private static function assertCompatible(?array $existing, array $candidate, string $method): void
    {
        if ($existing !== null && $existing['params'] !== $candidate['params']) {
            throw new \RuntimeException('Ambiguous parameter names for route: ' . $candidate['path']);
        }
        if (isset($existing['methods'][$method])) {
            throw new \RuntimeException('Duplicate ' . $method . ' route: ' . $candidate['path']);
        }
    }

    /** Decode each segment once; return null for malformed or unsafe request paths. */
    public static function requestPath(string $path): ?string
    {
        if (str_contains($path, '//') || preg_match('/%(?![0-9a-f]{2})/i', $path)) {
            return null;
        }
        $segments = $path === '/' || $path === '' ? [] : explode('/', trim($path, '/'));
        foreach ($segments as &$segment) {
            $segment = rawurldecode($segment);
            if ($segment === '.' || $segment === '..' || strpbrk($segment, "/\\\0") !== false) {
                return null;
            }
        }
        return implode('/', $segments);
    }

    /**
     * @param array{static:array<string,array>,dynamic:list<array>,middleware:list<string>} $map
     * @return array{0:array,1:array<string,string>}|null Matched route and decoded parameters.
     */
    public static function match(array $map, string $path): ?array
    {
        if (isset($map['static'][$path])) {
            return [$map['static'][$path], []];
        }
        foreach ($map['dynamic'] as $route) {
            if (preg_match($route['regex'], $path, $matches)) {
                array_shift($matches);
                return [$route, array_combine($route['params'], $matches)];
            }
        }
        return null;
    }
}
