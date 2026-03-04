<?php
declare(strict_types=1);

namespace CorianderCore\Core\Console\Services\Updater;

class FrameworkUpdateService
{
    private FrameworkVersionService $versionService;
    private GitHubReleaseService $releaseService;
    private ZipArchiveService $archiveService;
    private FrameworkFileSyncService $fileSyncService;

    public function __construct(
        ?FrameworkVersionService $versionService = null,
        ?GitHubReleaseService $releaseService = null,
        ?ZipArchiveService $archiveService = null,
        ?FrameworkFileSyncService $fileSyncService = null
    ) {
        $this->versionService = $versionService ?? new FrameworkVersionService();
        $this->releaseService = $releaseService ?? new GitHubReleaseService();
        $this->archiveService = $archiveService ?? new ZipArchiveService();
        $this->fileSyncService = $fileSyncService ?? new FrameworkFileSyncService();
    }

    public function getLocalVersion(): string
    {
        return $this->versionService->getLocalVersion();
    }

    /**
     * @return array{tag:string, zip_url:string}
     */
    public function fetchLatestRelease(): array
    {
        return $this->releaseService->fetchLatestRelease();
    }

    public function isUpdateAvailable(string $localVersion, string $latestVersion): bool
    {
        return $this->versionService->isUpdateAvailable($localVersion, $latestVersion);
    }

    /**
     * @return array{operations: array<int, array{type:string,relative_path:string,source:string,destination:string}>, add_count:int, update_count:int, unchanged_count:int, missing_paths: string[], applied_add_count:int, applied_update_count:int, skipped_local_changes_count:int, skipped_local_changes: string[], backup_count:int, backups: string[]}
     */
    public function runUpdate(string $zipUrl, bool $dryRun = false, bool $force = false, bool $createBackups = true): array
    {
        $tempDirectory = $this->createTempDirectory();
        $archivePath = $tempDirectory . '/framework.zip';
        $extractPath = $tempDirectory . '/extract';

        try {
            $this->releaseService->downloadArchive($zipUrl, $archivePath);
            $sourceRoot = $this->archiveService->extract($archivePath, $extractPath);
            $plan = $this->fileSyncService->buildPlan($sourceRoot);

            $applyResult = [
                'applied_add_count' => 0,
                'applied_update_count' => 0,
                'skipped_local_changes_count' => 0,
                'skipped_local_changes' => [],
                'backup_count' => 0,
                'backups' => [],
            ];

            if (!$dryRun) {
                $applyResult = $this->fileSyncService->applyPlan($plan, $force, $createBackups);
            }

            return array_merge($plan, $applyResult);
        } finally {
            $this->deleteDirectory($tempDirectory);
        }
    }

    private function createTempDirectory(): string
    {
        $base = rtrim(sys_get_temp_dir(), '\\/');
        $path = $base . '/coriander-update-' . bin2hex(random_bytes(8));
        if (!mkdir($path, 0775, true) && !is_dir($path)) {
            throw new \RuntimeException('Unable to create temporary directory for update process.');
        }

        return $path;
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
