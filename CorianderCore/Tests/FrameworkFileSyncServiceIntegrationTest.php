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


