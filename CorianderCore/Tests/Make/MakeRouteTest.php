<?php
declare(strict_types=1);

namespace CorianderCore\Tests\Make;

use CorianderCore\Core\Console\CommandExitCode;
use CorianderCore\Core\Console\Commands\Make\Route\MakeRoute;
use CorianderCore\Core\Utils\DirectoryHandler;
use PHPUnit\Framework\TestCase;

class MakeRouteTest extends TestCase
{
    private static string $testPath;

    private MakeRoute $makeRoute;

    public static function setUpBeforeClass(): void
    {
        self::$testPath = PROJECT_ROOT . '/CorianderCore/Tests/_tmp_make_route/';
    }

    protected function setUp(): void
    {
        if (!is_dir(self::$testPath)) {
            mkdir(self::$testPath, 0755, true);
        }

        $this->makeRoute = new MakeRoute(self::$testPath . 'src/Routes/');
    }

    protected function tearDown(): void
    {
        if (is_dir(self::$testPath)) {
            DirectoryHandler::deleteDirectory(self::$testPath);
        }
    }

    public function testCreateRouteFileSuccessfully(): void
    {
        $exitCode = $this->makeRoute->execute(['admin']);

        $this->expectOutputRegex('/Success/');
        $this->assertSame(CommandExitCode::SUCCESS, $exitCode);
        $this->assertFileExists(self::$testPath . 'src/Routes/admin.get.php');
        $this->assertStringContainsString(
            'return static',
            (string) file_get_contents(self::$testPath . 'src/Routes/admin.get.php')
        );
    }

    public function testCreateNestedRouteFileSuccessfully(): void
    {
        $exitCode = $this->makeRoute->execute(['admin/users']);

        $this->expectOutputRegex('/Success/');
        $this->assertSame(CommandExitCode::SUCCESS, $exitCode);
        $this->assertFileExists(self::$testPath . 'src/Routes/admin/users.get.php');
    }

    public function testRouteFileAlreadyExists(): void
    {
        $this->makeRoute->execute(['admin']);

        $exitCode = $this->makeRoute->execute(['admin']);

        $this->expectOutputRegex('/already exists/');
        $this->assertSame(CommandExitCode::FAILURE, $exitCode);
    }

    public function testDynamicMethodHandlerRunsWithoutRegistration(): void
    {
        $this->expectOutputRegex('/Success/');
        self::assertSame(0, $this->makeRoute->execute(['users/[id].post']));
        $router = new \CorianderCore\Core\Router\Router(self::$testPath . 'src/Routes');
        $response = $router->handle(new \Nyholm\Psr7\ServerRequest('POST', '/users/42'));
        self::assertSame('Hello', (string) $response->getBody());
        self::assertSame(200, $response->getStatusCode());
    }

    public function testNoRouteNameProvided(): void
    {
        $exitCode = $this->makeRoute->execute([]);

        $this->expectOutputRegex('/Please specify a route file name/');
        $this->assertSame(CommandExitCode::INVALID_USAGE, $exitCode);
    }

    public function testInvalidRouteNameIsRejected(): void
    {
        $exitCode = $this->makeRoute->execute(['../admin']);

        $this->expectOutputRegex('/Invalid route file name/');
        $this->assertSame(CommandExitCode::INVALID_USAGE, $exitCode);
    }
}
