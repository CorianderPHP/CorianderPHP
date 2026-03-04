<?php
declare(strict_types=1);

namespace CorianderCore\Core\Console\Commands;

use CorianderCore\Core\Console\ConsoleOutput;
use CorianderCore\Core\Console\Services\Updater\FrameworkUpdateService;
use CorianderCore\Core\Console\Services\Updater\PostUpdateTasksService;

class Update
{
    private FrameworkUpdateService $updateService;
    private PostUpdateTasksService $postUpdateTasksService;

    /**
     * @var callable(string):bool
     */
    private $confirmationPrompt;

    public function __construct(
        ?FrameworkUpdateService $updateService = null,
        ?callable $confirmationPrompt = null,
        ?PostUpdateTasksService $postUpdateTasksService = null
    ) {
        $this->updateService = $updateService ?? new FrameworkUpdateService();
        $this->confirmationPrompt = $confirmationPrompt ?? [$this, 'promptUserConfirmation'];
        $this->postUpdateTasksService = $postUpdateTasksService ?? new PostUpdateTasksService();
    }

    /**
     * @param array<int, string> $args
     */
    public function execute(array $args = []): void
    {
        $assumeYes = in_array('--yes', $args, true);
        $dryRun = in_array('--dry-run', $args, true);
        $force = in_array('--force', $args, true);
        $clearCache = in_array('--clear-cache', $args, true);

        $localVersion = $this->updateService->getLocalVersion();
        $latestRelease = $this->updateService->fetchLatestRelease();
        $latestVersion = $latestRelease['tag'];

        ConsoleOutput::print('Current version: &8' . $localVersion);
        ConsoleOutput::print('Latest version: &2' . $latestVersion);

        if (!$this->updateService->isUpdateAvailable($localVersion, $latestVersion)) {
            ConsoleOutput::print('&2Framework is already up to date.');
            return;
        }

        if ($dryRun) {
            ConsoleOutput::print('&eDry run enabled: no files will be changed.');
        } elseif (!$assumeYes) {
            $confirmed = ($this->confirmationPrompt)('A new version is available. Update now? [y/N]: ');
            if (!$confirmed) {
                ConsoleOutput::print('&eUpdate cancelled.');
                return;
            }
        }

        $result = $this->updateService->runUpdate($latestRelease['zip_url'], $dryRun, $force, true);

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

        if ($dryRun) {
            ConsoleOutput::print('&eNo changes applied (--dry-run).');
            return;
        }

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

        $postTaskResults = $this->postUpdateTasksService->run($clearCache);
        $this->printPostTaskResult('composer dump-autoload', $postTaskResults['composer_dump_autoload']);

        if ($clearCache && $postTaskResults['cache_clear'] !== null) {
            $this->printPostTaskResult('cache clear', $postTaskResults['cache_clear']);
        }

        ConsoleOutput::print('&2Framework update completed successfully.');
    }

    /**
     * @param array{success: bool, exit_code: int, output: string} $taskResult
     */
    private function printPostTaskResult(string $taskName, array $taskResult): void
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

    private function promptUserConfirmation(string $message): bool
    {
        fwrite(STDOUT, $message);
        $input = fgets(STDIN);
        if ($input === false) {
            return false;
        }

        $normalized = strtolower(trim($input));
        return $normalized === 'y' || $normalized === 'yes';
    }
}
