<?php

declare(strict_types=1);

namespace CorianderCore\Tests;

use CorianderCore\Core\Database\DatabaseException;
use CorianderCore\Core\Database\DatabaseHandler;
use CorianderCore\Core\Database\SQLManager;
use PHPUnit\Framework\TestCase;

class SQLManagerTest extends TestCase
{
    public function testUpdateWhereDoesNotOverwriteConditionBindings(): void
    {
        $pdo = $this->createSqliteHandler();
        $pdo->exec('CREATE TABLE items (id INTEGER PRIMARY KEY, w_id INTEGER, `a-b` TEXT)');
        $pdo->exec("INSERT INTO items VALUES (1, 10, 'first'), (2, 20, 'second')");

        SQLManager::updateWhere('items', ['w_id' => 2, 'a-b' => 'changed'], ['id' => 1]);

        $this->assertSame([
            ['id' => 1, 'w_id' => 2, 'a-b' => 'changed'],
            ['id' => 2, 'w_id' => 20, 'a-b' => 'second'],
        ], $pdo->query('SELECT * FROM items ORDER BY id')->fetchAll(\PDO::FETCH_ASSOC));
    }

    public function testRawUpdatePreservesParametersResemblingGeneratedNames(): void
    {
        $pdo = $this->createSqliteHandler();
        $pdo->exec('CREATE TABLE items (id INTEGER PRIMARY KEY, value INTEGER)');
        $pdo->exec('INSERT INTO items VALUES (1, 10), (2, 20)');

        set_error_handler(static fn(int $severity): bool => $severity === E_USER_DEPRECATED);
        try {
            SQLManager::update('items', ['id' => 3, 'value' => 30], 'id = :set_0 AND value = :set_1', ['set_0' => 1, ':set_1' => 10]);
        } finally {
            restore_error_handler();
        }

        $this->assertSame([
            ['id' => 2, 'value' => 20],
            ['id' => 3, 'value' => 30],
        ], $pdo->query('SELECT * FROM items ORDER BY id')->fetchAll(\PDO::FETCH_ASSOC));
    }

    public function testFindWhereRejectsEmptyConditions(): void
    {
        $this->expectException(DatabaseException::class);
        SQLManager::findWhere(['id'], 'users', []);
    }

    public function testUpdateWhereRejectsEmptyConditions(): void
    {
        $this->expectException(DatabaseException::class);
        SQLManager::updateWhere('users', ['name' => 'new'], []);
    }

    public function testDeleteWhereRejectsEmptyConditions(): void
    {
        $this->expectException(DatabaseException::class);
        SQLManager::deleteWhere('users', []);
    }

    public function testBuildWhereFromArrayCreatesUniquePlaceholders(): void
    {
        $method = new \ReflectionMethod(SQLManager::class, 'buildWhereFromArray');
        [$clause, $params] = $method->invoke(null, ['a-b' => 1, 'a_b' => 2], 'w_');

        $this->assertSame('`a-b` = :w_a_b AND `a_b` = :w_a_b_1', $clause);
        $this->assertSame(['w_a_b' => 1, 'w_a_b_1' => 2], $params);
    }

    public function testFindAllSupportsWildcardColumn(): void
    {
        $pdo = $this->createSqliteHandler();
        $pdo->exec('CREATE TABLE users (id INTEGER PRIMARY KEY, name TEXT)');
        $pdo->exec("INSERT INTO users (name) VALUES ('alice')");

        $rows = SQLManager::findAll(['*'], 'users');

        $this->assertCount(1, $rows);
        $this->assertSame('alice', $rows[0]['name']);
    }

    public function testFindAllSupportsTableOnlySignature(): void
    {
        $pdo = $this->createSqliteHandler();
        $pdo->exec('CREATE TABLE users (id INTEGER PRIMARY KEY, name TEXT)');
        $pdo->exec("INSERT INTO users (name) VALUES ('bob')");

        $rows = SQLManager::findAll('users');

        $this->assertCount(1, $rows);
        $this->assertSame('bob', $rows[0]['name']);
    }

