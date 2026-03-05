<?php

declare(strict_types=1);

namespace CorianderCore\Tests;

use CorianderCore\Core\Database\DatabaseException;
use CorianderCore\Core\Database\DatabaseHandler;
use CorianderCore\Core\Database\SQLManager;
use PHPUnit\Framework\TestCase;

class SQLManagerTest extends TestCase
{
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
        $method->setAccessible(true);

        [$clause, $params] = $method->invoke(null, ['a-b' => 1, 'a_b' => 2], 'w_');

        $this->assertSame('`a-b` = :w_a_b AND `a_b` = :w_a_b_1', $clause);
        $this->assertSame(['w_a_b' => 1, 'w_a_b_1' => 2], $params);
    }

    public function testFindAllSupportsWildcardColumn(): void
    {
        $pdo = new \PDO('sqlite::memory:');
        $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        $pdo->exec('CREATE TABLE users (id INTEGER PRIMARY KEY, name TEXT)');
        $pdo->exec("INSERT INTO users (name) VALUES ('alice')");

        $handler = new DatabaseHandler();
        $reflection = new \ReflectionClass($handler);
        $pdoProperty = $reflection->getProperty('pdo');
        $pdoProperty->setAccessible(true);
        $pdoProperty->setValue($handler, $pdo);

        SQLManager::setDatabaseHandler($handler);

        $rows = SQLManager::findAll(['*'], 'users');

        $this->assertCount(1, $rows);
        $this->assertSame('alice', $rows[0]['name']);
    }

    public function testFindAllSupportsTableOnlySignature(): void
    {
        $pdo = new \PDO('sqlite::memory:');
        $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        $pdo->exec('CREATE TABLE users (id INTEGER PRIMARY KEY, name TEXT)');
        $pdo->exec("INSERT INTO users (name) VALUES ('bob')");

        $handler = new DatabaseHandler();
        $reflection = new \ReflectionClass($handler);
        $pdoProperty = $reflection->getProperty('pdo');
        $pdoProperty->setAccessible(true);
        $pdoProperty->setValue($handler, $pdo);

        SQLManager::setDatabaseHandler($handler);

        $rows = SQLManager::findAll('users');

        $this->assertCount(1, $rows);
        $this->assertSame('bob', $rows[0]['name']);
    }

    public function testBuildColumnListSupportsQualifiedWildcard(): void
    {
        $method = new \ReflectionMethod(SQLManager::class, 'buildColumnList');
        $method->setAccessible(true);

        $columnList = $method->invoke(null, ['users.*', 'users.name']);

        $this->assertSame('`users`.*, `users`.`name`', $columnList);
    }

    public function testQuoteIdentifierRejectsEmptyIdentifier(): void
    {
        $method = new \ReflectionMethod(SQLManager::class, 'quoteIdentifier');
        $method->setAccessible(true);

        $this->expectException(DatabaseException::class);
        $method->invoke(null, '');
    }
}
