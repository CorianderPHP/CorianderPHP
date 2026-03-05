<?php
declare(strict_types=1);

namespace CorianderCore\Tests\Make;

use CorianderCore\Core\Console\Commands\Make\Migration\MakeMigration;
use CorianderCore\Tests\Support\TestDirectoryHelperTrait;
use PHPUnit\Framework\TestCase;

class MakeMigrationTest extends TestCase
{
    use TestDirectoryHelperTrait;

    private string $tempRoot;
    private string $migrationDir;

    protected function setUp(): void
    {
        $this->tempRoot = $this->createTemporaryDirectory('_tmp_make_migration');
        $this->migrationDir = $this->tempRoot . '/migrations';
    }

    protected function tearDown(): void
    {
        $this->deleteDirectory($this->tempRoot);
    }

    public function testCreatesMigrationFileWithTemplate(): void
    {
        $command = new MakeMigration($this->migrationDir);

        ob_start();
        $command->execute(['CreateUsersTable']);
        $output = (string) ob_get_clean();

        $files = glob($this->migrationDir . '/*.php') ?: [];

        $this->assertCount(1, $files);
        $this->assertMatchesRegularExpression('/\d{14}_create_users_table\.php$/', basename($files[0]));
        $this->assertStringContainsString('public function up(\\PDO $pdo): void', (string) file_get_contents($files[0]));
        $this->assertStringContainsString('Migration created', $output);
    }
}
