<?php
declare(strict_types=1);

namespace CorianderCore\Core\Database\Migrations;

use PDO;
use RuntimeException;

/**
 * MigrationManager discovers, validates, and executes database migrations.
 */
class MigrationManager
{
    private const LOCK_NAME_PREFIX = 'coriander_migrations_';

    private ?string $lockName = null;

    public function __construct(
        private PDO $pdo,
        private string $connection,
        private string $migrationsDirectory
    ) {}

    /**
     * @return array{applied: int, pending: int, ran: list<string>, batch: int}
     */
    public function migrate(bool $dryRun = false, bool $allowChanged = false): array
    {
        $this->ensureMigrationsTable();

        $records = $this->getAppliedRecords();
        $files = $this->discoverMigrationFiles();
        $checksumChanges = $this->collectAppliedChecksumChanges($records, $files);
        $this->assertNoChangedAppliedMigrations($checksumChanges, $allowChanged);

        $pending = [];
        foreach ($files as $file) {
            if (!isset($records[$file['filename']])) {
                $pending[] = $file;
            }
        }

        if ($dryRun) {
            return [
                'applied' => 0,
                'pending' => count($pending),
                'ran' => array_map(static fn(array $f): string => $f['filename'], $pending),
                'batch' => $this->nextBatchNumber(),
            ];
        }

        if ($pending === []) {
            return ['applied' => 0, 'pending' => 0, 'ran' => [], 'batch' => $this->nextBatchNumber()];
        }

        $this->acquireLock();

        try {
            $batch = $this->nextBatchNumber();
            $ran = [];

            foreach ($pending as $file) {
                $migration = $this->loadMigration($file['path']);
                $this->runInTransaction(function () use ($migration): void {
                    $migration->up($this->pdo);
                });

                $this->recordMigration($file['filename'], $batch, $this->calculateChecksum($file['path']));
                $ran[] = $file['filename'];
            }

            return [
                'applied' => count($ran),
                'pending' => count($pending) - count($ran),
                'ran' => $ran,
                'batch' => $batch,
            ];
        } finally {
            $this->releaseLock();
        }
    }

    /**
     * @return array{rolled_back: int, batch: int|null, files: list<string>}
     */
    public function rollback(int $step = 1, bool $dryRun = false): array
    {
        if ($step < 1) {
            throw new RuntimeException('Rollback step must be greater than or equal to 1.');
        }

        $this->ensureMigrationsTable();
        $targets = $this->getRollbackTargets($step);

        if ($targets === []) {
            return ['rolled_back' => 0, 'batch' => null, 'files' => []];
        }

        if ($dryRun) {
            return [
                'rolled_back' => 0,
                'batch' => (int) $targets[0]['batch'],
                'files' => array_map(static fn(array $t): string => $t['filename'], $targets),
            ];
        }

        $files = $this->discoverMigrationFilesIndexed();
        $this->acquireLock();

        try {
            $rolledBack = [];
            $batch = (int) $targets[0]['batch'];

            foreach ($targets as $target) {
                $filename = (string) $target['filename'];
                if (!isset($files[$filename])) {
                    throw new RuntimeException("Cannot rollback migration '{$filename}': file not found.");
                }

                $migration = $this->loadMigration($files[$filename]['path']);
                if (!method_exists($migration, 'down')) {
                    throw new RuntimeException("Cannot rollback migration '{$filename}': down() method is missing.");
                }

                $this->runInTransaction(function () use ($migration): void {
                    $migration->down($this->pdo);
                });

                $this->deleteMigrationRecord($filename);
                $rolledBack[] = $filename;
            }

            return [
                'rolled_back' => count($rolledBack),
                'batch' => $batch,
                'files' => $rolledBack,
            ];
        } finally {
            $this->releaseLock();
        }
    }

    /**
     * @return list<array{filename: string, status: string, batch: int|null, executed_at: string|null, changed: bool}>
     */
    public function status(bool $allowChanged = false): array
    {
        $this->ensureMigrationsTable();
        $records = $this->getAppliedRecords();
        $files = $this->discoverMigrationFiles();

        $checksumChanges = $this->collectAppliedChecksumChanges($records, $files);
        $this->assertNoChangedAppliedMigrations($checksumChanges, $allowChanged);

        $status = [];
        foreach ($files as $file) {
            $record = $records[$file['filename']] ?? null;
            $status[] = [
                'filename' => $file['filename'],
                'status' => $record ? 'applied' : 'pending',
                'batch' => $record ? (int) $record['batch'] : null,
                'executed_at' => $record['executed_at'] ?? null,
                'changed' => $record ? ($checksumChanges[$file['filename']] ?? false) : false,
            ];
        }

        return $status;
    }

