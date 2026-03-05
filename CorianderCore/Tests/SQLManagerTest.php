<?php

declare(strict_types=1);

namespace CorianderCore\Tests;

use CorianderCore\Core\Database\DatabaseException;
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
}
