<?php
declare(strict_types=1);

namespace CorianderCore\Core\Console\Commands\Migrate;

final class MigrateOptions
{
    public function __construct(
        public readonly string $action,
        public readonly bool $dryRun,
        public readonly bool $allowChanged,
        public readonly int $step,
    ) {}

    /**
     * @param array<int, string> $args
     */
    public static function fromArgs(array $args): self
    {
        $action = 'up';
        if (isset($args[0]) && in_array(strtolower($args[0]), ['rollback', 'status', 'up'], true)) {
            $action = strtolower((string) array_shift($args));
        }

        return new self(
            $action,
            in_array('--dry-run', $args, true),
            in_array('--allow-changed', $args, true),
            self::extractStepOption($args),
        );
    }

    /**
     * @param array<int, string> $args
     */
    private static function extractStepOption(array $args): int
    {
        foreach ($args as $arg) {
            if (!str_starts_with($arg, '--step=')) {
                continue;
            }

            $value = (int) substr($arg, strlen('--step='));
            return $value > 0 ? $value : 1;
        }

        return 1;
    }
}
