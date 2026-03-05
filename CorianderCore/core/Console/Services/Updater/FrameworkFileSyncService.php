<?php
declare(strict_types=1);

namespace CorianderCore\Core\Console\Services\Updater;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;

final class FrameworkFileSyncService
{
    private string $projectRoot;

    /**
     * @var string[]
     */
    private array $managedPaths;

    private string $defaultBackupDirectory;

    /**
     * @var array<string, bool>|null
     */
    private ?array $modifiedPathsIndex = null;

    /**
     * @param string[] $managedPaths
     */
    public function __construct(?string $projectRoot = null, array $managedPaths = ['CorianderCore', 'coriander'], ?string $defaultBackupDirectory = null)
    {
        $this->projectRoot = $projectRoot ?? (defined('PROJECT_ROOT') ? PROJECT_ROOT : dirname(__DIR__, 6));
        $this->managedPaths = $managedPaths;
        $this->defaultBackupDirectory = $this->normalizeBackupDirectory($defaultBackupDirectory ?? $this->resolveDefaultBackupDirectory());
    }

    /**
     * @return array{operations: array<int, array{type:string,relative_path:string,source:string,destination:string}>, add_count:int, update_count:int, unchanged_count:int, missing_paths: string[]}
     */
    public function buildPlan(string $sourceRoot): array
    {
        $operations = [];
        $addCount = 0;
        $updateCount = 0;
        $unchangedCount = 0;
        $missingPaths = [];

        foreach ($this->managedPaths as $managedPath) {
            $sourcePath = $sourceRoot . '/' . $managedPath;
            if (!file_exists($sourcePath)) {
                $missingPaths[] = $managedPath;
                continue;
            }

            if (is_file($sourcePath)) {
                $relativePath = str_replace('\\', '/', $managedPath);
                $destination = $this->projectRoot . '/' . $relativePath;
                $type = $this->determineOperationType($sourcePath, $destination, $unchangedCount);
                if ($type !== null) {
                    $operations[] = [
                        'type' => $type,
                        'relative_path' => $relativePath,
                        'source' => $sourcePath,
                        'destination' => $destination,
                    ];
                    $type === 'add' ? $addCount++ : $updateCount++;
                }
                continue;
            }

            $files = $this->collectFiles($sourcePath);
            foreach ($files as $sourceFile) {
                $relativePath = str_replace('\\', '/', substr($sourceFile, strlen($sourceRoot) + 1));
                $destination = $this->projectRoot . '/' . $relativePath;

                $type = $this->determineOperationType($sourceFile, $destination, $unchangedCount);
                if ($type === null) {
                    continue;
                }

                $operations[] = [
                    'type' => $type,
                    'relative_path' => $relativePath,
                    'source' => $sourceFile,
                    'destination' => $destination,
                ];
                $type === 'add' ? $addCount++ : $updateCount++;
            }
        }

        usort($operations, static fn(array $left, array $right): int => strcmp($left['relative_path'], $right['relative_path']));

        return [
            'operations' => $operations,
            'add_count' => $addCount,
            'update_count' => $updateCount,
            'unchanged_count' => $unchangedCount,
            'missing_paths' => $missingPaths,
        ];
    }

