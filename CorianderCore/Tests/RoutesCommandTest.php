<?php
declare(strict_types=1);
namespace CorianderCore\Tests;

use CorianderCore\Core\Console\{CommandExitCode, CommandHandler};
use CorianderCore\Core\Console\Commands\Routes;

class RoutesCommandTest extends RouteFixtureTestCase
{
    private function listing(array $args = ['list']): array
    {
        ob_start();
        try {
            $code = (new Routes($this->directory))->execute($args);
            return [$code, ob_get_contents()];
        } finally { ob_end_clean(); }
    }

    public function testListsParametersMethodsAndOrderedMiddlewareWithoutExecutingFiles(): void
    {
        foreach (['index.get.php', 'teams/[team]/users/[id].get.php', 'teams/[team]/users/[id].post.php',
            '_middleware.php', 'teams/_middleware.php', 'teams/[team]/users/_middleware.php', '_hidden.get.php'] as $file) {
            $this->put($file, 'throw new \\RuntimeException("Must not execute");');
        }
        [$code, $output] = $this->listing();
        self::assertSame(CommandExitCode::SUCCESS, $code);
        self::assertStringContainsString('/teams/[team]/users/[id]', $output);
        self::assertStringContainsString('POST', $output);
        self::assertStringContainsString('HEAD (GET)', $output);
        self::assertStringContainsString('_middleware.php -> teams/_middleware.php -> teams/[team]/users/_middleware.php', $output);
        self::assertStringNotContainsString('_hidden', $output);
        self::assertLessThan(strpos($output, '/teams/'), strpos($output, 'index.get.php'));
        self::assertSame($output, $this->listing()[1]);
        self::assertFileDoesNotExist($this->directory . '/routes.php');
    }

    public function testExplicitHeadUsesItsOwnHandler(): void
    {
        $this->put('index.get.php', 'throw new \\RuntimeException();');
        $this->put('index.head.php', 'throw new \\RuntimeException();');
        [$code, $output] = $this->listing();
        self::assertSame(CommandExitCode::SUCCESS, $code);
        self::assertStringContainsString('index.head.php', $output);
        self::assertStringNotContainsString('HEAD (GET)', $output);
    }

    public function testEmptyInvalidUsageAndDiscoveryErrors(): void
    {
        self::assertSame([CommandExitCode::SUCCESS, 'No routes found.' . PHP_EOL], $this->listing());
        self::assertSame(CommandExitCode::INVALID_USAGE, $this->listing(['list', '--unknown'])[0]);
        $this->put('users.get.php', '');
        $this->put('users/index.get.php', '');
        [$code, $output] = $this->listing();
        self::assertSame(CommandExitCode::FAILURE, $code);
        self::assertStringContainsString('Duplicate GET route', $output);
    }

    public function testCommandDispatchAndHelp(): void
    {
        $handler = new CommandHandler();
        ob_start();
        try {
            self::assertSame(CommandExitCode::SUCCESS, $handler->handle('routes:list', []));
            self::assertStringContainsString('index.get.php', ob_get_contents());
            ob_clean();
            self::assertSame(CommandExitCode::SUCCESS, $handler->handle('help', ['routes']));
            self::assertStringContainsString('php coriander routes:list', ob_get_contents());
        } finally { ob_end_clean(); }
    }
}
