<?php
declare(strict_types=1);

namespace CorianderCore\Core\Bootstrap;

final class SessionBootstrap
{
    /**
     * @var array{path:string,secure:bool,httponly:bool,samesite:string}
     */
    private static array $cookieParams = [
        'path' => '/',
        'secure' => false,
        'httponly' => true,
        'samesite' => 'Lax',
    ];

    public static function configure(bool $secure): void
    {
        self::$cookieParams = [
            'path' => '/',
            'secure' => $secure,
            'httponly' => true,
            'samesite' => 'Lax',
        ];
    }

    /**
     * @param array<string,mixed> $serverParams
     * @param string[] $statelessPrefixes
     */
    public static function startForRequest(array $serverParams, array $statelessPrefixes = ['api']): void
    {
        if (self::isStatelessRequest($serverParams, $statelessPrefixes)) {
            return;
        }

        self::start();
    }

    public static function start(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }

        session_set_cookie_params(self::$cookieParams);
        session_start();
    }

    /**
     * @param array<string,mixed> $serverParams
     * @param string[] $statelessPrefixes
     */
    private static function isStatelessRequest(array $serverParams, array $statelessPrefixes): bool
    {
        $uri = (string) ($serverParams['REQUEST_URI'] ?? '/');
        $path = parse_url($uri, PHP_URL_PATH);
        $normalizedPath = trim(is_string($path) ? $path : '/', '/');

        foreach ($statelessPrefixes as $prefix) {
            $normalizedPrefix = trim($prefix, '/');
            if ($normalizedPrefix === '') {
                continue;
            }

            if ($normalizedPath === $normalizedPrefix || str_starts_with($normalizedPath, $normalizedPrefix . '/')) {
                return true;
            }
        }

        return false;
    }
}
