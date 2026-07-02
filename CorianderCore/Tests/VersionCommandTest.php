<?php

declare(strict_types=1);

namespace CorianderCore\Tests;

use CorianderCore\Core\Console\Commands\Version;
use CorianderCore\Core\Console\CommandExitCode;
use CorianderCore\Core\Console\Services\Updater\FrameworkVersionService;
use PHPUnit\Framework\TestCase;

class VersionCommandTest extends TestCase
{
    public function testExecutePrintsLocalVersion(): void
    {
        $tempDir = sys_get_temp_dir() . '/coriander-version-test-' . uniqid('', true);
        mkdir($tempDir, 0775, true);
        file_put_contents($tempDir . '/VERSION', "v9.9.9\n");

        try {
            $service = new FrameworkVersionService($tempDir, 'VERSION');
            $command = new Version($service);

            ob_start();
            $exitCode = $command->execute([]);
            $output = (string) ob_get_clean();

            $this->assertSame(CommandExitCode::SUCCESS, $exitCode);
            $this->assertStringContainsString('Framework version:', $output);
            $this->assertStringContainsString('v9.9.9', $output);
        } finally {
            @unlink($tempDir . '/VERSION');
            @rmdir($tempDir);
        }
    }
}
