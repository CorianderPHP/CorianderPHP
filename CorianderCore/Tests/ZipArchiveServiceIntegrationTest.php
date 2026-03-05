<?php

declare(strict_types=1);

namespace CorianderCore\Tests;

use CorianderCore\Core\Console\Services\Updater\ZipArchiveService;
use PHPUnit\Framework\TestCase;
use ZipArchive;

class ZipArchiveServiceIntegrationTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/coriander-zip-' . bin2hex(random_bytes(6));
        mkdir($this->root, 0775, true);
    }

    protected function tearDown(): void
    {
        $this->deleteDirectory($this->root);
    }

    public function testExtractReturnsReleaseRootDirectory(): void
    {
        if (!class_exists(ZipArchive::class)) {
            self::markTestSkipped('ZipArchive extension is required.');
        }

        $archivePath = $this->root . '/release.zip';
        $extractPath = $this->root . '/extract';

        $zip = new ZipArchive();
        $zip->open($archivePath, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        $zip->addFromString('release-root/CorianderCore/core/file.txt', 'data');
        $zip->close();

        $service = new ZipArchiveService();
        $sourceRoot = $service->extract($archivePath, $extractPath);

        $this->assertDirectoryExists($sourceRoot);
        $this->assertFileExists($sourceRoot . '/CorianderCore/core/file.txt');
    }


    public function testExtractRejectsUnsafeTraversalEntry(): void
    {
        if (!class_exists(ZipArchive::class)) {
            self::markTestSkipped('ZipArchive extension is required.');
        }

        $archivePath = $this->root . '/unsafe.zip';
        $extractPath = $this->root . '/extract-unsafe';

        $zip = new ZipArchive();
        $zip->open($archivePath, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        $zip->addFromString('../escape.txt', 'data');
        $zip->close();

        $service = new ZipArchiveService();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('unsafe path traversal');
        $service->extract($archivePath, $extractPath);
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
