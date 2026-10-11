<?php
declare(strict_types=1);

namespace CorianderCore\Core\Support;

final class PublicUrl
{
    public static function asset(string $path): string
    {
        return self::toPublicUrl('/public/' . ltrim(str_replace('\\', '/', $path), '/'));
    }

    /** Raw relative asset path; an optional physical file maps a custom asset root. */
    public static function versionedAsset(string $path, ?string $filePath = null): string
    {
        $path = ltrim(str_replace('\\', '/', $path), '/');
        $url = self::asset(implode('/', array_map('rawurlencode', explode('/', $path))));
        $filePath ??= self::publicFilePath($path);

        if (!is_file($filePath)) {
            return $url;
        }

        if (in_array(strtolower(pathinfo($path, PATHINFO_EXTENSION)), ['css', 'js'], true)) {
            // Generated assets carry their hash so requests only read a bounded prefix.
            $prefix = file_get_contents($filePath, false, null, 0, 96);
            $version = preg_match('~\A(?:// coriander-version:([a-f0-9]{64})\n|/\* coriander-version:([a-f0-9]{64}) \*/\n)~', $prefix ?: '', $match)
                ? ($match[1] ?: $match[2]) : hash_file('sha256', $filePath);
        } else {
            $version = filemtime($filePath);
        }
        return $version === false ? $url : $url . '?' . $version;
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
