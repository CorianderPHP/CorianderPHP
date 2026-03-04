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

    /**
     * @var array<string, bool>|null
     */
    private ?array $modifiedPathsIndex = null;

    /**
     * @param string[] $managedPaths
     */
    public function __construct(?string $projectRoot = null, array $managedPaths = ['CorianderCore', 'coriander'])
    {
        $this->projectRoot = $projectRoot ?? (defined('PROJECT_ROOT') ? PROJECT_ROOT : dirname(__DIR__, 6));
        $this->managedPaths = $managedPaths;
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
    public function applyPlan(array $plan, bool $force = false, bool $createBackups = true): array
    {
        $appliedAddCount = 0;
        $appliedUpdateCount = 0;
        $skippedLocalChanges = [];
        $backupRelativePaths = [];

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
                    $backupAbsolutePath = $this->createBackup($operation['destination']);
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

    private function createBackup(string $destinationFile): string
    {
        $backupPath = $destinationFile . '.bak.' . date('YmdHis');
        $suffix = 0;
        while (file_exists($backupPath)) {
            $suffix++;
            $backupPath = $destinationFile . '.bak.' . date('YmdHis') . '.' . $suffix;
        }

        if (!copy($destinationFile, $backupPath)) {
            throw new RuntimeException('Failed to create backup file: ' . $destinationFile);
        }

        return $backupPath;
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
