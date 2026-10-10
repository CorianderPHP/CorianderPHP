<?php
declare(strict_types=1);

namespace CorianderCore\Tests;

use CorianderCore\Core\Database\Migrations\MigrationManager;
use CorianderCore\Tests\Support\TestDirectoryHelperTrait;
use PDO;
use PHPUnit\Framework\TestCase;

class MigrationManagerTest extends TestCase
{
    use TestDirectoryHelperTrait;

    private string $tempRoot;
    private string $databaseFile;
    private string $migrationDir;
    private ?PDO $pdo = null;
    private MigrationManager $manager;

    protected function setUp(): void
    {
        $this->tempRoot = $this->createTemporaryDirectory('_tmp_migration_manager');
        $this->databaseFile = $this->tempRoot . '/test.sqlite';
        $this->migrationDir = $this->tempRoot . '/migrations';

        mkdir($this->migrationDir, 0777, true);

        $this->pdo = new PDO('sqlite:' . $this->databaseFile);
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->manager = new MigrationManager($this->pdo, 'sqlite', $this->migrationDir);
    }

    protected function tearDown(): void
    {
        $this->pdo = null;
        $this->deleteDirectory($this->tempRoot);
    }

    public function testMigrateStatusAndRollbackFlow(): void
    {
        $this->writeMigration(
            '20260101000000_create_users_table.php',
            "CREATE TABLE users (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT NOT NULL)",
            "DROP TABLE IF EXISTS users"
        );

        $status = $this->manager->status();
        $this->assertCount(1, $status);
        $this->assertSame('pending', $status[0]['status']);

        $firstRun = $this->manager->migrate();
        $this->assertSame(1, $firstRun['applied']);
        $this->assertSame(1, $firstRun['batch']);

        $this->assertSame(1, (int) $this->pdo->query("SELECT COUNT(*) FROM sqlite_master WHERE type='table' AND name='users'")->fetchColumn());

        $this->writeMigration(
            '20260101000001_create_profiles_table.php',
            "CREATE TABLE profiles (id INTEGER PRIMARY KEY AUTOINCREMENT, user_id INTEGER NOT NULL)",
            "DROP TABLE IF EXISTS profiles"
        );

        $secondRun = $this->manager->migrate();
        $this->assertSame(1, $secondRun['applied']);
        $this->assertSame(2, $secondRun['batch']);
        $this->assertSame(1, (int) $this->pdo->query("SELECT COUNT(*) FROM sqlite_master WHERE type='table' AND name='profiles'")->fetchColumn());

        $rollback = $this->manager->rollback(1);
        $this->assertSame(1, $rollback['rolled_back']);
        $this->assertSame(2, $rollback['batch']);
        $this->assertSame(0, (int) $this->pdo->query("SELECT COUNT(*) FROM sqlite_master WHERE type='table' AND name='profiles'")->fetchColumn());

        $finalStatus = $this->manager->status();
        $this->assertSame('applied', $finalStatus[0]['status']);
        $this->assertSame('pending', $finalStatus[1]['status']);
    }

    public function testChecksumMismatchIsRejectedUnlessAllowed(): void
    {
        $file = '20260101000000_create_items_table.php';
        $this->writeMigration(
            $file,
            "CREATE TABLE items (id INTEGER PRIMARY KEY AUTOINCREMENT)",
            "DROP TABLE IF EXISTS items"
        );

        $this->manager->migrate();

        file_put_contents(
            $this->migrationDir . '/' . $file,
            $this->buildMigrationFileContent(
                "CREATE TABLE items (id INTEGER PRIMARY KEY AUTOINCREMENT)",
                "DROP TABLE IF EXISTS items",
                '// changed after execution'
            )
        );

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('checksum mismatch');
        $this->manager->status();
    }

