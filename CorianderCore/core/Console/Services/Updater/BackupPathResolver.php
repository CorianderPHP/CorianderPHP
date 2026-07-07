<?php
declare(strict_types=1);

namespace CorianderCore\Core\Console\Services\Updater;

use RuntimeException;

final class BackupPathResolver
{
    private string $projectRoot;

    private string $defaultBackupDirectory;

    public function __construct(string $projectRoot, ?string $defaultBackupDirectory = null)
    {
        $this->projectRoot = rtrim(str_replace('\\', '/', $projectRoot), '/');
        $this->defaultBackupDirectory = $this->normalizeBackupDirectory($defaultBackupDirectory ?? $this->resolveDefaultBackupDirectory());
    }

    public function getDefaultBackupDirectory(): string
    {
        return $this->defaultBackupDirectory;
    }

    public function resolveScopePath(string $scope, ?string $backupDirectory = null): string
    {
        $baseDirectory = $this->normalizeBackupDirectory($backupDirectory ?? $this->defaultBackupDirectory);
        $normalizedScope = $this->normalizeBackupScope($scope);
        if ($normalizedScope === '') {
            throw new RuntimeException('Backup scope cannot be empty.');
        }

        return $this->projectRoot . '/' . $baseDirectory . '/' . $normalizedScope;
    }

    public function normalizeBackupDirectory(string $backupDirectory): string
    {
        if (str_contains($backupDirectory, "\0")) {
            throw new RuntimeException('Backup directory contains invalid null-byte characters.');
        }

        $normalized = str_replace("\\", "/", trim($backupDirectory));
        if ($normalized === '') {
            return 'backups/coriander';
        }

        if (str_starts_with($normalized, '/') || preg_match('/^[a-zA-Z]:\//', $normalized) === 1) {
            throw new RuntimeException('Backup directory must be a relative path inside the project.');
        }

        $segments = array_values(array_filter(explode('/', trim($normalized, '/')), static fn(string $segment): bool => $segment !== ''));
        if ($segments === []) {
            return 'backups/coriander';
        }

        foreach ($segments as $segment) {
            if ($segment === '.' || $segment === '..') {
                throw new RuntimeException('Backup directory cannot contain path traversal segments.');
            }
        }

        return implode('/', $segments);
    }

    public function normalizeBackupScope(?string $scope): string
    {
        if ($scope === null) {
            return '';
        }

        if (str_contains($scope, "\0")) {
            throw new RuntimeException('Backup scope contains invalid null-byte characters.');
        }

        $normalized = str_replace("\\", "/", trim($scope));
        if ($normalized === '') {
            return '';
        }

        if (str_starts_with($normalized, '/') || preg_match('/^[A-Za-z]:\//', $normalized) === 1) {
            throw new RuntimeException('Backup scope must be relative to the backup directory.');
        }

        $segments = array_values(array_filter(explode('/', trim($normalized, '/')), static fn(string $segment): bool => $segment !== ''));
        foreach ($segments as $segment) {
            if ($segment === '.' || $segment === '..') {
                throw new RuntimeException('Backup scope cannot contain path traversal segments.');
            }
        }

        return implode('/', $segments);
    }

    private function resolveDefaultBackupDirectory(): string
    {
        if (defined('CORIANDER_UPDATE_BACKUP_DIR') && is_string(CORIANDER_UPDATE_BACKUP_DIR) && trim(CORIANDER_UPDATE_BACKUP_DIR) !== '') {
            return CORIANDER_UPDATE_BACKUP_DIR;
        }

        return 'backups/coriander';
    }
}
