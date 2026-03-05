<?php
declare(strict_types=1);

namespace CorianderCore\Core\Console\Commands\Make\Migration;

use CorianderCore\Core\Console\ConsoleOutput;
use RuntimeException;

class MakeMigration
{
    public function __construct(private ?string $migrationsDirectory = null)
    {
        $this->migrationsDirectory ??= PROJECT_ROOT . '/database/migrations';
    }

    /**
     * @param array<int, string> $args
     */
    public function execute(array $args): void
    {
        if (!isset($args[0]) || trim($args[0]) === '') {
            ConsoleOutput::print('&4[Error]&7 Please specify a migration name, e.g., make:migration CreateUsersTable.');
            return;
        }

        $slug = $this->toSnakeCase((string) $args[0]);
        if ($slug === '') {
            ConsoleOutput::print('&4[Error]&7 Invalid migration name. Use letters, numbers, or underscores.');
            return;
        }

        $timestamp = date('YmdHis');
        $filename = $timestamp . '_' . $slug . '.php';

        if (!is_dir($this->migrationsDirectory) && !mkdir($concurrentDirectory = $this->migrationsDirectory, 0777, true) && !is_dir($concurrentDirectory)) {
            throw new RuntimeException('Failed to create migrations directory: ' . $this->migrationsDirectory);
        }

        $fullPath = $this->migrationsDirectory . '/' . $filename;
        if (file_exists($fullPath)) {
            ConsoleOutput::print('&4[Error]&7 Migration file already exists: ' . $filename);
            return;
        }

        file_put_contents($fullPath, $this->buildTemplate());

        ConsoleOutput::print('&2[Success]&7 Migration created: &8' . $this->relativePath($fullPath));
    }

    private function buildTemplate(): string
    {
        return <<<'PHP'
<?php
declare(strict_types=1);

return new class {
    public function up(\PDO $pdo): void
    {
        // Example:
        // $pdo->exec('CREATE TABLE users (id INTEGER PRIMARY KEY AUTOINCREMENT)');
    }

    public function down(\PDO $pdo): void
    {
        // Example:
        // $pdo->exec('DROP TABLE IF EXISTS users');
    }
};
PHP;
    }

    private function toSnakeCase(string $value): string
    {
        $value = trim($value);
        $value = preg_replace('/(?<!^)[A-Z]/', '_$0', $value) ?? $value;
        $value = strtolower($value);
        $value = preg_replace('/[^a-z0-9_]+/', '_', $value) ?? $value;
        $value = trim($value, '_');

        return preg_replace('/_+/', '_', $value) ?? '';
    }

    private function relativePath(string $path): string
    {
        $root = rtrim((string) PROJECT_ROOT, '/\\') . DIRECTORY_SEPARATOR;
        if (str_starts_with($path, $root)) {
            return str_replace('\\', '/', substr($path, strlen($root)));
        }

        return str_replace('\\', '/', $path);
    }
}
