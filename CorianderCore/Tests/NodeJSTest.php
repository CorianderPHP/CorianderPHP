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
}
