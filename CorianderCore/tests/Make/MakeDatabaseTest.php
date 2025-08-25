<?php

namespace CorianderCore\Tests\Make;

use PHPUnit\Framework\TestCase;
use CorianderCore\Core\Console\Commands\Make\Database\MakeDatabase;
use CorianderCore\Core\Console\Services\PdoDriverService;

class MakeDatabaseTest extends TestCase
{
    /**
     * @var MakeDatabase
     */
    protected $makeDatabase;

    /**
     * Sets up the necessary conditions before each test, including a mock for PdoDriverService.
     */
    protected function setUp(): void
    {
        // Mock the PdoDriverService
        $pdoDriverServiceMock = $this->createMock(PdoDriverService::class);
        $pdoDriverServiceMock->method('getAvailableDrivers')
            ->willReturn(['mysql', 'sqlite']);

        // Initialize MakeDatabase with the mocked PdoDriverService
        $this->makeDatabase = new MakeDatabase($pdoDriverServiceMock);
    }

    /**
     * Test the scenario when the MySQL driver is missing, ensuring the warning is displayed.
     */
    public function testMissingMysqlDriver()
    {
        // Mock the PdoDriverService to simulate missing MySQL
        $pdoDriverServiceMock = $this->createMock(PdoDriverService::class);
        $pdoDriverServiceMock->method('getAvailableDrivers')
            ->willReturn([]);  // MySQL is missing, no drivers

        $this->makeDatabase = new MakeDatabase($pdoDriverServiceMock);

        // Expect the warning message to be printed when MySQL is unavailable
        $this->expectOutputRegex("/MySQL PDO driver is not available/");

        // Execute the MakeDatabase process
        $this->makeDatabase->execute();
    }

    /**
     * Test the scenario when the MySQL driver is present, ensuring the success symbol is displayed.
     */
    public function testNoMissingMysqlDriver()
    {
        // Expect the output to contain the checkmark and MySQL in the correct format
        $this->expectOutputRegex("/1.\s*.*✓\s*.*MySQL/");

        // Execute the MakeDatabase process
        $this->makeDatabase->execute();
    }
}
