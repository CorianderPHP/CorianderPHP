<?php

declare(strict_types=1);

namespace CorianderCore\Tests;

use CorianderCore\Core\Console\ConsoleOutput;
use PHPUnit\Framework\TestCase;

class ConsoleOutputTest extends TestCase
{
    private string|false $previousNoColor;
    private string|false $previousForceColor;
    private string|false $previousCliColorForce;

    protected function setUp(): void
    {
        $this->previousNoColor = getenv('NO_COLOR');
        $this->previousForceColor = getenv('FORCE_COLOR');
        $this->previousCliColorForce = getenv('CLICOLOR_FORCE');
    }

    protected function tearDown(): void
    {
        $this->restoreEnv('NO_COLOR', $this->previousNoColor);
        $this->restoreEnv('FORCE_COLOR', $this->previousForceColor);
        $this->restoreEnv('CLICOLOR_FORCE', $this->previousCliColorForce);
    }

    public function testPrintDisablesAnsiWhenNoColorIsSet(): void
    {
        putenv('NO_COLOR=1');
        putenv('FORCE_COLOR');
        putenv('CLICOLOR_FORCE');

        ob_start();
        ConsoleOutput::print('&4Error');
        $output = (string) ob_get_clean();

        $this->assertStringNotContainsString("\033[", $output);
        $this->assertSame("Error" . PHP_EOL, $output);
    }

    public function testPrintUsesAnsiWhenForceColorIsSet(): void
    {
        putenv('NO_COLOR');
        putenv('FORCE_COLOR=1');
        putenv('CLICOLOR_FORCE');

        ob_start();
        ConsoleOutput::print('&4Error');
        $output = (string) ob_get_clean();

        $this->assertStringContainsString("\033[", $output);
        $this->assertStringContainsString('Error', $output);
    }

    private function restoreEnv(string $name, string|false $value): void
    {
        if ($value === false) {
            putenv($name);
            return;
        }

        putenv($name . '=' . $value);
    }
}