    /**
     * @param array{operations: array<int, array{type:string,relative_path:string,source:string,destination:string}>} $plan
     * @return array{applied_add_count:int, applied_update_count:int, skipped_local_changes_count:int, skipped_local_changes: string[], backup_count:int, backups: string[]}
     */
    public function applyPlan(array $plan, bool $force = false, bool $createBackups = true, ?string $backupScope = null, ?string $backupDirectory = null): array
    {
        $appliedAddCount = 0;
        $appliedUpdateCount = 0;
        $skippedLocalChanges = [];
        $backupRelativePaths = [];
        $appliedAddPaths = [];

        /**
         * @var array<int, array{type:string,destination:string,backup_absolute:string|null}>
         */
        $appliedOperations = [];

        try {
            foreach ($plan['operations'] as $operation) {
                if ($operation['type'] === 'update' && !$force && $this->isLocallyModified($operation['relative_path'])) {
                    $skippedLocalChanges[] = $operation['relative_path'];
                    continue;
                }

                $backupAbsolutePath = null;
                if ($operation['type'] === 'update' && $createBackups && file_exists($operation['destination'])) {
                    $backupAbsolutePath = $this->createBackup($operation['destination'], $backupScope, $backupDirectory);
                    $backupRelativePaths[] = $this->toRelativeProjectPath($backupAbsolutePath);
                }

                $directory = dirname($operation['destination']);
                if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
                    throw new RuntimeException('Unable to create destination directory: ' . $directory);
                }

                if (!is_file($operation['source'])) {
                    throw new RuntimeException('Source file missing for update operation: ' . $operation['relative_path']);
                }

                if (!copy($operation['source'], $operation['destination'])) {
                    throw new RuntimeException('Failed to write file: ' . $operation['relative_path']);
                }

                $appliedOperations[] = [
                    'type' => $operation['type'],
                    'destination' => $operation['destination'],
                    'backup_absolute' => $backupAbsolutePath,
                ];

                if ($operation['type'] === 'add') {
                    $appliedAddCount++;
                    $appliedAddPaths[] = $operation['relative_path'];
                } else {
                    $appliedUpdateCount++;
                }
            }
        } catch (RuntimeException $exception) {
            $rollbackError = $this->rollbackAppliedOperations($appliedOperations);
            $message = 'Update failed and was rolled back: ' . $exception->getMessage();
            if ($rollbackError !== null) {
                $message .= ' | Rollback issue: ' . $rollbackError;
            }
            throw new RuntimeException($message, 0, $exception);
        }

        if ($createBackups && $backupScope !== null && trim($backupScope) !== '') {
            $this->writeRollbackManifest($backupScope, $backupDirectory, $appliedAddPaths);
        }