    public function testSafeWhereMethodsDoNotTriggerDeprecatedRawMethods(): void
    {
        $pdo = $this->createSqliteHandler();
        $pdo->exec('CREATE TABLE users (id INTEGER PRIMARY KEY, name TEXT)');
        $pdo->exec("INSERT INTO users (name) VALUES ('alice')");

        $deprecations = 0;
        set_error_handler(static function (int $severity) use (&$deprecations): bool {
            if ($severity === E_USER_DEPRECATED) {
                $deprecations++;
            }

            return true;
        });

        try {
            $rows = SQLManager::findWhere(['id', 'name'], 'users', ['name' => 'alice']);
            $updated = SQLManager::updateWhere('users', ['name' => 'bob'], ['id' => 1]);
            $deleted = SQLManager::deleteWhere('users', ['name' => 'bob']);
        } finally {
            restore_error_handler();
        }

        $this->assertSame(0, $deprecations);
        $this->assertSame([['id' => 1, 'name' => 'alice']], $rows);
        $this->assertTrue($updated);
        $this->assertTrue($deleted);
        $this->assertSame([], SQLManager::findAll('users'));
    }

    public function testSqlScriptReturnsAllRowsForCustomSelect(): void
    {
        $pdo = $this->createSqliteHandler();
        $pdo->exec('CREATE TABLE users (id INTEGER PRIMARY KEY, name TEXT, status TEXT)');
        $pdo->exec("INSERT INTO users (name, status) VALUES ('alice', 'active')");
        $pdo->exec("INSERT INTO users (name, status) VALUES ('bob', 'active')");
        $pdo->exec("INSERT INTO users (name, status) VALUES ('charlie', 'disabled')");

        $rows = SQLManager::sqlScript(
            'SELECT id, name FROM users WHERE status = :status ORDER BY id ASC',
            ['status' => 'active']
        );

        $this->assertSame([
            ['id' => 1, 'name' => 'alice'],
            ['id' => 2, 'name' => 'bob'],
        ], $rows);
    }

    public function testSqlScriptReturnsSingleRowForCustomSelectWithOneResult(): void
    {
        $pdo = $this->createSqliteHandler();
        $pdo->exec('CREATE TABLE users (id INTEGER PRIMARY KEY, name TEXT, status TEXT)');
        $pdo->exec("INSERT INTO users (name, status) VALUES ('alice', 'active')");

        $row = SQLManager::sqlScript(
            'SELECT id, name FROM users WHERE status = :status',
            ['status' => 'active']
        );

        $this->assertSame(['id' => 1, 'name' => 'alice'], $row);
    }

    public function testSqlScriptReturnsEmptyArrayForCustomSelectWithNoResults(): void
    {
        $pdo = $this->createSqliteHandler();
        $pdo->exec('CREATE TABLE users (id INTEGER PRIMARY KEY, name TEXT, status TEXT)');

        $rows = SQLManager::sqlScript(
            'SELECT id, name FROM users WHERE status = :status',
            ['status' => 'active']
        );

        $this->assertSame([], $rows);
    }

    public function testSqlScriptReturnsTrueForCustomWriteStatement(): void
    {
        $pdo = $this->createSqliteHandler();
        $pdo->exec('CREATE TABLE users (id INTEGER PRIMARY KEY, name TEXT, status TEXT)');
        $pdo->exec("INSERT INTO users (name, status) VALUES ('alice', 'active')");

        $result = SQLManager::sqlScript(
            'UPDATE users SET status = :status WHERE name = :name',
            ['status' => 'disabled', 'name' => 'alice']
        );

        $this->assertTrue($result);
        $this->assertSame(
            [['status' => 'disabled']],
            SQLManager::findWhere(['status'], 'users', ['name' => 'alice'])
        );
    }

    public function testBuildColumnListSupportsQualifiedWildcard(): void
    {
        $method = new \ReflectionMethod(SQLManager::class, 'buildColumnList');
        $columnList = $method->invoke(null, ['users.*', 'users.name']);

        $this->assertSame('`users`.*, `users`.`name`', $columnList);
    }

    public function testQuoteIdentifierRejectsEmptyIdentifier(): void
    {
        $method = new \ReflectionMethod(SQLManager::class, 'quoteIdentifier');
        $this->expectException(DatabaseException::class);
        $method->invoke(null, '');
    }

    private function createSqliteHandler(): \PDO
    {
        $pdo = new \PDO('sqlite::memory:');
        $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);

        $handler = new DatabaseHandler();
        $reflection = new \ReflectionClass($handler);
        $pdoProperty = $reflection->getProperty('pdo');
        $pdoProperty->setValue($handler, $pdo);

        SQLManager::setDatabaseHandler($handler);

        return $pdo;
    }
}
