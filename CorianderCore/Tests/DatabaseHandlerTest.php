<?php
declare(strict_types=1);

namespace CorianderCore\Tests;

use CorianderCore\Core\Database\DatabaseHandler;
use PHPUnit\Framework\TestCase;

class DatabaseHandlerTest extends TestCase
{
    public function testBuildMysqlDsnIncludesPortAndProvidedCharset(): void
    {
        $dsn = DatabaseHandler::buildMysqlDsn('localhost', 'app_db', 3307, 'latin1');

        $this->assertSame('mysql:host=localhost;port=3307;dbname=app_db;charset=latin1', $dsn);
    }

    public function testBuildMysqlDsnFallsBackToUtf8mb4AndOmitsInvalidPort(): void
    {
        $dsn = DatabaseHandler::buildMysqlDsn('127.0.0.1', 'app_db', 0, '');

        $this->assertSame('mysql:host=127.0.0.1;dbname=app_db;charset=utf8mb4', $dsn);
    }
}
