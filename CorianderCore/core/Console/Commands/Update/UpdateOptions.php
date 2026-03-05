<?php
declare(strict_types=1);

namespace CorianderCore\Core\Console\Commands\Update;

final class UpdateOptions
{
    public function __construct(
        public readonly bool $assumeYes,
        public readonly bool $dryRun,
        public readonly bool $force,
        public readonly bool $clearCache,
        public readonly bool $rollback,
        public readonly ?string $backupDirectory,
    ) {
    }

    /**
     * @param array<int,string> $args
     */
    public static function fromArgs(array $args): self
    {
        return new self(
            assumeYes: in_array('--yes', $args, true),
            dryRun: in_array('--dry-run', $args, true),
            force: in_array('--force', $args, true),
            clearCache: in_array('--clear-cache', $args, true),
            rollback: in_array('--rollback', $args, true),
            backupDirectory: self::extractOptionValue($args, '--backup-dir'),
        );
    }

    /**
     * @param array<int,string> $args
     */
    private static function extractOptionValue(array $args, string $option): ?string
    {
        foreach ($args as $arg) {
            if (!str_starts_with($arg, $option . '=')) {
                continue;
            }

            $value = trim(substr($arg, strlen($option) + 1));
            return $value !== '' ? $value : null;
        }

        return null;
    }
}
