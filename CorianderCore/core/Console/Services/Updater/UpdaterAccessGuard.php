<?php
declare(strict_types=1);

namespace CorianderCore\Core\Console\Services\Updater;

use RuntimeException;

/**
 * Enforces updater command access policy (environment, auth token, rate limit).
 */
final class UpdaterAccessGuard
{
    private string $projectRoot;

    /**
     * @var callable():int
     */
    private $clock;

    /**
     * @param callable():int|null $clock
     */
    public function __construct(?string $projectRoot = null, ?callable $clock = null)
    {
        $this->projectRoot = $projectRoot ?? (defined('PROJECT_ROOT') ? PROJECT_ROOT : dirname(__DIR__, 6));
        $this->clock = $clock ?? static fn(): int => time();
    }

    /**
     * @param array<int,string> $args
     * @return array<int,string>
     */
    public function assertCanRun(array $args): array
    {
        if (PHP_SAPI !== 'cli') {
            throw new RuntimeException('Framework updater can only run from CLI.');
        }

        $this->assertUpdaterEnabledForEnvironment();
        $sanitizedArgs = $this->assertAuthToken($args);
        $this->assertRateLimit();

        return $sanitizedArgs;
    }

    private function assertUpdaterEnabledForEnvironment(): void
    {
        $enabledFlag = getenv('CORIANDER_UPDATER_ENABLED');
        if ($enabledFlag !== false && in_array(strtolower(trim((string) $enabledFlag)), ['0', 'false', 'no', 'off'], true)) {
            throw new RuntimeException('Framework updater is disabled by CORIANDER_UPDATER_ENABLED.');
        }

        $appEnv = strtolower(trim((string) (getenv('APP_ENV') ?: 'development')));
        $allowProduction = getenv('CORIANDER_UPDATER_ALLOW_PRODUCTION');
        if ($appEnv === 'production' && !in_array(strtolower(trim((string) $allowProduction)), ['1', 'true', 'yes', 'on'], true)) {
            throw new RuntimeException('Framework updater is blocked in production. Set CORIANDER_UPDATER_ALLOW_PRODUCTION=1 to enable explicitly.');
        }
    }

    /**
     * @param array<int,string> $args
     * @return array<int,string>
     */
    private function assertAuthToken(array $args): array
    {
        $expectedToken = getenv('CORIANDER_UPDATER_AUTH_TOKEN');
        if (!is_string($expectedToken) || trim($expectedToken) === '') {
            return $args;
        }

        $providedToken = null;
        $filteredArgs = [];

        foreach ($args as $arg) {
            if (str_starts_with($arg, '--auth-token=')) {
                $providedToken = substr($arg, strlen('--auth-token='));
                continue;
            }

            $filteredArgs[] = $arg;
        }

        if (!is_string($providedToken) || $providedToken === '' || !hash_equals($expectedToken, $providedToken)) {
            throw new RuntimeException('Invalid updater auth token. Provide --auth-token=<token>.');
        }

        return $filteredArgs;
    }

    private function assertRateLimit(): void
    {
        $maxAttempts = (int) (getenv('CORIANDER_UPDATER_MAX_ATTEMPTS_PER_HOUR') ?: 5);
        if ($maxAttempts <= 0) {
            return;
        }

        $rateLimitFile = $this->resolveRateLimitFilePath();
        $directory = dirname($rateLimitFile);
        if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
            throw new RuntimeException('Unable to create updater rate-limit directory.');
        }

        $this->assertPathInsideProject($rateLimitFile);

        $handle = @fopen($rateLimitFile, 'c+');
        if ($handle === false) {
            throw new RuntimeException('Unable to open updater rate-limit state file.');
        }

        try {
            if (!@flock($handle, LOCK_EX)) {
                throw new RuntimeException('Unable to lock updater rate-limit state file.');
            }

            rewind($handle);
            $raw = stream_get_contents($handle);
            $state = is_string($raw) && trim($raw) !== '' ? json_decode($raw, true) : null;
            if (!is_array($state)) {
                $state = [];
            }

            $hour = gmdate('Y-m-d-H', ($this->clock)());
            $currentHour = isset($state['hour']) && is_string($state['hour']) ? $state['hour'] : '';
            $currentCount = isset($state['count']) ? (int) $state['count'] : 0;

            if ($currentHour !== $hour) {
                $currentHour = $hour;
                $currentCount = 0;
            }

            if ($currentCount >= $maxAttempts) {
                throw new RuntimeException('Updater rate limit reached (' . $maxAttempts . ' attempts/hour). Retry next hour or raise CORIANDER_UPDATER_MAX_ATTEMPTS_PER_HOUR.');
            }

            $currentCount++;

            $newState = json_encode([
                'hour' => $currentHour,
                'count' => $currentCount,
            ], JSON_UNESCAPED_SLASHES);

            if (!is_string($newState)) {
                throw new RuntimeException('Unable to encode updater rate-limit state.');
            }

            rewind($handle);
            if (!ftruncate($handle, 0)) {
                throw new RuntimeException('Unable to truncate updater rate-limit state file.');
            }

            if (fwrite($handle, $newState) === false) {
                throw new RuntimeException('Unable to write updater rate-limit state file.');
            }

            fflush($handle);
        } finally {
            @flock($handle, LOCK_UN);
            fclose($handle);
        }
    }

    private function resolveRateLimitFilePath(): string
    {
        $configured = getenv('CORIANDER_UPDATER_RATE_LIMIT_FILE');
        if (!is_string($configured) || trim($configured) === '') {
            return $this->projectRoot . '/cache/.updater-rate-limit.json';
        }

        return $this->projectRoot . '/' . $this->normalizeRelativePath($configured);
    }

    private function assertPathInsideProject(string $path): void
    {
        $projectRoot = $this->normalizePathForComparison($this->projectRoot);
        $directory = dirname($path);
        $resolvedDirectory = realpath($directory);
        if ($resolvedDirectory === false) {
            throw new RuntimeException('Unable to resolve updater rate-limit directory.');
        }

        $normalizedDirectory = $this->normalizePathForComparison($resolvedDirectory);
        if ($normalizedDirectory !== $projectRoot && !str_starts_with($normalizedDirectory, $projectRoot . '/')) {
            throw new RuntimeException('Updater rate-limit path must stay inside project root.');
        }
    }

    private function normalizePathForComparison(string $path): string
    {
        $normalized = rtrim(str_replace('\\', '/', $path), '/');
        if (DIRECTORY_SEPARATOR === '\\') {
            $normalized = strtolower($normalized);
        }

        return $normalized;
    }

    private function normalizeRelativePath(string $path): string
    {
        if (str_contains($path, "\0")) {
            throw new RuntimeException('Updater rate-limit path contains invalid null-byte characters.');
        }

        $normalized = str_replace('\\', '/', trim($path));
        if ($normalized === '') {
            throw new RuntimeException('Updater rate-limit path cannot be empty.');
        }

        if (str_starts_with($normalized, '/') || preg_match('/^[A-Za-z]:\//', $normalized) === 1) {
            throw new RuntimeException('Updater rate-limit path must be relative to project root.');
        }

        $segments = explode('/', trim($normalized, '/'));
        $safeSegments = [];

        foreach ($segments as $segment) {
            if ($segment === '' || $segment === '.') {
                continue;
            }

            if ($segment === '..') {
                throw new RuntimeException('Updater rate-limit path cannot contain traversal segments.');
            }

            $safeSegments[] = $segment;
        }

        if ($safeSegments === []) {
            throw new RuntimeException('Updater rate-limit path must contain at least one segment.');
        }

        return implode('/', $safeSegments);
    }
}
