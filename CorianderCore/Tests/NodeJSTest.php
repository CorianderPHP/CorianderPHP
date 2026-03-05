<?php
declare(strict_types=1);

namespace CorianderCore\Tests;

use CorianderCore\Core\Console\Commands\NodeJS;
use PHPUnit\Framework\TestCase;

class NodeJSTest extends TestCase
{
    public function testExecuteWithoutArgsPrintsError(): void
    {
        $command = new NodeJS();

        ob_start();
        $command->execute([]);
        $output = (string) ob_get_clean();

        $this->assertStringContainsString('Please provide a Node.js command to run', $output);
    }

    public function testWatchCommandPrintsStartupMessageAndForwardsChildOutput(): void
    {
        $command = new NodeJS(PHP_BINARY);

        ob_start();
        $command->execute(['run', 'watch-tw']);
        $output = (string) ob_get_clean();

        $this->assertStringContainsString('Starting watcher... Press Ctrl+C to stop.', $output);
        $this->assertStringContainsString('Could not open input file', $output);
        $this->assertStringContainsString('npm command failed with exit code', $output);
    }
}