    public function ensureMigrationsDirectoryExists(): void
    {
        if (!is_dir($this->migrationsDirectory) && !mkdir($concurrentDirectory = $this->migrationsDirectory, 0777, true) && !is_dir($concurrentDirectory)) {
            throw new RuntimeException('Failed to create migrations directory: ' . $this->migrationsDirectory);
        }
    }

    /**
     * @return list<array{filename: string, path: string}>
     */
    private function discoverMigrationFiles(): array
    {
        $this->ensureMigrationsDirectoryExists();
        $files = glob($this->migrationsDirectory . '/*.php') ?: [];
        sort($files, SORT_STRING);

        $migrations = [];
        foreach ($files as $file) {
            $filename = basename($file);
            if (!preg_match('/^\d{14}_[a-z0-9_]+\.php$/', $filename)) {
                continue;
            }

            $migrations[] = [
                'filename' => $filename,
                'path' => $file,
            ];
        }

        return $migrations;
    }

    /**
     * @return array<string, array{filename: string, path: string}>
     */
    private function discoverMigrationFilesIndexed(): array
    {
        $indexed = [];
        foreach ($this->discoverMigrationFiles() as $file) {
            $indexed[$file['filename']] = $file;
        }

        return $indexed;
    }

    private function ensureMigrationsTable(): void
    {
        if ($this->isSqliteConnection()) {
            $this->pdo->exec(
                'CREATE TABLE IF NOT EXISTS migrations (' .
                'id INTEGER PRIMARY KEY AUTOINCREMENT,' .
                'filename TEXT NOT NULL,' .
                'connection TEXT NOT NULL,' .
                'batch INTEGER NOT NULL,' .
                'checksum TEXT NOT NULL,' .
                'executed_at TEXT NOT NULL' .
                ')'
            );
            $this->pdo->exec('CREATE UNIQUE INDEX IF NOT EXISTS idx_migrations_connection_filename ON migrations(connection, filename)');
            return;
        }

        $this->pdo->exec(
            'CREATE TABLE IF NOT EXISTS migrations (' .
            'id INT AUTO_INCREMENT PRIMARY KEY,' .
            'filename VARCHAR(255) NOT NULL,' .
            'connection VARCHAR(64) NOT NULL,' .
            'batch INT NOT NULL,' .
            'checksum VARCHAR(64) NOT NULL,' .
            'executed_at DATETIME NOT NULL,' .
            'UNIQUE KEY uniq_migrations_connection_filename (connection, filename)' .
            ') ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
        );
    }

    /**
     * @return array<string, array{filename: string, batch: int, checksum: string, executed_at: string}>
     */
    private function getAppliedRecords(): array
    {
        $statement = $this->pdo->prepare('SELECT filename, batch, checksum, executed_at FROM migrations WHERE connection = :connection ORDER BY id ASC');
        $statement->execute(['connection' => $this->connection]);

        $records = [];
        foreach ($statement->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
            $records[(string) $row['filename']] = [
                'filename' => (string) $row['filename'],
                'batch' => (int) $row['batch'],
                'checksum' => (string) $row['checksum'],
                'executed_at' => (string) $row['executed_at'],
            ];
        }

        return $records;
    }

    private function nextBatchNumber(): int
    {
        $statement = $this->pdo->prepare('SELECT MAX(batch) FROM migrations WHERE connection = :connection');
        $statement->execute(['connection' => $this->connection]);
        $maxBatch = $statement->fetchColumn();
        if ($maxBatch === false || $maxBatch === null) {
            return 1;
        }

        return ((int) $maxBatch) + 1;
    }

