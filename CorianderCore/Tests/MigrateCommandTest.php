<?php
declare(strict_types=1);

namespace CorianderCore\Tests;

use CorianderCore\Core\Console\Commands\Migrate;
use CorianderCore\Core\Console\CommandExitCode;
use PHPUnit\Framework\TestCase;

class MigrateCommandTest extends TestCase
{
    private string|false $previousAppEnv;

    protected function setUp(): void
    {
        $this->previousAppEnv = getenv('APP_ENV');
    }

    protected function tearDown(): void
    {
        if ($this->previousAppEnv === false) {
            putenv('APP_ENV');
            return;
        }

        putenv('APP_ENV=' . $this->previousAppEnv);
    }

    public function testAllowChangedIsBlockedOutsideLocalEnvironment(): void
    {
        putenv('APP_ENV=production');

        $command = new Migrate();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('--allow-changed is restricted');
        $command->execute(['--allow-changed']);
    }

    public function testAllowChangedPassesEnvironmentGuardInLocal(): void
    {
        putenv('APP_ENV=local');

        $command = new Migrate();

        ob_start();
        $exitCode = $command->execute(['--allow-changed']);
        $output = (string) ob_get_clean();

        $this->assertSame(CommandExitCode::SUCCESS, $exitCode);
        $this->assertStringContainsString('Database is up to date.', $output);
    }

    public function testUnknownActionReturnsUnknownCommand(): void
    {
        $command = new Migrate();

        ob_start();
        $exitCode = $command->execute(['unknown']);
        $output = (string) ob_get_clean();

        $this->assertSame(CommandExitCode::UNKNOWN_COMMAND, $exitCode);
        $this->assertStringContainsString('Unknown migrate command: migrate:unknown', $output);
    }
}
