<?php
declare(strict_types=1);

namespace CorianderCore\Tests;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use CorianderCore\Tests\Support\TestDirectoryHelperTrait;

class ConfigEnvironmentTest extends TestCase
{
    use TestDirectoryHelperTrait;

    #[DataProvider('applicationEnvironments')]
    public function testApplicationEnvironmentDefaultsAndOverrides(
        ?string $environment,
        bool $example,
        string $override,
        string $expectedEnvironment,
        string $expectedDebug,
    ): void {
        $root = $this->createTemporaryDirectory('_tmp_config_environment');
        try {
            mkdir($root . '/config');
            mkdir($root . '/CorianderCore/core/Bootstrap', 0777, true);
            copy(PROJECT_ROOT . '/config/config.php', $root . '/config/config.php');
            copy(PROJECT_ROOT . '/CorianderCore/core/Bootstrap/EnvLoader.php', $root . '/CorianderCore/core/Bootstrap/EnvLoader.php');
            if ($example) {
                copy(PROJECT_ROOT . '/.env-example', $root . '/.env-example');
            }
            if ($environment !== null) {
                file_put_contents($root . '/.env', $environment);
            }
            $output = $this->runPhpCode('define("PROJECT_ROOT", ' . var_export($root, true) . ');' . <<<'PHP'
foreach (['APP_ENV', 'APP_DEBUG'] as $name) {
    putenv($name);
    unset($_ENV[$name], $_SERVER[$name]);
}
PHP
                . $override . <<<'PHP'
putenv('LOG_CHANNEL=' . (PHP_OS_FAMILY === 'Windows' ? 'NUL' : '/dev/null'));
require PROJECT_ROOT . '/config/config.php';
require 'vendor/autoload.php';
(new \CorianderCore\Core\Router\RouteCache())->get();
$response = \CorianderCore\Core\Http\ErrorResponse::fromException(new \RuntimeException('private-details'));
echo json_encode([
    getenv('APP_ENV'), getenv('APP_DEBUG'), is_file(PROJECT_ROOT . '/cache/routes.php'),
    str_contains((string) $response->getBody(), 'private-details'),
]);
PHP);
            self::assertSame([
                $expectedEnvironment, $expectedDebug, $expectedEnvironment === 'production',
                $expectedEnvironment === 'local' && $expectedDebug === '1',
            ], json_decode($output, true, flags: JSON_THROW_ON_ERROR));
            if ($environment !== null || $example) {
                self::assertSame($environment ?? file_get_contents(PROJECT_ROOT . '/.env-example'), file_get_contents($root . '/.env'));
            } else {
                self::assertFileDoesNotExist($root . '/.env');
            }
        } finally {
            $this->deleteDirectory($root);
        }
    }

    /** @return array<string,array{?string,bool,string,string,string}> */
    public static function applicationEnvironments(): array
    {
        return [
            'no files' => [null, false, '', 'production', '0'],
            'automatic example copy' => [null, true, '', 'production', '0'],
            'existing local file' => ["APP_ENV=local\nAPP_DEBUG=1\n", true, '', 'local', '1'],
            'missing debug key' => ["APP_ENV=local\n", true, '', 'local', '0'],
            'missing environment key' => ["APP_DEBUG=1\n", false, '', 'production', '1'],
            'process override' => [null, true, "putenv('APP_ENV=local'); putenv('APP_DEBUG=1');", 'local', '1'],
            'ENV override' => [null, true, "\$_ENV['APP_ENV']='local'; \$_ENV['APP_DEBUG']='1';", 'local', '1'],
            'SERVER override' => [null, true, "\$_SERVER['APP_ENV']='local'; \$_SERVER['APP_DEBUG']='1';", 'local', '1'],
        ];
    }

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
