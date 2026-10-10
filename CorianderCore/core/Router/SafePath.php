<?php
declare(strict_types=1);

namespace CorianderCore\Core\Router;

/**
 * Shared guard for user-provided relative path segments used by routing/views.
 */
final class SafePath
{
    /** Validate existing ancestors before a generator creates directories. */
    public static function destination(string $root, string $relative): string
    {
        $relative = self::normalizeRelativePath($relative);
        if ($relative === null) {
            throw new \RuntimeException('Invalid generated path');
        }
        if (!is_dir($root) && !mkdir($root, 0755, true)) {
            throw new \RuntimeException('Cannot create generator root');
        }
        $realRoot = realpath($root);
        $destination = $root . '/' . $relative;
        $ancestor = dirname($destination);
        while (!file_exists($ancestor) && !is_link($ancestor)) {
            $ancestor = dirname($ancestor);
        }
        $realAncestor = realpath($ancestor);
        if ($realRoot === false || $realAncestor === false || ($realAncestor !== $realRoot
            && !str_starts_with($realAncestor, $realRoot . DIRECTORY_SEPARATOR))) {
            throw new \RuntimeException('Generated path escapes its root');
        }
        return $destination;
    }

    /** @throws \RuntimeException For missing files or paths escaping the root, including symlinks. */
    public static function resolveFile(string $root, string $relative): string
    {
        $relative = self::normalizeRelativePath($relative);
        $realRoot = realpath($root);
        $file = $relative === null ? false : realpath($root . '/' . $relative);
        if ($realRoot === false || $file === false || !is_file($file)) {
            throw new \RuntimeException('Required application file is missing or invalid: ' . ($relative ?? '[invalid path]'));
        }
        $prefix = rtrim($realRoot, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
        if (!str_starts_with($file, $prefix)) {
            throw new \RuntimeException('Application file escapes its directory: ' . $relative);
        }
        return $file;
    }

    /** Normalize application paths; return null for absolute, empty or traversing paths. */
    public static function normalizeRelativePath(string $path): ?string
    {
        if (str_contains($path, "\0")) {
            return null;
        }

        $normalizedPath = str_replace('\\', '/', trim($path));
        if ($normalizedPath === '' || str_starts_with($normalizedPath, '/')) {
            return null;
        }

        if (preg_match('/^[a-zA-Z]:\//', $normalizedPath) === 1) {
            return null;
        }

        $segments = explode('/', trim($normalizedPath, '/'));
        foreach ($segments as $segment) {
            if ($segment === '' || $segment === '.' || $segment === '..') {
                return null;
            }
        }

        return implode('/', $segments);
    }
}
