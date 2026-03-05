<?php
declare(strict_types=1);

namespace CorianderCore\Core\Console\Commands\Update;

use CorianderCore\Core\Console\ConsoleOutput;

final class UpdateOutputPresenter
{
    /**
     * @param array{scope:string,restored_count:int,restored_files:array<int,string>} $result
     */
    public function printRollbackResult(array $result): void
    {
        ConsoleOutput::print('Rollback scope: &8' . $result['scope']);
        ConsoleOutput::print('Restored files: &2' . (string) $result['restored_count']);
        foreach ($result['restored_files'] as $restoredFile) {
            ConsoleOutput::print('&8- restored: ' . $restoredFile);
        }
    }

    public function printRollbackDryRunWarning(): void
    {
        ConsoleOutput::print('&e--dry-run has no effect with --rollback.');
    }

    public function printRollbackCancelled(): void
    {
        ConsoleOutput::print('&eRollback cancelled.');
    }

    public function printRollbackSuccess(): void
    {
        ConsoleOutput::print('&2Rollback completed successfully.');
    }

    public function printVersions(string $localVersion, string $latestVersion): void
    {
        ConsoleOutput::print('Current version: &8' . $localVersion);
        ConsoleOutput::print('Latest version: &2' . $latestVersion);
    }

    public function printAlreadyUpToDate(): void
    {
        ConsoleOutput::print('&2Framework is already up to date.');
    }

    public function printDryRunEnabled(): void
    {
        ConsoleOutput::print('&eDry run enabled: no files will be changed.');
    }

    public function printUpdateCancelled(): void
    {
        ConsoleOutput::print('&eUpdate cancelled.');
    }

    /**
     * @param array{operations: array<int, array{type:string,relative_path:string,source:string,destination:string}>, add_count:int, update_count:int, unchanged_count:int, missing_paths: string[]} $result
     */
    public function printPlan(array $result, bool $dryRun): void
    {
        $label = $dryRun ? 'Would add' : 'Planned add';
        ConsoleOutput::print($label . ': &2' . (string) $result['add_count']);

        $label = $dryRun ? 'Would update' : 'Planned update';
        ConsoleOutput::print($label . ': &2' . (string) $result['update_count']);

        ConsoleOutput::print('Unchanged: &8' . (string) $result['unchanged_count']);

        if (!empty($result['missing_paths'])) {
            ConsoleOutput::print('&eWarning:&7 missing managed paths in archive: &8' . implode(', ', $result['missing_paths']));
        }

        foreach ($result['operations'] as $operation) {
            $action = $dryRun ? 'would ' . $operation['type'] : 'plan ' . $operation['type'];
            ConsoleOutput::print('&8- ' . $action . ': ' . $operation['relative_path']);
        }
    }

    public function printDryRunNoChanges(): void
    {
        ConsoleOutput::print('&eNo changes applied (--dry-run).');
    }

    /**
     * @param array{applied_add_count:int,applied_update_count:int,skipped_local_changes_count:int,skipped_local_changes:array<int,string>,backup_count:int} $result
     */
    public function printAppliedSummary(array $result, bool $force): void
    {
        ConsoleOutput::print('Applied add: &2' . (string) $result['applied_add_count']);
        ConsoleOutput::print('Applied update: &2' . (string) $result['applied_update_count']);

        if ($result['skipped_local_changes_count'] > 0) {
            ConsoleOutput::print('&eSkipped local changes: &7' . (string) $result['skipped_local_changes_count']);
            foreach ($result['skipped_local_changes'] as $skippedPath) {
                ConsoleOutput::print('&8- skipped: ' . $skippedPath);
            }
            if (!$force) {
                ConsoleOutput::print('&eUse --force to overwrite skipped local changes.');
            }
        }

        ConsoleOutput::print('Backups created: &2' . (string) $result['backup_count']);
    }

    /**
     * @param array{success: bool, exit_code: int, output: string} $taskResult
     */
    public function printPostTaskResult(string $taskName, array $taskResult): void
    {
        if ($taskResult['success']) {
            ConsoleOutput::print('&2Post-task succeeded:&7 ' . $taskName);
            return;
        }

        ConsoleOutput::print('&ePost-task failed:&7 ' . $taskName . ' (exit ' . $taskResult['exit_code'] . ')');
        if ($taskResult['output'] !== '') {
            ConsoleOutput::print('&8' . $taskResult['output']);
        }
    }

    public function printUpdateSuccess(): void
    {
        ConsoleOutput::print('&2Framework update completed successfully.');
    }
}
