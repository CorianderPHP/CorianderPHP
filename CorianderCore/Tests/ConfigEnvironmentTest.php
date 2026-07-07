<?php
declare(strict_types=1);

namespace CorianderCore\Tests;

use PHPUnit\Framework\TestCase;

class ConfigEnvironmentTest extends TestCase
{
    public function testDatabaseConstantsCanBeLoadedFromEnvironment(): void
    {
        $output = $this->runPhpCode(<<<'PHP'
putenv('DB_TYPE=mysql');
putenv('DB_HOST=127.0.0.1');
putenv('DB_PORT=3307');
putenv('DB_CHARSET=utf8mb4');
putenv('DB_NAME=app');
putenv('DB_USER=root');
putenv('DB_PASSWORD=secret');
require 'config/config.php';
echo DB_TYPE . '|' . DB_HOST . '|' . DB_PORT . '|' . DB_CHARSET . '|' . DB_NAME . '|' . DB_USER . '|' . DB_PASSWORD;
PHP);

        $this->assertSame('mysql|127.0.0.1|3307|utf8mb4|app|root|secret', $output);
    }

    public function testPredefinedDatabaseConstantsWinOverEnvironment(): void
    {
        $output = $this->runPhpCode(<<<'PHP'
define('DB_TYPE', 'sqlite');
putenv('DB_TYPE=mysql');
require 'config/config.php';
echo DB_TYPE;
PHP);

        $this->assertSame('sqlite', $output);
    }

    private function runPhpCode(string $code): string
    {
        $process = proc_open(
            [PHP_BINARY, '-r', $code],
            [
                1 => ['pipe', 'w'],
                2 => ['pipe', 'w'],
            ],
            $pipes,
            PROJECT_ROOT
        );

        $this->assertIsResource($process);

        $stdout = stream_get_contents($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);

        $exitCode = proc_close($process);

        $this->assertSame(0, $exitCode, is_string($stderr) ? $stderr : '');

        return trim(is_string($stdout) ? $stdout : '');
    }
}