        return [
            'applied_add_count' => $appliedAddCount,
            'applied_update_count' => $appliedUpdateCount,
            'skipped_local_changes_count' => count($skippedLocalChanges),
            'skipped_local_changes' => $skippedLocalChanges,
            'backup_count' => count($backupRelativePaths),
            'backups' => $backupRelativePaths,
        ];
    }

    /**
     * @return array{scope:string, restored_count:int, restored_files:string[]}
     */
    public function rollbackLatestBackup(?string $backupDirectory = null): array
    {
        $baseDirectory = $this->normalizeBackupDirectory($backupDirectory ?? $this->defaultBackupDirectory);
        $basePath = $this->projectRoot . '/' . $baseDirectory;

        if (!is_dir($basePath)) {
            throw new RuntimeException('No backup directory found: ' . $baseDirectory);
        }

        $latestScope = $this->findLatestBackupScope($basePath);
        if ($latestScope === null) {
            throw new RuntimeException('No backup scope found in: ' . $baseDirectory);
        }

        return $this->rollbackBackupScope($latestScope, $backupDirectory);
    }

    /**
     * @return array{scope:string, restored_count:int, restored_files:string[]}
     */
    public function rollbackBackupScope(string $scope, ?string $backupDirectory = null): array
    {
        $scope = trim(str_replace('\\', '/', $scope), '/');
        if ($scope === '') {
            throw new RuntimeException('Backup scope cannot be empty.');
        }

        $scopePath = $this->resolveBackupScopePath($scope, $backupDirectory);
        if (!is_dir($scopePath)) {
            throw new RuntimeException('Backup scope not found: ' . $scope);
        }

        $backupCandidates = $this->collectBackupCandidates($scopePath);
        if ($backupCandidates === []) {
            throw new RuntimeException('No backup files found in scope: ' . $scope);
        }

        $manifest = $this->readRollbackManifest($scopePath);
        $addedFiles = isset($manifest['added_files']) && is_array($manifest['added_files'])
            ? array_values(array_filter($manifest['added_files'], 'is_string'))
            : [];

        $result = $this->runAtomicRollback($backupCandidates, $addedFiles);
        $result['scope'] = $scope;

        return $result;
    }
    /**
     * @return string[]
     */
    private function collectFiles(string $directory): array
    {
        $files = [];
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($directory, RecursiveDirectoryIterator::SKIP_DOTS)
        );

        foreach ($iterator as $item) {
            if ($item->isFile()) {
                $files[] = $item->getPathname();
            }
        }

        return $files;
    }

    private function determineOperationType(string $source, string $destination, int &$unchangedCount): ?string
    {
        if (!file_exists($destination)) {
            return 'add';
        }

        $sourceHash = @hash_file('sha256', $source);
        $destinationHash = @hash_file('sha256', $destination);
        if ($sourceHash !== false && $destinationHash !== false && $sourceHash === $destinationHash) {
            $unchangedCount++;
            return null;
        }

        return 'update';
    }

    private function isLocallyModified(string $relativePath): bool
    {
        $index = $this->getModifiedPathsIndex();
        return isset($index[$relativePath]);
    }

    /**
     * @return array<string, bool>
     */
    private function getModifiedPathsIndex(): array
    {
        if ($this->modifiedPathsIndex !== null) {
            return $this->modifiedPathsIndex;
        }

        $this->modifiedPathsIndex = [];
        if (!function_exists('shell_exec')) {
            return $this->modifiedPathsIndex;
        }

        $root = escapeshellarg($this->projectRoot);
        $paths = implode(' ', array_map(static fn(string $path): string => escapeshellarg($path), $this->managedPaths));
        $command = "git -C {$root} status --porcelain -- {$paths}";
        $output = shell_exec($command);
        if (!is_string($output) || trim($output) === '') {
            return $this->modifiedPathsIndex;
        }

        $lines = preg_split('/\r\n|\r|\n/', trim($output));
        if (!is_array($lines)) {
            return $this->modifiedPathsIndex;
        }

        foreach ($lines as $line) {
            if (strlen($line) < 4) {
                continue;
            }

            $path = trim(substr($line, 3));
            if ($path === '') {
                continue;
            }

            if (str_contains($path, ' -> ')) {
                $parts = explode(' -> ', $path);
                $path = trim((string) end($parts));
            }

            $normalizedPath = str_replace('\\', '/', $path);
            $this->modifiedPathsIndex[$normalizedPath] = true;
        }

        return $this->modifiedPathsIndex;
    }

    private function createBackup(string $destinationFile, ?string $backupScope = null, ?string $backupDirectory = null): string
    {
        $relativePath = $this->toRelativeProjectPath($destinationFile);
        $baseDirectory = $this->normalizeBackupDirectory($backupDirectory ?? $this->defaultBackupDirectory);
        $scopeSegment = $backupScope !== null && trim($backupScope) !== ''
            ? '/' . trim(str_replace('\\', '/', $backupScope), '/')
            : '';

        $backupPath = $this->projectRoot . '/' . $baseDirectory . $scopeSegment . '/' . $relativePath . '.bak';
        $suffix = 0;
        while (file_exists($backupPath)) {
            $suffix++;
            $backupPath = $this->projectRoot . '/' . $baseDirectory . $scopeSegment . '/' . $relativePath . '.bak.' . $suffix;
        }

        $directory = dirname($backupPath);
        if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
            throw new RuntimeException('Unable to create backup directory: ' . $directory);
        }

        if (!copy($destinationFile, $backupPath)) {
            throw new RuntimeException('Failed to create backup file: ' . $relativePath);
        }

        return $backupPath;
    }

    private function findLatestBackupScope(string $basePath): ?string
    {
        $entries = scandir($basePath);
        if ($entries === false) {
            return null;
        }

        $latestScope = null;
        $latestMtime = -1;

        foreach ($entries as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }

            $candidatePath = $basePath . '/' . $entry;
            if (!is_dir($candidatePath)) {
                continue;
            }

            $mtime = @filemtime($candidatePath);
            if ($mtime === false) {
                continue;
            }

            if ($mtime > $latestMtime) {
                $latestMtime = $mtime;
                $latestScope = $entry;
            }
        }

        return $latestScope;
    }

    /**
     * @return array<string, string>
     */
    private function collectBackupCandidates(string $scopePath): array
    {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($scopePath, RecursiveDirectoryIterator::SKIP_DOTS)
        );

        $candidates = [];

        foreach ($iterator as $item) {
            if (!$item->isFile()) {
                continue;
            }

            $relativeBackupPath = str_replace('\\', '/', substr($item->getPathname(), strlen($scopePath) + 1));
            if (!preg_match('/^(.*)\.bak(?:\.(\d+))?$/', $relativeBackupPath, $matches)) {
                continue;
            }

            $targetRelativePath = $matches[1];
            $suffix = isset($matches[2]) ? (int) $matches[2] : 0;

            if (!isset($candidates[$targetRelativePath]) || $suffix > $candidates[$targetRelativePath]['suffix']) {
                $candidates[$targetRelativePath] = [
                    'suffix' => $suffix,
                    'path' => $item->getPathname(),
                ];
            }
        }

        $resolved = [];
        foreach ($candidates as $targetRelativePath => $candidate) {
            $resolved[$targetRelativePath] = $candidate['path'];
        }

        ksort($resolved);

        return $resolved;
    }
    /**
     * @param string[] $addedFiles
     * @param array<string, string> $backupCandidates
     * @return array{restored_count:int, restored_files:string[]}
     */
    private function runAtomicRollback(array $backupCandidates, array $addedFiles): array
    {
        $deleteTargets = [];
        foreach ($addedFiles as $addedFile) {
            $normalized = trim(str_replace('\\', '/', $addedFile), '/');
            if ($normalized === '' || isset($backupCandidates[$normalized])) {
                continue;
            }
            $deleteTargets[] = $normalized;
        }

        $deleteTargets = array_values(array_unique($deleteTargets));

        $snapshotDirectory = rtrim(sys_get_temp_dir(), '\\/') . '/coriander-rollback-' . bin2hex(random_bytes(8));
        if (!mkdir($snapshotDirectory, 0775, true) && !is_dir($snapshotDirectory)) {
            throw new RuntimeException('Unable to create rollback snapshot directory.');
        }

        $snapshots = [];

        try {
            $targets = array_values(array_unique(array_merge(array_keys($backupCandidates), $deleteTargets)));
            sort($targets);

            foreach ($targets as $targetRelativePath) {
                $destination = $this->projectRoot . '/' . $targetRelativePath;
                if (!is_file($destination)) {
                    continue;
                }

                $snapshotPath = $snapshotDirectory . '/' . $targetRelativePath;
                $snapshotDir = dirname($snapshotPath);
                if (!is_dir($snapshotDir) && !mkdir($snapshotDir, 0775, true) && !is_dir($snapshotDir)) {
                    throw new RuntimeException('Unable to create rollback snapshot directory: ' . $snapshotDir);
                }

                if (!copy($destination, $snapshotPath)) {
                    throw new RuntimeException('Unable to snapshot file before rollback: ' . $targetRelativePath);
                }

                $snapshots[$targetRelativePath] = $snapshotPath;
            }

            $restoredFiles = [];
            foreach ($backupCandidates as $targetRelativePath => $backupFile) {
                $destination = $this->projectRoot . '/' . $targetRelativePath;
                $destinationDirectory = dirname($destination);
                if (!is_dir($destinationDirectory) && !mkdir($destinationDirectory, 0775, true) && !is_dir($destinationDirectory)) {
                    throw new RuntimeException('Unable to create destination directory: ' . $destinationDirectory);
                }

                if (!copy($backupFile, $destination)) {
                    throw new RuntimeException('Failed to restore backup file: ' . $targetRelativePath);
                }

                $restoredFiles[] = $targetRelativePath;
            }

            foreach ($deleteTargets as $targetRelativePath) {
                $destination = $this->projectRoot . '/' . $targetRelativePath;
                if (is_file($destination) && !@unlink($destination)) {
                    throw new RuntimeException('Failed to remove file added by update: ' . $targetRelativePath);
                }

                if (!in_array($targetRelativePath, $restoredFiles, true)) {
                    $restoredFiles[] = $targetRelativePath;
                }
            }

            sort($restoredFiles);

            return [
                'restored_count' => count($restoredFiles),
                'restored_files' => $restoredFiles,
            ];
        } catch (RuntimeException $exception) {
            foreach ($snapshots as $targetRelativePath => $snapshotPath) {
                $destination = $this->projectRoot . '/' . $targetRelativePath;
                $destinationDirectory = dirname($destination);
                if (!is_dir($destinationDirectory)) {
                    @mkdir($destinationDirectory, 0775, true);
                }
                @copy($snapshotPath, $destination);
            }

            foreach ($deleteTargets as $targetRelativePath) {
                if (isset($snapshots[$targetRelativePath])) {
                    continue;
                }
                $destination = $this->projectRoot . '/' . $targetRelativePath;
                if (is_file($destination)) {
                    @unlink($destination);
                }
            }

            throw new RuntimeException('Rollback failed and was reverted: ' . $exception->getMessage(), 0, $exception);
        } finally {
            $this->deleteTemporaryDirectory($snapshotDirectory);
        }
    }

    /**
     * @return array{added_files:string[]}|null
     */
    private function readRollbackManifest(string $scopePath): ?array
    {
        $manifestPath = $scopePath . '/.rollback-manifest.json';
        if (!is_file($manifestPath)) {
            return null;
        }

        $decoded = json_decode((string) file_get_contents($manifestPath), true);
        return is_array($decoded) ? $decoded : null;
    }

    /**
     * @param string[] $addedFiles
     */
    private function writeRollbackManifest(string $scope, ?string $backupDirectory, array $addedFiles): void
    {
        $scopePath = $this->resolveBackupScopePath($scope, $backupDirectory);
        if (!is_dir($scopePath) && !mkdir($scopePath, 0775, true) && !is_dir($scopePath)) {
            throw new RuntimeException('Unable to create backup scope directory: ' . $scopePath);
        }

        $manifestPath = $scopePath . '/.rollback-manifest.json';
        $payload = [
            'added_files' => array_values(array_unique($addedFiles)),
            'generated_at' => date(DATE_ATOM),
        ];

        $json = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        if (!is_string($json) || @file_put_contents($manifestPath, $json) === false) {
            throw new RuntimeException('Unable to write rollback manifest.');
        }
    }

    private function resolveBackupScopePath(string $scope, ?string $backupDirectory = null): string
    {
        $baseDirectory = $this->normalizeBackupDirectory($backupDirectory ?? $this->defaultBackupDirectory);
        $normalizedScope = trim(str_replace('\\', '/', $scope), '/');
        return $this->projectRoot . '/' . $baseDirectory . '/' . $normalizedScope;
    }
    private function deleteTemporaryDirectory(string $directory): void
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
                $this->deleteTemporaryDirectory($path);
            } elseif (file_exists($path)) {
                @unlink($path);
            }
        }

        @rmdir($directory);
    }
    private function resolveDefaultBackupDirectory(): string
    {
        if (defined('CORIANDER_UPDATE_BACKUP_DIR') && is_string(CORIANDER_UPDATE_BACKUP_DIR) && trim(CORIANDER_UPDATE_BACKUP_DIR) !== '') {
            return CORIANDER_UPDATE_BACKUP_DIR;
        }

        return 'backups/coriander';
    }

    private function normalizeBackupDirectory(string $backupDirectory): string
    {
        $normalized = trim(str_replace('\\', '/', $backupDirectory), '/');
        return $normalized !== '' ? $normalized : 'backups/coriander';
    }

    /**
     * @param array<int, array{type:string,destination:string,backup_absolute:string|null}> $appliedOperations
     */
    private function rollbackAppliedOperations(array $appliedOperations): ?string
    {
        $restoreFailures = [];

        for ($index = count($appliedOperations) - 1; $index >= 0; $index--) {
            $operation = $appliedOperations[$index];

            if ($operation['type'] === 'add') {
                if (file_exists($operation['destination']) && !@unlink($operation['destination'])) {
                    $restoreFailures[] = 'Unable to remove added file during rollback: ' . $this->toRelativeProjectPath($operation['destination']);
                }
                continue;
            }

            if ($operation['backup_absolute'] !== null && file_exists($operation['backup_absolute'])) {
                if (!@copy($operation['backup_absolute'], $operation['destination'])) {
                    $restoreFailures[] = 'Unable to restore backup during rollback: ' . $this->toRelativeProjectPath($operation['destination']);
                }
            }
        }

        if ($restoreFailures === []) {
            return null;
        }

        return implode(' | ', $restoreFailures);
    }

    private function toRelativeProjectPath(string $absolutePath): string
    {
        $normalizedRoot = rtrim(str_replace('\\', '/', $this->projectRoot), '/');
        $normalizedAbsolute = str_replace('\\', '/', $absolutePath);
        if (str_starts_with($normalizedAbsolute, $normalizedRoot . '/')) {
            return substr($normalizedAbsolute, strlen($normalizedRoot) + 1);
        }

        return $normalizedAbsolute;
    }
}