    public function testChecksumMismatchCanBeInspectedWithAllowChanged(): void
    {
        $file = '20260101000000_create_items_table.php';
        $this->writeMigration(
            $file,
            "CREATE TABLE items (id INTEGER PRIMARY KEY AUTOINCREMENT)",
            "DROP TABLE IF EXISTS items"
        );

        $this->manager->migrate();

        file_put_contents(
            $this->migrationDir . '/' . $file,
            $this->buildMigrationFileContent(
                "CREATE TABLE items (id INTEGER PRIMARY KEY AUTOINCREMENT)",
                "DROP TABLE IF EXISTS items",
                '// changed after execution'
            )
        );

        $status = $this->manager->status(true);
        $this->assertTrue($status[0]['changed']);
    }

    public function testMigrationCanCloseFrameworkStartedTransaction(): void
    {
        file_put_contents(
            $this->migrationDir . '/20260101000000_manual_commit.php',
            "<?php\ndeclare(strict_types=1);\n\nreturn new class {\n    public function up(\\PDO \$pdo): void\n    {\n        \$pdo->exec('CREATE TABLE manually_committed (id INTEGER PRIMARY KEY AUTOINCREMENT)');\n        if (\$pdo->inTransaction()) {\n            \$pdo->commit();\n        }\n    }\n\n    public function down(\\PDO \$pdo): void\n    {\n        \$pdo->exec('DROP TABLE IF EXISTS manually_committed');\n    }\n};\n"
        );

        $result = $this->manager->migrate();

        $this->assertSame(1, $result['applied']);
        $this->assertSame(
            1,
            (int) $this->pdo->query("SELECT COUNT(*) FROM sqlite_master WHERE type='table' AND name='manually_committed'")->fetchColumn()
        );
    }

    private function writeMigration(string $filename, string $upSql, string $downSql): void
    {
        file_put_contents(
            $this->migrationDir . '/' . $filename,
            $this->buildMigrationFileContent($upSql, $downSql)
        );
    }

    public function testHistoryInsertFailureRollsBackMigrationChanges(): void
    {
        $this->pdo->exec('CREATE TABLE events (id INTEGER)');
        $this->writeMigration('20260101000000_insert_event.php', 'INSERT INTO events VALUES (1)', 'DELETE FROM events');
        $this->manager->status();
        $this->pdo->exec("CREATE TRIGGER reject_record BEFORE INSERT ON migrations BEGIN SELECT RAISE(ABORT, 'history insert failed'); END");

        try {
            $this->manager->migrate();
            $this->fail('Expected migration history failure.');
        } catch (\PDOException $exception) {
            $this->assertStringContainsString('history insert failed', $exception->getMessage());
        }
        $this->assertSame(0, (int) $this->pdo->query('SELECT COUNT(*) FROM events')->fetchColumn());
        $this->assertSame(0, (int) $this->pdo->query('SELECT COUNT(*) FROM migrations')->fetchColumn());

        $this->pdo->exec('DROP TRIGGER reject_record');
        $this->assertSame(1, $this->manager->migrate()['applied']);
        $this->assertSame(1, (int) $this->pdo->query('SELECT COUNT(*) FROM events')->fetchColumn());
    }

    public function testHistoryDeleteFailureRollsBackDownChanges(): void
    {
        $this->pdo->exec('CREATE TABLE events (id INTEGER)');
        $this->writeMigration('20260101000000_insert_event.php', 'INSERT INTO events VALUES (1)', 'DELETE FROM events');
        $this->manager->migrate();
        $this->pdo->exec("CREATE TRIGGER reject_delete BEFORE DELETE ON migrations BEGIN SELECT RAISE(ABORT, 'history delete failed'); END");

        try {
            $this->manager->rollback();
            $this->fail('Expected rollback history failure.');
        } catch (\PDOException $exception) {
            $this->assertStringContainsString('history delete failed', $exception->getMessage());
        }
        $this->assertSame(1, (int) $this->pdo->query('SELECT COUNT(*) FROM events')->fetchColumn());
        $this->assertSame(1, (int) $this->pdo->query('SELECT COUNT(*) FROM migrations')->fetchColumn());

        $this->pdo->exec('DROP TRIGGER reject_delete');
        $this->assertSame(1, $this->manager->rollback()['rolled_back']);
        $this->assertSame(0, (int) $this->pdo->query('SELECT COUNT(*) FROM events')->fetchColumn());
    }