    private function recordMigration(string $filename, int $batch, string $checksum): void
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO migrations (filename, connection, batch, checksum, executed_at) VALUES (:filename, :connection, :batch, :checksum, :executed_at)'
        );

        $statement->execute([
            'filename' => $filename,
            'connection' => $this->connection,
            'batch' => $batch,
            'checksum' => $checksum,
            'executed_at' => date('Y-m-d H:i:s'),
        ]);
    }

    private function deleteMigrationRecord(string $filename): void
    {
        $statement = $this->pdo->prepare('DELETE FROM migrations WHERE connection = :connection AND filename = :filename');
        $statement->execute([
            'connection' => $this->connection,
            'filename' => $filename,
        ]);
    }

    /**
     * @return list<array{filename: string, batch: int}>
     */
    private function getRollbackTargets(int $step): array
    {
        $statement = $this->pdo->prepare('SELECT filename, batch FROM migrations WHERE connection = :connection ORDER BY id DESC');
        $statement->execute(['connection' => $this->connection]);

        $rows = $statement->fetchAll(PDO::FETCH_ASSOC) ?: [];
        if ($rows === []) {
            return [];
        }

        $targets = [];
        $seenBatches = [];

        foreach ($rows as $row) {
            $batch = (int) $row['batch'];
            if (!in_array($batch, $seenBatches, true)) {
                $seenBatches[] = $batch;
                if (count($seenBatches) > $step) {
                    break;
                }
            }

            $targets[] = [
                'filename' => (string) $row['filename'],
                'batch' => $batch,
            ];
        }

        return $targets;
    }

    /**
     * @param array<string, array{filename: string, batch: int, checksum: string, executed_at: string}> $records
     * @param list<array{filename: string, path: string}> $files
     * @return array<string,bool>
     */
    private function collectAppliedChecksumChanges(array $records, array $files): array
    {
        $pathsByFilename = [];
        foreach ($files as $file) {
            $pathsByFilename[$file['filename']] = $file['path'];
        }

        $changes = [];
        foreach ($records as $filename => $record) {
            if (!isset($pathsByFilename[$filename])) {
                continue;
            }

            $checksum = $this->calculateChecksum($pathsByFilename[$filename]);
            $changes[$filename] = $record['checksum'] !== $checksum;
        }

        return $changes;
    }

    /**
     * @param array<string,bool> $checksumChanges
     */
    private function assertNoChangedAppliedMigrations(array $checksumChanges, bool $allowChanged): void
    {
        if ($allowChanged) {
            return;
        }

        foreach ($checksumChanges as $filename => $changed) {
            if (!$changed) {
                continue;
            }

            throw new RuntimeException(
                "Migration checksum mismatch detected for '{$filename}'. " .
                "Use --allow-changed only in local development if you intentionally edited an applied migration."
            );
        }
    }

    private function calculateChecksum(string $path): string
    {
        $checksum = hash_file('sha256', $path);
        if ($checksum === false) {
            throw new RuntimeException('Unable to hash migration file: ' . basename($path));
        }

        return $checksum;
    }

    private function loadMigration(string $path): object
    {
        $migration = require $path;

        if (!is_object($migration)) {
            throw new RuntimeException('Migration file must return an object: ' . basename($path));
        }

        if (!method_exists($migration, 'up')) {
            throw new RuntimeException('Migration object must define up(PDO $pdo): ' . basename($path));
        }

        return $migration;
    }

    /**
     * @param callable():void $callback
     */
    private function runInTransaction(callable $callback): void
    {
        $inTransaction = $this->pdo->inTransaction();

        if (!$inTransaction) {
            $this->pdo->beginTransaction();
        }

        try {
            $callback();
            if (!$inTransaction) {
                $this->pdo->commit();
            }
        } catch (\Throwable $exception) {
            if (!$inTransaction && $this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $exception;
        }
    }

    private function acquireLock(): void
    {
        if ($this->isSqliteConnection()) {
            return;
        }

        $statement = $this->pdo->prepare('SELECT GET_LOCK(:name, 10)');
        $statement->execute(['name' => $this->getLockName()]);
        $acquired = $statement->fetchColumn();

        if ((int) $acquired !== 1) {
            throw new RuntimeException('Could not acquire migration lock. Another migration process may be running.');
        }
    }

    private function releaseLock(): void
    {
        if ($this->isSqliteConnection()) {
            return;
        }

        $statement = $this->pdo->prepare('SELECT RELEASE_LOCK(:name)');
        $statement->execute(['name' => $this->getLockName()]);
    }

    private function isSqliteConnection(): bool
    {
        return strtolower($this->connection) === 'sqlite';
    }

    private function getLockName(): string
    {
        if ($this->lockName !== null) {
            return $this->lockName;
        }

        $projectRoot = defined('PROJECT_ROOT') ? (string) PROJECT_ROOT : '';
        $dbName = defined('DB_NAME') ? (string) DB_NAME : '';
        $seed = strtolower($this->connection) . '|' . $projectRoot . '|' . $dbName;

        $this->lockName = self::LOCK_NAME_PREFIX . substr(hash('sha256', $seed), 0, 32);
        return $this->lockName;
    }
}





