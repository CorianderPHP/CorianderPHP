<?php
declare(strict_types=1);

namespace CorianderCore\Core\Console\Commands;

use CorianderCore\Core\Console\Commands\Update\UpdateOptions;
use CorianderCore\Core\Console\Commands\Update\UpdateOutputPresenter;
use CorianderCore\Core\Console\CommandExitCode;
use CorianderCore\Core\Console\Services\Updater\FrameworkUpdateService;
use CorianderCore\Core\Console\Services\Updater\PostUpdateTasksService;
use CorianderCore\Core\Console\Services\Updater\UpdaterAccessGuard;

class Update
{
    /**
     * @var callable(string):bool
     */
    private $confirmationPrompt;

    public function __construct(
        private ?FrameworkUpdateService $updateService = null,
        ?callable $confirmationPrompt = null,
        private ?PostUpdateTasksService $postUpdateTasksService = null,
        private ?UpdaterAccessGuard $accessGuard = null,
        private ?UpdateOutputPresenter $presenter = null,
    ) {
        $this->updateService ??= new FrameworkUpdateService();
        $this->postUpdateTasksService ??= new PostUpdateTasksService();
        $this->accessGuard ??= new UpdaterAccessGuard();
        $this->presenter ??= new UpdateOutputPresenter();
        $this->confirmationPrompt = $confirmationPrompt ?? [$this, 'promptUserConfirmation'];
    }

    /**
     * @param array<int, string> $args
     */
    public function execute(array $args = []): int
    {
        $sanitizedArgs = $this->accessGuard->assertCanRun($args);
        $options = UpdateOptions::fromArgs($sanitizedArgs);

        if ($options->rollback) {
            return $this->executeRollback($options);
        }

        return $this->executeUpdate($options);
    }

    private function executeRollback(UpdateOptions $options): int
    {
        if ($options->dryRun) {
            $this->presenter->printRollbackDryRunWarning();
        }

        if (!$options->assumeYes && !$this->confirm('Rollback latest framework backup now? [y/N]: ')) {
            $this->presenter->printRollbackCancelled();
            return CommandExitCode::FAILURE;
        }

        $result = $this->updateService->rollbackLatestBackup($options->backupDirectory);
        $this->presenter->printRollbackResult($result);

        $postTaskResults = $this->postUpdateTasksService->run($options->clearCache);
        $this->presenter->printPostTaskResult('composer dump-autoload', $postTaskResults['composer_dump_autoload']);

        if ($options->clearCache && $postTaskResults['cache_clear'] !== null) {
            $this->presenter->printPostTaskResult('cache clear', $postTaskResults['cache_clear']);
        }

        $this->presenter->printRollbackSuccess();
        return $this->postTasksSucceeded($postTaskResults) ? CommandExitCode::SUCCESS : CommandExitCode::FAILURE;
    }

    private function executeUpdate(UpdateOptions $options): int
    {
        $localVersion = $this->updateService->getLocalVersion();
        $latestRelease = $this->updateService->fetchLatestRelease();
        $latestVersion = $latestRelease['tag'];
        $backupScope = $localVersion . '-to-' . $latestVersion;

        $this->presenter->printVersions($localVersion, $latestVersion);

        if (!$this->updateService->isUpdateAvailable($localVersion, $latestVersion)) {
            $this->presenter->printAlreadyUpToDate();
            return CommandExitCode::SUCCESS;
        }

        if ($options->dryRun) {
            $this->presenter->printDryRunEnabled();
        } elseif (!$options->assumeYes && !$this->confirm('A new version is available. Update now? [y/N]: ')) {
            $this->presenter->printUpdateCancelled();
            return CommandExitCode::FAILURE;
        }

        $result = $this->updateService->runUpdate(
            $latestRelease['zip_url'],
            $options->dryRun,
            $options->force,
            true,
            $backupScope,
            $options->backupDirectory,
        );

        $this->presenter->printPlan($result, $options->dryRun);

        if ($options->dryRun) {
            $this->presenter->printDryRunNoChanges();
            return CommandExitCode::SUCCESS;
        }

        $this->presenter->printAppliedSummary($result, $options->force);

        $postTaskResults = $this->postUpdateTasksService->run($options->clearCache);
        $this->presenter->printPostTaskResult('composer dump-autoload', $postTaskResults['composer_dump_autoload']);

        if ($options->clearCache && $postTaskResults['cache_clear'] !== null) {
            $this->presenter->printPostTaskResult('cache clear', $postTaskResults['cache_clear']);
        }

        $this->presenter->printUpdateSuccess();
        return $this->postTasksSucceeded($postTaskResults) ? CommandExitCode::SUCCESS : CommandExitCode::FAILURE;
    }

    /**
     * @param array{composer_dump_autoload: array{success: bool, exit_code: int, output: string}, cache_clear: array{success: bool, exit_code: int, output: string}|null} $postTaskResults
     */
    private function postTasksSucceeded(array $postTaskResults): bool
    {
        return $postTaskResults['composer_dump_autoload']['success']
            && ($postTaskResults['cache_clear'] === null || $postTaskResults['cache_clear']['success']);
    }

    private function confirm(string $message): bool
    {
        return (bool) ($this->confirmationPrompt)($message);
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
