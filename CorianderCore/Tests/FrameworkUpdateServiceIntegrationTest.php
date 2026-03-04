<?php

declare(strict_types=1);

namespace CorianderCore\Tests;

use CorianderCore\Core\Console\Services\Updater\FrameworkFileSyncService;
use CorianderCore\Core\Console\Services\Updater\FrameworkUpdateService;
use CorianderCore\Core\Console\Services\Updater\FrameworkVersionService;
use CorianderCore\Core\Console\Services\Updater\GitHubReleaseService;
use CorianderCore\Core\Console\Services\Updater\ZipArchiveService;
use PHPUnit\Framework\TestCase;
use ZipArchive;

class FrameworkUpdateServiceIntegrationTest extends TestCase
{
    private string $projectRoot;
    private string $fixtureRoot;

    protected function setUp(): void
    {
        $this->projectRoot = sys_get_temp_dir() . '/coriander-update-project-' . bin2hex(random_bytes(6));
        $this->fixtureRoot = sys_get_temp_dir() . '/coriander-update-fixture-' . bin2hex(random_bytes(6));

        mkdir($this->projectRoot, 0775, true);
        mkdir($this->fixtureRoot, 0775, true);
    }

    protected function tearDown(): void
    {
        $this->deleteDirectory($this->projectRoot);
        $this->deleteDirectory($this->fixtureRoot);
    }

    public function testRunUpdateAppliesManagedFilesAndCreatesBackups(): void
    {
        if (!class_exists(ZipArchive::class)) {
            self::markTestSkipped('ZipArchive extension is required.');
        }

        $localCoreFile = $this->projectRoot . '/CorianderCore/core/example.txt';
        $localCliFile = $this->projectRoot . '/coriander';
        mkdir(dirname($localCoreFile), 0775, true);
        file_put_contents($localCoreFile, 'local-core');
        file_put_contents($localCliFile, 'local-cli');

        $archivePath = $this->fixtureRoot . '/framework.zip';
        $zip = new ZipArchive();
        $zip->open($archivePath, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        $zip->addFromString('release/CorianderCore/core/example.txt', 'new-core');
        $zip->addFromString('release/coriander', 'new-cli');
        $zip->close();

        $releaseService = new LocalArchiveReleaseService($archivePath);
        $versionService = new FrameworkVersionService($this->projectRoot, 'CorianderCore/VERSION');
        $updateService = new FrameworkUpdateService(
            $versionService,
            $releaseService,
            new ZipArchiveService(),
            new FrameworkFileSyncService($this->projectRoot, ['CorianderCore', 'coriander'])
        );

        $result = $updateService->runUpdate('local://archive', false, true, true);

        $this->assertSame('new-core', (string) file_get_contents($localCoreFile));
        $this->assertSame('new-cli', (string) file_get_contents($localCliFile));
        $this->assertSame(2, $result['applied_update_count']);
        $this->assertSame(2, $result['backup_count']);

        foreach ($result['backups'] as $backupRelativePath) {
            $this->assertFileExists($this->projectRoot . '/' . $backupRelativePath);
        }
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

class LocalArchiveReleaseService extends GitHubReleaseService
{
    private string $archivePath;

    public function __construct(string $archivePath)
    {
        $this->archivePath = $archivePath;
        parent::__construct('CorianderPHP/CorianderPHP');
    }

    public function downloadArchive(string $url, string $destination): void
    {
        copy($this->archivePath, $destination);
    }
}
