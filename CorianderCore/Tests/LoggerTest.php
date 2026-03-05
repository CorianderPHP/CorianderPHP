<?php

declare(strict_types=1);

namespace CorianderCore\Tests;

use CorianderCore\Core\Logging\Logger;
use PHPUnit\Framework\TestCase;

class LoggerTest extends TestCase
{
    public function testInvalidMinLevelFallsBackToWarning(): void
    {
        $logFile = sys_get_temp_dir() . '/coriander-logger-' . uniqid('', true) . '.log';

        try {
            $logger = new Logger($logFile, 'invalid-level');
            $logger->log('info', 'info should be filtered');
            $logger->log('warning', 'warning should be logged');

            $this->assertFileExists($logFile);
            $content = (string) file_get_contents($logFile);

            $this->assertStringNotContainsString('[INFO] info should be filtered', $content);
            $this->assertStringContainsString('[WARNING] warning should be logged', $content);
        } finally {
            if (file_exists($logFile)) {
                unlink($logFile);
            }
        }
    }
}
