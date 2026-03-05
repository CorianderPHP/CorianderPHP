<?php
declare(strict_types=1);

namespace CorianderCore\Tests;

use CorianderCore\Core\Bootstrap\TimezoneBootstrap;
use PHPUnit\Framework\TestCase;

class TimezoneBootstrapTest extends TestCase
{
    private string $originalTimezone;

    protected function setUp(): void
    {
        $this->originalTimezone = date_default_timezone_get();
    }

    protected function tearDown(): void
    {
        date_default_timezone_set($this->originalTimezone);
    }

    public function testApplyFromEnvironmentSetsValidTimezone(): void
    {
        $applied = TimezoneBootstrap::applyFromEnvironment('Europe/Paris');

        $this->assertTrue($applied);
        $this->assertSame('Europe/Paris', date_default_timezone_get());
    }

    public function testApplyFromEnvironmentRejectsInvalidTimezone(): void
    {
        date_default_timezone_set('UTC');

        $applied = TimezoneBootstrap::applyFromEnvironment('Invalid/Timezone');

        $this->assertFalse($applied);
        $this->assertSame('UTC', date_default_timezone_get());
    }
}
