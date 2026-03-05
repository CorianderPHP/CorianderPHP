<?php
declare(strict_types=1);

namespace CorianderCore\Core\Console\Commands\Migrate;

use CorianderCore\Core\Console\ConsoleOutput;

final class MigrateOutputPresenter
{
    /**
     * @param list<array{filename: string, status: string, batch: int|null, executed_at: string|null, changed: bool}> $rows
     */
    public function printStatus(array $rows): void
    {
        if ($rows === []) {
            ConsoleOutput::print('&eNo migration files found in database/migrations.');
            return;
        }

        foreach ($rows as $row) {
            $state = $row['status'] === 'applied' ? '&2applied' : '&epending';
            $batch = $row['batch'] === null ? '-' : (string) $row['batch'];
            $changed = $row['changed'] ? ' &4(changed)' : '';
            ConsoleOutput::print("{$state}&7 [batch {$batch}] {$row['filename']}{$changed}");
        }
    }

    /**
     * @param array{applied: int, pending: int, ran: list<string>, batch: int} $result
     */
    public function printMigrateResult(array $result, bool $dryRun): void
    {
        if ($dryRun) {
            ConsoleOutput::print('&eDry run: migrate plan only, no changes applied.');
            if ($result['ran'] === []) {
                ConsoleOutput::print('&eNo pending migrations.');
                return;
            }

            foreach ($result['ran'] as $file) {
                ConsoleOutput::print('&8- would run: ' . $file);
            }
            return;
        }

        if ($result['applied'] === 0) {
            ConsoleOutput::print('&2Database is up to date.');
            return;
        }

        ConsoleOutput::print('&2Applied migrations: ' . (string) $result['applied'] . ' (batch ' . (string) $result['batch'] . ')');
        foreach ($result['ran'] as $file) {
            ConsoleOutput::print('&8- ran: ' . $file);
        }
    }

    /**
     * @param array{rolled_back: int, batch: int|null, files: list<string>} $result
     */
    public function printRollbackResult(array $result, bool $dryRun): void
    {
        if ($result['rolled_back'] === 0 && $result['files'] === []) {
            ConsoleOutput::print('&eNothing to rollback.');
            return;
        }

        if ($dryRun) {
            ConsoleOutput::print('&eDry run: rollback plan only, no changes applied.');
            foreach ($result['files'] as $file) {
                ConsoleOutput::print('&8- would rollback: ' . $file);
            }
            return;
        }

        ConsoleOutput::print('&2Rolled back migrations: ' . (string) $result['rolled_back']);
        foreach ($result['files'] as $file) {
            ConsoleOutput::print('&8- rolled back: ' . $file);
        }
    }
}
