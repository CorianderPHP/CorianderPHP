<?php
declare(strict_types=1);

namespace CorianderCore\Core\Http;

final class TrustedProxy
{
    /**
     * @param array<string,mixed> $serverParams
     */
    public static function isSecureRequest(array $serverParams, ?string $trustedProxies = null): bool
    {
        $https = strtolower((string) ($serverParams['HTTPS'] ?? ''));
        if ($https !== '' && $https !== 'off' && $https !== '0') {
            return true;
        }

        $remoteAddr = (string) ($serverParams['REMOTE_ADDR'] ?? '');
        if (self::isTrustedProxy($remoteAddr, $trustedProxies)) {
            $forwardedProto = strtolower((string) ($serverParams['HTTP_X_FORWARDED_PROTO'] ?? ''));
            if ($forwardedProto !== '') {
                $firstHop = trim(explode(',', $forwardedProto, 2)[0]);
                if ($firstHop === 'https') {
                    return true;
                }
            }

            $forwarded = strtolower((string) ($serverParams['HTTP_FORWARDED'] ?? ''));
            if ($forwarded !== '' && str_contains($forwarded, 'proto=https')) {
                return true;
            }

            $forwardedSsl = strtolower((string) ($serverParams['HTTP_X_FORWARDED_SSL'] ?? ''));
            if ($forwardedSsl === 'on') {
                return true;
            }

            $frontEndHttps = strtolower((string) ($serverParams['HTTP_FRONT_END_HTTPS'] ?? ''));
            if ($frontEndHttps === 'on') {
                return true;
            }
        }

        return (string) ($serverParams['SERVER_PORT'] ?? '') === '443';
    }

    private static function isTrustedProxy(string $remoteAddr, ?string $trustedProxies = null): bool
    {
        if ($remoteAddr === '') {
            return false;
        }

        $trusted = $trustedProxies ?? (defined('TRUSTED_PROXIES') ? (string) TRUSTED_PROXIES : '127.0.0.1,::1');
        $entries = array_map(static fn(string $item): string => trim($item), explode(',', $trusted));

        foreach ($entries as $entry) {
            if ($entry === '') {
                continue;
            }

            if ($entry === '*') {
                return true;
            }

            if (self::ipInCidr($remoteAddr, $entry)) {
                return true;
            }
        }

        return false;
    }

    private static function ipInCidr(string $ip, string $cidr): bool
    {
        if (!str_contains($cidr, '/')) {
            return $ip === $cidr;
        }

        [$subnet, $prefixLength] = explode('/', $cidr, 2);
        if ($subnet === '' || !ctype_digit($prefixLength)) {
            return false;
        }

        $ipBinary = @inet_pton($ip);
        $subnetBinary = @inet_pton($subnet);
        if ($ipBinary === false || $subnetBinary === false || strlen($ipBinary) !== strlen($subnetBinary)) {
            return false;
        }

        $bits = (int) $prefixLength;
        $maxBits = strlen($ipBinary) * 8;
        if ($bits < 0 || $bits > $maxBits) {
            return false;
        }

        $bytes = intdiv($bits, 8);
        $remainder = $bits % 8;

        if ($bytes > 0 && substr($ipBinary, 0, $bytes) !== substr($subnetBinary, 0, $bytes)) {
            return false;
        }

        if ($remainder === 0) {
            return true;
        }

        $mask = (0xFF << (8 - $remainder)) & 0xFF;
        return (ord($ipBinary[$bytes]) & $mask) === (ord($subnetBinary[$bytes]) & $mask);
    }
}
