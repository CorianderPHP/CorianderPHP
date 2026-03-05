<?php

declare(strict_types=1);

namespace CorianderCore\Tests;

use CorianderCore\Core\Console\Commands\Update;
use CorianderCore\Core\Console\Services\Updater\FrameworkUpdateService;
use CorianderCore\Core\Console\Services\Updater\PostUpdateTasksService;
use PHPUnit\Framework\TestCase;

class UpdateCommandTest extends TestCase
{
    protected function setUp(): void
    {
        putenv('APP_ENV=development');
        putenv('CORIANDER_UPDATER_ALLOW_PRODUCTION=1');
        putenv('CORIANDER_UPDATER_MAX_ATTEMPTS_PER_HOUR=0');
        putenv('CORIANDER_UPDATER_RATE_LIMIT_FILE=cache/_tmp_update_rate_limit.json');
    }

    protected function tearDown(): void
    {
        putenv('APP_ENV');
        putenv('CORIANDER_UPDATER_ALLOW_PRODUCTION');
        putenv('CORIANDER_UPDATER_MAX_ATTEMPTS_PER_HOUR');
        putenv('CORIANDER_UPDATER_RATE_LIMIT_FILE');

        $rateLimitFile = PROJECT_ROOT . '/cache/_tmp_update_rate_limit.json';
        if (file_exists($rateLimitFile)) {
            unlink($rateLimitFile);
        }
    }

    public function testDryRunExecutesWithoutPromptAndWithoutPostTasks(): void
    {
        $service = new FakeFrameworkUpdateService();
        $postTasks = new FakePostUpdateTasksService();
        $command = new Update($service, static function (): bool {
            throw new \RuntimeException('Prompt should not be called during dry run.');
        }, $postTasks);

        ob_start();
        $command->execute(['--dry-run']);
        $output = (string) ob_get_clean();

        $this->assertTrue($service->runUpdateCalled);
        $this->assertTrue($service->lastDryRunFlag);
        $this->assertFalse($postTasks->runCalled);
        $this->assertStringContainsString('No changes applied (--dry-run).', $output);
    }

    public function testInteractiveCancelSkipsUpdate(): void
    {
        $service = new FakeFrameworkUpdateService();
        $postTasks = new FakePostUpdateTasksService();
        $command = new Update($service, static fn(string $message): bool => false, $postTasks);

        ob_start();
        $command->execute([]);
        $output = (string) ob_get_clean();

        $this->assertFalse($service->runUpdateCalled);
        $this->assertFalse($postTasks->runCalled);
        $this->assertStringContainsString('Update cancelled.', $output);
    }

    public function testYesFlagAppliesUpdateWithoutPromptAndRunsPostTasks(): void
    {
        $service = new FakeFrameworkUpdateService();
        $postTasks = new FakePostUpdateTasksService();
        $command = new Update($service, static function (): bool {
            throw new \RuntimeException('Prompt should not be called with --yes.');
        }, $postTasks);

        ob_start();
        $command->execute(['--yes']);
        $output = (string) ob_get_clean();

        $this->assertTrue($service->runUpdateCalled);
        $this->assertFalse($service->lastDryRunFlag);
        $this->assertTrue($postTasks->runCalled);
        $this->assertFalse($postTasks->clearCacheFlag);
        $this->assertStringContainsString('Framework update completed successfully.', $output);
    }

    public function testBackupDirectoryOptionIsForwardedToUpdateService(): void
    {
        $service = new FakeFrameworkUpdateService();
        $postTasks = new FakePostUpdateTasksService();
        $command = new Update($service, static fn(string $message): bool => true, $postTasks);

        ob_start();
        $command->execute(['--yes', '--backup-dir=backups/custom']);
        ob_end_clean();

        $this->assertSame('backups/custom', $service->lastBackupDirectory);
        $this->assertSame('v0.1.0-to-v0.2.0', $service->lastBackupScope);
    }

    public function testRollbackFlagRestoresFromLatestBackup(): void
    {
        $service = new FakeFrameworkUpdateService();
        $postTasks = new FakePostUpdateTasksService();
        $command = new Update($service, static fn(string $message): bool => true, $postTasks);

        ob_start();
        $command->execute(['--rollback', '--yes']);
        $output = (string) ob_get_clean();

        $this->assertTrue($service->rollbackCalled);
        $this->assertFalse($service->runUpdateCalled);
        $this->assertTrue($postTasks->runCalled);
        $this->assertStringContainsString('Rollback completed successfully.', $output);
    }

    public function testClearCacheFlagRunsOptionalCacheTask(): void
    {
        $service = new FakeFrameworkUpdateService();
        $postTasks = new FakePostUpdateTasksService();
        $command = new Update($service, static fn(string $message): bool => true, $postTasks);

        ob_start();
        $command->execute(['--clear-cache']);
        ob_end_clean();

        $this->assertTrue($postTasks->runCalled);
        $this->assertTrue($postTasks->clearCacheFlag);
    }
}

class FakeFrameworkUpdateService extends FrameworkUpdateService
{
    public bool $runUpdateCalled = false;
    public bool $rollbackCalled = false;
    public bool $lastDryRunFlag = false;
    public ?string $lastBackupScope = null;
    public ?string $lastBackupDirectory = null;

    public function __construct()
    {
    }

    public function getLocalVersion(): string
    {
        return 'v0.1.0';
    }

    public function fetchLatestRelease(): array
    {
        return [
            'tag' => 'v0.2.0',
            'zip_url' => 'https://example.com/fake.zip',
        ];
    }

    public function isUpdateAvailable(string $localVersion, string $latestVersion): bool
    {
        return true;
    }


    public function rollbackLatestBackup(?string $backupDirectory = null): array
    {
        $this->rollbackCalled = true;
        $this->lastBackupDirectory = $backupDirectory;

        return [
            'scope' => 'v0.1.2-to-v0.1.3',
            'restored_count' => 1,
            'restored_files' => ['CorianderCore/autoload.php'],
        ];
    }

    public function runUpdate(string $zipUrl, bool $dryRun = false, bool $force = false, bool $createBackups = true, ?string $backupScope = null, ?string $backupDirectory = null): array
    {
        $this->runUpdateCalled = true;
        $this->lastDryRunFlag = $dryRun;
        $this->lastBackupScope = $backupScope;
        $this->lastBackupDirectory = $backupDirectory;

        return [
            'operations' => [
                ['type' => 'update', 'relative_path' => 'CorianderCore/autoload.php', 'source' => 's', 'destination' => 'd'],
            ],
            'add_count' => 0,
            'update_count' => 1,
            'unchanged_count' => 0,
            'missing_paths' => [],
            'applied_add_count' => $dryRun ? 0 : 0,
            'applied_update_count' => $dryRun ? 0 : 1,
            'skipped_local_changes_count' => 0,
            'skipped_local_changes' => [],
            'backup_count' => $dryRun ? 0 : 1,
            'backups' => $dryRun ? [] : ['backups/coriander/v0.1.0-to-v0.2.0/CorianderCore/autoload.php.bak'],
        ];
    }
}

class FakePostUpdateTasksService extends PostUpdateTasksService
{
    public bool $runCalled = false;
    public bool $clearCacheFlag = false;

    public function run(bool $clearCache): array
    {
        $this->runCalled = true;
        $this->clearCacheFlag = $clearCache;

        return [
            'composer_dump_autoload' => [
                'success' => true,
                'exit_code' => 0,
                'output' => '',
            ],
            'cache_clear' => $clearCache
                ? [
                    'success' => true,
                    'exit_code' => 0,
                    'output' => '',
                ]
                : null,
        ];
    }
}

