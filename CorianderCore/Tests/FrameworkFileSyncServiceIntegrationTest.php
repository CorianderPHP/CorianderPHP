<?php

declare(strict_types=1);

namespace CorianderCore\Tests;

use CorianderCore\Core\Console\Services\Updater\FrameworkFileSyncService;
use PHPUnit\Framework\TestCase;

class FrameworkFileSyncServiceIntegrationTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/coriander-sync-' . bin2hex(random_bytes(6));
        mkdir($this->root, 0775, true);
    }

    protected function tearDown(): void
    {
        $this->deleteDirectory($this->root);
    }

    public function testApplyPlanRollsBackWhenAnOperationFails(): void
    {
        $destination = $this->root . '/CorianderCore/core/test.txt';
        $sourceOk = $this->root . '/source/ok.txt';
        $sourceMissing = $this->root . '/source/missing.txt';

        mkdir(dirname($destination), 0775, true);
        mkdir(dirname($sourceOk), 0775, true);

        file_put_contents($destination, 'original');
        file_put_contents($sourceOk, 'updated');

        $plan = [
            'operations' => [
                [
                    'type' => 'update',
                    'relative_path' => 'CorianderCore/core/test.txt',
                    'source' => $sourceOk,
                    'destination' => $destination,
                ],
                [
                    'type' => 'add',
                    'relative_path' => 'CorianderCore/core/new.txt',
                    'source' => $sourceMissing,
                    'destination' => $this->root . '/CorianderCore/core/new.txt',
                ],
            ],
        ];

        $service = new FrameworkFileSyncService($this->root, ['CorianderCore']);

        try {
            $service->applyPlan($plan, true, true);
            self::fail('Expected rollback exception was not thrown.');
        } catch (\RuntimeException $exception) {
            $this->assertStringContainsString('rolled back', $exception->getMessage());
            $this->assertStringContainsString('Source file missing', $exception->getMessage());
        }

        $this->assertSame('original', (string) file_get_contents($destination));
        $this->assertFileDoesNotExist($this->root . '/CorianderCore/core/new.txt');

        $backupFiles = glob($this->root . '/backups/coriander/CorianderCore/core/test.txt.bak*');
        $this->assertIsArray($backupFiles);
        $this->assertNotEmpty($backupFiles);
    }


    public function testRollbackLatestBackupRestoresLatestScopeFiles(): void
    {
        $destination = $this->root . '/CorianderCore/core/test.txt';
        $addedFile = $this->root . '/CorianderCore/core/new-added.txt';
        mkdir(dirname($destination), 0775, true);
        file_put_contents($destination, 'current');
        file_put_contents($addedFile, 'created-by-update');

        $oldScope = $this->root . '/backups/coriander/v0.1.1-to-v0.1.2/CorianderCore/core';
        $latestScope = $this->root . '/backups/coriander/v0.1.2-to-v0.1.3/CorianderCore/core';
        mkdir($oldScope, 0775, true);
        mkdir($latestScope, 0775, true);

        file_put_contents($oldScope . '/test.txt.bak', 'old-backup');
        file_put_contents($latestScope . '/test.txt.bak', 'latest-backup');
        file_put_contents($latestScope . '/test.txt.bak.1', 'latest-backup-1');
        file_put_contents(
            $this->root . '/backups/coriander/v0.1.2-to-v0.1.3/.rollback-manifest.json',
            (string) json_encode(['added_files' => ['CorianderCore/core/new-added.txt']], JSON_PRETTY_PRINT)
        );

        touch($this->root . '/backups/coriander/v0.1.1-to-v0.1.2', time() - 120);
        touch($this->root . '/backups/coriander/v0.1.2-to-v0.1.3', time() - 60);

        $service = new FrameworkFileSyncService($this->root, ['CorianderCore']);
        $result = $service->rollbackLatestBackup();

        $this->assertSame('v0.1.2-to-v0.1.3', $result['scope']);
        $this->assertSame(2, $result['restored_count']);
        $this->assertSame(['CorianderCore/core/new-added.txt', 'CorianderCore/core/test.txt'], $result['restored_files']);
        $this->assertSame('latest-backup-1', (string) file_get_contents($destination));
        $this->assertFileDoesNotExist($addedFile);
    }

    public function testConstructorRejectsTraversalBackupDirectory(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('path traversal');

        new FrameworkFileSyncService($this->root, ['CorianderCore'], '../outside');
    }

    public function testApplyPlanRejectsTraversalBackupScope(): void
    {
        $destination = $this->root . '/CorianderCore/core/safe.txt';
        $source = $this->root . '/source/safe.txt';

        mkdir(dirname($destination), 0775, true);
        mkdir(dirname($source), 0775, true);

        file_put_contents($destination, 'current');
        file_put_contents($source, 'updated');

        $plan = [
            'operations' => [
                [
                    'type' => 'update',
                    'relative_path' => 'CorianderCore/core/safe.txt',
                    'source' => $source,
                    'destination' => $destination,
                ],
            ],
        ];

        $service = new FrameworkFileSyncService($this->root, ['CorianderCore']);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('path traversal');
        $service->applyPlan($plan, true, true, '../escape');
    }

    public function testRollbackBackupScopeRejectsTraversalScope(): void
    {
        $service = new FrameworkFileSyncService($this->root, ['CorianderCore']);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('path traversal');
        $service->rollbackBackupScope('../escape');
    }

    public function testGitLocalChangesArePreservedUnlessForced(): void
    {
        if (!function_exists('proc_open') || !function_exists('shell_exec')) {
            $this->markTestSkipped('Git subprocess support is required.');
        }
        mkdir($this->root . '/CorianderCore');
        $spacedName = PHP_OS_FAMILY === 'Windows' ? 'space name.txt' : 'space -> name.txt';
        $renamedName = PHP_OS_FAMILY === 'Windows' ? 'renamed destination.txt' : 'renamed -> destination.txt';
        foreach (['00-first.txt', $spacedName, 'deleted.txt', 'old.txt'] as $filename) {
            file_put_contents($this->root . '/CorianderCore/' . $filename, 'original');
        }
        $this->runGit('init', '--quiet');
        $this->runGit('add', 'CorianderCore');
        $this->runGit('-c', 'user.name=Framework Tests', '-c', 'user.email=tests@example.invalid', 'commit', '--quiet', '-m', '🧪 Create test baseline.');
        file_put_contents($this->root . '/CorianderCore/00-first.txt', 'local');
        file_put_contents($this->root . '/CorianderCore/' . $spacedName, 'local');
        unlink($this->root . '/CorianderCore/deleted.txt');
        $this->runGit('mv', 'CorianderCore/old.txt', 'CorianderCore/' . $renamedName);
        file_put_contents($this->root . '/CorianderCore/untracked file.txt', 'local');

        mkdir($this->root . '/download/CorianderCore', 0775, true);
        foreach (['00-first.txt', $spacedName, 'deleted.txt', 'old.txt', $renamedName, 'untracked file.txt'] as $filename) {
            file_put_contents($this->root . '/download/CorianderCore/' . $filename, 'remote');
        }
        $service = new FrameworkFileSyncService($this->root, ['CorianderCore']);
        $plan = $service->buildPlan($this->root . '/download');
        $result = $service->applyPlan($plan);
        $this->assertSame(6, $result['skipped_local_changes_count']);
        foreach (['00-first.txt', $spacedName, 'untracked file.txt'] as $filename) {
            $this->assertSame('local', file_get_contents($this->root . '/CorianderCore/' . $filename));
        }
        $this->assertSame('original', file_get_contents($this->root . '/CorianderCore/' . $renamedName));
        $this->assertFileDoesNotExist($this->root . '/CorianderCore/old.txt');
        $this->assertFileDoesNotExist($this->root . '/CorianderCore/deleted.txt');

        $forced = $service->applyPlan($plan, true);
        $this->assertSame(0, $forced['skipped_local_changes_count']);
        foreach ($plan['operations'] as $operation) {
            $this->assertSame('remote', file_get_contents($operation['destination']));
        }
    }

    private function runGit(string ...$args): void
    {
        $process = proc_open(array_merge(['git', '-C', $this->root], $args), [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
        if (!is_resource($process)) {
            throw new \RuntimeException('Unable to start test Git process.');
        }
        $output = stream_get_contents($pipes[1]) . stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $this->assertSame(0, proc_close($process), $output);
    }

    private function deleteDirectory(string $directory): void
    {
        if (!is_dir($directory)) {
            return;
        }

        $items = scandir($directory);
        if ($items === false) {
            return;
        }

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $path = $directory . DIRECTORY_SEPARATOR . $item;
            if (is_dir($path)) {
                $this->deleteDirectory($path);
            } elseif (file_exists($path)) {
                @unlink($path);
            }
        }

        @rmdir($directory);
    }
}
