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

        $backupFiles = glob($destination . '.bak.*');
        $this->assertIsArray($backupFiles);
        $this->assertNotEmpty($backupFiles);
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
