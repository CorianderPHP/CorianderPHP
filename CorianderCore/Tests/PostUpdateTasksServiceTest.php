<?php

declare(strict_types=1);

namespace CorianderCore\Tests;

use CorianderCore\Core\Console\Services\Updater\PostUpdateTasksService;
use PHPUnit\Framework\TestCase;

class PostUpdateTasksServiceTest extends TestCase
{
    public function testRunComposerDumpAutoloadReturnsStructuredResult(): void
    {
        $service = new PostUpdateTasksService();
        $result = $service->run(false);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('composer_dump_autoload', $result);
        $this->assertArrayHasKey('success', $result['composer_dump_autoload']);
        $this->assertArrayHasKey('exit_code', $result['composer_dump_autoload']);
        $this->assertArrayHasKey('output', $result['composer_dump_autoload']);
    }

    public function testRunWithClearCacheIncludesCacheResult(): void
    {
        $service = new PostUpdateTasksService();
        $result = $service->run(true);

        $this->assertArrayHasKey('cache_clear', $result);
        $this->assertNotNull($result['cache_clear']);
        $this->assertArrayHasKey('success', $result['cache_clear']);
        $this->assertArrayHasKey('exit_code', $result['cache_clear']);
        $this->assertArrayHasKey('output', $result['cache_clear']);
    }
}
