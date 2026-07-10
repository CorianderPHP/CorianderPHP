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
        self::$testPath = PROJECT_ROOT . '/CorianderCore/tests/_tmp_make_route/';
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
        $this->assertFileExists(self::$testPath . 'src/Routes/admin.php');
        $this->assertStringContainsString(
            "\$router->get('admin'",
            (string) file_get_contents(self::$testPath . 'src/Routes/admin.php')
        );
    }

    public function testCreateNestedRouteFileSuccessfully(): void
    {
        $exitCode = $this->makeRoute->execute(['admin/users']);

        $this->expectOutputRegex('/Success/');
        $this->assertSame(CommandExitCode::SUCCESS, $exitCode);
        $this->assertFileExists(self::$testPath . 'src/Routes/admin/users.php');
    }

    public function testRouteFileAlreadyExists(): void
    {
        $this->makeRoute->execute(['admin']);

        $exitCode = $this->makeRoute->execute(['admin']);

        $this->expectOutputRegex('/already exists/');
        $this->assertSame(CommandExitCode::FAILURE, $exitCode);
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