    private function buildMigrationFileContent(string $upSql, string $downSql, string $extra = ''): string
    {
        return "<?php\ndeclare(strict_types=1);\n\nreturn new class {\n    public function up(\\PDO \$pdo): void\n    {\n        \$pdo->exec('" . addslashes($upSql) . "');\n    }\n\n    public function down(\\PDO \$pdo): void\n    {\n        \$pdo->exec('" . addslashes($downSql) . "');\n    }\n};\n" . ($extra !== '' ? $extra . "\n" : '');
    }

    public function testConcurrentProcessesRecheckMigrationAndRollbackStateAfterLocking(): void
    {
        $this->pdo->exec('CREATE TABLE events (action TEXT)');
        file_put_contents($this->migrationDir . '/20260101000000_record_event.php', <<<'PHP'
<?php
return new class {
    public function up(PDO $pdo): void { $this->record($pdo, 'up'); }
    public function down(PDO $pdo): void { $this->record($pdo, 'down'); }
    private function record(PDO $pdo, string $action): void {
        file_put_contents(__DIR__ . '/' . $action . '.started', 'ready');
        usleep(500000);
        $pdo->exec("INSERT INTO events VALUES ('$action')");
    }
};
PHP);
        $this->manager->status();
        $worker = $this->tempRoot . '/worker.php';
        file_put_contents($worker, <<<'PHP'
<?php
require $argv[1];
$pdo = new PDO('sqlite:' . $argv[2], null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$manager = new CorianderCore\Core\Database\Migrations\MigrationManager($pdo, 'sqlite', $argv[3]);
echo json_encode($manager->{$argv[4]}());
PHP);

        foreach (['migrate' => 'up', 'rollback' => 'down'] as $operation => $action) {
            $first = $this->startMigrationWorker($worker, $operation);
            $second = null;
            try {
                $deadline = microtime(true) + 5;
                while (!is_file($this->migrationDir . '/' . $action . '.started') && microtime(true) < $deadline) {
                    usleep(10000);
                }
                $this->assertFileExists($this->migrationDir . '/' . $action . '.started');
                $second = $this->startMigrationWorker($worker, $operation);
                $field = $operation === 'migrate' ? 'applied' : 'rolled_back';
                $this->assertSame(1, $this->finishMigrationWorker($first)[$field]);
                $this->assertSame(0, $this->finishMigrationWorker($second)[$field]);
                $this->assertSame(1, (int) $this->pdo->query("SELECT COUNT(*) FROM events WHERE action = '$action'")->fetchColumn());
            } finally {
                foreach ([$first, $second] as $workerProcess) {
                    if ($workerProcess !== null && is_resource($workerProcess[0])) {
                        proc_terminate($workerProcess[0]);
                        proc_close($workerProcess[0]);
                    }
                }
            }
        }
    }

    private function startMigrationWorker(string $worker, string $operation): array
    {
        $source = (new \ReflectionClass(MigrationManager::class))->getFileName();
        $process = proc_open([PHP_BINARY, $worker, $source, $this->databaseFile, $this->migrationDir, $operation],
            [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
        $this->assertIsResource($process);
        fclose($pipes[0]);
        return [$process, $pipes];
    }

    private function finishMigrationWorker(array $worker): array
    {
        [$process, $pipes] = $worker;
        $output = stream_get_contents($pipes[1]);
        $error = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $this->assertSame(0, proc_close($process), $error);
        return json_decode($output, true, 512, JSON_THROW_ON_ERROR);
    }
}
