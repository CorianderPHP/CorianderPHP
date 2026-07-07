<?php
declare(strict_types=1);

namespace CorianderCore\Core\Support;

final class PublicUrl
{
    public static function asset(string $path): string
    {
        return self::toPublicUrl('/public/' . ltrim(str_replace('\\', '/', $path), '/'));
    }

    public static function versionedAsset(string $path): string
    {
        $url = self::asset($path);
        $filePath = self::publicFilePath($path);

        if (!is_file($filePath)) {
            return $url;
        }

        $mtime = filemtime($filePath);
        return $mtime === false ? $url : $url . '?' . $mtime;
    }

    public static function toPublicUrl(string $path): string
    {
        $path = '/' . ltrim(str_replace('\\', '/', trim($path)), '/');
        if (!str_starts_with($path, '/public/')) {
            return $path;
        }

        return self::publicUrlPrefix() . substr($path, 7);
    }

    private static function publicUrlPrefix(): string
    {
        if (defined('PUBLIC_URL_PREFIX')) {
            return self::normalizePrefix((string) PUBLIC_URL_PREFIX);
        }

        $configuredPrefix = getenv('PUBLIC_URL_PREFIX');
        if (is_string($configuredPrefix)) {
            return self::normalizePrefix($configuredPrefix);
        }

        $scriptName = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? ''));
        return str_contains($scriptName, '/public/index.php') ? '/public' : '';
    }

    private static function normalizePrefix(string $prefix): string
    {
        $prefix = trim($prefix);
        if ($prefix === '' || $prefix === '/') {
            return '';
        }

        return '/' . trim($prefix, '/');
    }

    private static function publicFilePath(string $path): string
    {
        $projectRoot = defined('PROJECT_ROOT') ? (string) PROJECT_ROOT : dirname(__DIR__, 3);
        return rtrim(str_replace('\\', '/', $projectRoot), '/') . '/public/' . ltrim(str_replace('\\', '/', $path), '/');
    }
}
