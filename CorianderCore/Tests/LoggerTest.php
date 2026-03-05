<?php

declare(strict_types=1);

namespace CorianderCore\Tests;

use CorianderCore\Core\Logging\Logger;
use PHPUnit\Framework\TestCase;

class LoggerTest extends TestCase
{
    protected function tearDown(): void
    {
        putenv('APP_ENV');
        putenv('LOG_FORMAT');
        putenv('LOG_MAX_FILE_BYTES');
        putenv('LOG_MAX_FILES');
    }

    public function testInvalidMinLevelFallsBackToWarning(): void
    {
        $logFile = sys_get_temp_dir() . '/coriander-logger-' . uniqid('', true) . '.log';

        try {
            $logger = new Logger($logFile, 'invalid-level');
            $logger->log('info', 'info should be filtered');
            $logger->log('warning', 'warning should be logged');

            $this->assertFileExists($logFile);
            $content = (string) file_get_contents($logFile);

            $this->assertStringNotContainsString('info should be filtered', $content);
            $this->assertStringContainsString('warning should be logged', $content);
        } finally {
            if (file_exists($logFile)) {
                unlink($logFile);
            }
        }
    }

    public function testJsonFormatWritesStructuredRecordsInProduction(): void
    {
        putenv('APP_ENV=production');
        putenv('LOG_FORMAT=json');

        $logFile = sys_get_temp_dir() . '/coriander-logger-json-' . uniqid('', true) . '.log';

        try {
            $logger = new Logger($logFile, 'debug');
            $logger->log('info', 'hello {user}', ['user' => 'alice']);

            $raw = (string) file_get_contents($logFile);
            $decoded = json_decode(trim($raw), true);

            $this->assertIsArray($decoded);
            $this->assertSame('info', $decoded['level']);
            $this->assertSame('hello alice', $decoded['message']);
            $this->assertSame(['user' => 'alice'], $decoded['context']);
        } finally {
            if (file_exists($logFile)) {
                unlink($logFile);
            }
        }
    }

    public function testFileRotationMovesCurrentLogWhenSizeLimitReached(): void
    {
        putenv('APP_ENV=production');
        putenv('LOG_FORMAT=line');
        putenv('LOG_MAX_FILE_BYTES=100');
        putenv('LOG_MAX_FILES=2');

        $logFile = sys_get_temp_dir() . '/coriander-logger-rotate-' . uniqid('', true) . '.log';

        try {
            $logger = new Logger($logFile, 'debug');
            $logger->log('info', str_repeat('A', 80));
            $logger->log('info', str_repeat('B', 80));

            $this->assertFileExists($logFile . '.1');
        } finally {
            if (file_exists($logFile)) {
                unlink($logFile);
            }
            if (file_exists($logFile . '.1')) {
                unlink($logFile . '.1');
            }
            if (file_exists($logFile . '.2')) {
                unlink($logFile . '.2');
            }
        }
    }
}
