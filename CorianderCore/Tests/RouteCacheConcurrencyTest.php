<?php
declare(strict_types=1);
namespace CorianderCore\Tests;

class RouteCacheConcurrencyTest extends RouteFixtureTestCase
{
    public function testConcurrentColdRequestsPublishOneCompleteMap(): void
    {
        $this->response('index.get.php');
        $processes = [];
        for ($i = 0; $i < 2; $i++) {
            $process = proc_open([PHP_BINARY, __DIR__ . '/fixtures/cache-worker.php', $this->directory,
                $this->directory . '/map.php', $this->directory . '/builds.txt'],
                [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, PROJECT_ROOT, null, ['bypass_shell' => true]);
            self::assertIsResource($process);
            fclose($pipes[0]);
            $processes[] = [$process, $pipes];
        }
        foreach ($processes as [$process, $pipes]) {
            $output = stream_get_contents($pipes[1]);
            $error = stream_get_contents($pipes[2]);
            fclose($pipes[1]); fclose($pipes[2]);
            self::assertSame(0, proc_close($process), $error);
            self::assertSame('complete', $output);
        }
        self::assertSame("build\n", file_get_contents($this->directory . '/builds.txt'));
        self::assertSame([], glob($this->directory . '/map.php.*.tmp'));
    }
}
