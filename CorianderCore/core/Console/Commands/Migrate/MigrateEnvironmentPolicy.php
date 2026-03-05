<?php
declare(strict_types=1);

namespace CorianderCore\Core\Console\Commands\Migrate;

use RuntimeException;

final class MigrateEnvironmentPolicy
{
    public function assertAllowed(MigrateOptions $options): void
    {
        if ($options->allowChanged && !$this->isLocalEnvironment()) {
            throw new RuntimeException('--allow-changed is restricted to local/development environments.');
        }
    }

    private function isLocalEnvironment(): bool
    {
        $env = getenv('APP_ENV');
        if ($env === false && defined('APP_ENV')) {
            $env = (string) APP_ENV;
        }

        $normalized = strtolower(trim((string) $env));
        return in_array($normalized, ['local', 'dev', 'development', 'test'], true);
    }
}
