<?php
declare(strict_types=1);

namespace CorianderCore\Tests;

use CorianderCore\Core\Benchmark\BenchmarkHandler;
use CorianderCore\Core\Console\Commands\Benchmark\BenchmarkRouter;
use CorianderCore\Core\Support\OutputBuffer;
use PHPUnit\Framework\TestCase;

class BenchmarkRouterTest extends TestCase
{
    public function testUnknownRouteCannotBeMeasuredAsSuccess(): void
    {
        [$code, $output] = OutputBuffer::capture(fn() => (new BenchmarkRouter())->execute(['missing-benchmark-fixture']));
        self::assertSame(2, $code);
        self::assertStringContainsString('404', $output);
    }

    public function testMeasuresRegisteredHomepageAndReportsEnvironment(): void
    {
        [$code, $output] = OutputBuffer::capture(fn() => (new BenchmarkRouter())->execute(['home', '2']));
        self::assertSame(0, $code);
        self::assertStringContainsString('median_batch_mean_us', $output);
        self::assertStringContainsString(PHP_VERSION, $output);
    }

    public function testRepeatsTheOperationAndPropagatesFixtureFailures(): void
    {
        $calls = 0;
        $result = (new BenchmarkHandler())->measure(function () use (&$calls): void { $calls++; }, 3, 4);
        self::assertSame(13, $calls);
        self::assertSame(4, $result['samples']);
        $this->expectException(\RuntimeException::class);
        (new BenchmarkHandler())->measure(static fn() => throw new \RuntimeException('fixture failed'));
    }
}
