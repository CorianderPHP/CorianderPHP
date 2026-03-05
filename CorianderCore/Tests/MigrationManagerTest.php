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

    private function writeMigration(string $filename, string $upSql, string $downSql): void
    {
        file_put_contents(
            $this->migrationDir . '/' . $filename,
            $this->buildMigrationFileContent($upSql, $downSql)
        );
    }

    private function buildMigrationFileContent(string $upSql, string $downSql, string $extra = ''): string
    {
        return "<?php\ndeclare(strict_types=1);\n\nreturn new class {\n    public function up(\\PDO \$pdo): void\n    {\n        \$pdo->exec('" . addslashes($upSql) . "');\n    }\n\n    public function down(\\PDO \$pdo): void\n    {\n        \$pdo->exec('" . addslashes($downSql) . "');\n    }\n};\n" . ($extra !== '' ? $extra . "\n" : '');
    }
}
