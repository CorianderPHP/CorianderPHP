<?php
declare(strict_types=1);

use PDO;

return new class {
    public function up(PDO $pdo): void
    {
        $pdo->exec('CREATE TABLE users (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT NOT NULL)');
    }

    public function down(PDO $pdo): void
    {
        $pdo->exec('DROP TABLE IF EXISTS users');
    }
};
