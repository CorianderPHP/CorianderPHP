<?php
declare(strict_types=1);

namespace CorianderCore\Core\Router;

/**
 * Shared guard for user-provided relative path segments used by routing/views.
 */
final class SafePath
{
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
