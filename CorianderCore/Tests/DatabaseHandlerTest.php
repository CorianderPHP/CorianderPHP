<?php
declare(strict_types=1);

namespace CorianderCore\Tests;

use CorianderCore\Core\Database\DatabaseHandler;
use PHPUnit\Framework\TestCase;

class DatabaseHandlerTest extends TestCase
{
    protected function tearDown(): void
    {
        DatabaseHandler::setAutoCloseConnection(true);
    }

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

    public function testCloseUsesInstanceAutoCloseSetting(): void
    {
        $handler = new DatabaseHandler(null, false);
        $pdo = new \PDO('sqlite::memory:');
        $this->injectPdo($handler, $pdo);

        $handler->close();

        $this->assertSame($pdo, $handler->getPDO());

        $handler->setAutoClose(true);
        $handler->close();

        $this->assertNull($handler->getPDO());
    }

    public function testStaticAutoCloseSetterOnlyChangesNewHandlerDefault(): void
    {
        $existingHandler = new DatabaseHandler(null, false);
        $existingPdo = new \PDO('sqlite::memory:');
        $this->injectPdo($existingHandler, $existingPdo);

        DatabaseHandler::setAutoCloseConnection(true);
        $newHandler = new DatabaseHandler();
        $newPdo = new \PDO('sqlite::memory:');
        $this->injectPdo($newHandler, $newPdo);

        $existingHandler->close();
        $newHandler->close();

        $this->assertSame($existingPdo, $existingHandler->getPDO());
        $this->assertNull($newHandler->getPDO());
    }

    private function injectPdo(DatabaseHandler $handler, \PDO $pdo): void
    {
        $reflection = new \ReflectionClass($handler);
        $pdoProperty = $reflection->getProperty('pdo');
        $pdoProperty->setValue($handler, $pdo);
    }
}
