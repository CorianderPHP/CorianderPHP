<?php
declare(strict_types=1);

namespace CorianderCore\Core\Router;

/** Discovers filenames only; PHP handler code is never executed here. */
class RouteMap
{
    public function discover(string $directory): array
    {
        $groups = [];
        if (!is_dir($directory)) {
            return ['static' => [], 'dynamic' => []];
        }
        $root = rtrim(str_replace('\\', '/', realpath($directory)), '/');
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS));
        foreach ($iterator as $file) {
            if (!$file->isFile()) {
                continue;
            }
            $relative = substr(str_replace('\\', '/', $file->getPathname()), strlen(rtrim(str_replace('\\', '/', $directory), '/')) + 1);
            if (preg_match('#(^|/)[_.]#', $relative) || !preg_match('/\.(get|post|put|patch|delete|head|options)\.php$/', $relative, $suffix)) {
                continue;
            }
            SafePath::resolveFile($root, $relative);
            $path = substr($relative, 0, -strlen($suffix[0]));
            $segments = explode('/', $path);
            if (end($segments) === 'index') {
                array_pop($segments);
            }
            $params = [];
            $pattern = [];
            $signature = [];
            $specificity = [];
            foreach ($segments as $segment) {
                if (preg_match('/^\[([A-Za-z][A-Za-z0-9_]*)\]$/', $segment, $parameter)) {
                    if (in_array($parameter[1], $params, true)) {
                        throw new \RuntimeException('Repeated route parameter: ' . $relative);
                    }
                    $params[] = $parameter[1];
                    $pattern[] = '([^/]+)';
                    $signature[] = '[]';
                    $specificity[] = 0;
                } elseif (preg_match('/^[A-Za-z0-9][A-Za-z0-9._-]*$/', $segment)) {
                    $pattern[] = preg_quote($segment, '#');
                    $signature[] = $segment;
                    $specificity[] = 1;
                } else {
                    throw new \RuntimeException('Invalid route segment: ' . $relative);
                }
            }
            $key = implode('/', $signature);
            $method = strtoupper($suffix[1]);
            if (isset($groups[$key]) && $groups[$key]['params'] !== $params) {
                throw new \RuntimeException('Ambiguous parameter names for route: ' . $path);
            }
            if (isset($groups[$key]['methods'][$method])) {
                throw new \RuntimeException('Duplicate ' . $method . ' route: ' . $path);
            }
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
            $groups[$key] ??= ['path' => implode('/', $segments), 'regex' => '#^' . implode('/', $pattern) . '$#',
                'params' => $params, 'specificity' => $specificity, 'methods' => []];
            $groups[$key]['methods'][$method] = ['file' => $relative, 'directories' => $directories, 'middleware' => $knownMiddleware];
        }
        $map = ['static' => [], 'dynamic' => []];
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
