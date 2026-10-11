<?php
declare(strict_types=1);
namespace CorianderCore\Tests\Make;

use CorianderCore\Core\Console\Commands\Make\View\MakeView;
use CorianderCore\Core\Router\Router;
use CorianderCore\Core\Support\OutputBuffer;
use CorianderCore\Tests\RouteFixtureTestCase;
use Nyholm\Psr7\ServerRequest;

class MakeViewTest extends RouteFixtureTestCase
{
    private function generate(array $args): array
    {
        return OutputBuffer::capture(fn() => (new MakeView($this->directory))->execute($args));
    }

    #[\PHPUnit\Framework\Attributes\RunInSeparateProcess]
    public function testGeneratedPageRendersWithoutOpeningSession(): void
    {
        self::assertSame(0, $this->generate(['PublicPage'])[0]);
        $html = (string) (new Router($this->directory))->handle(new ServerRequest('GET', '/public-page'))->getBody();
        self::assertStringContainsString('<h1>public-page</h1>', $html);
        self::assertStringNotContainsString('<form', $html);
        self::assertStringNotContainsString('<!--', $html);
        self::assertSame(PHP_SESSION_NONE, session_status());
    }

    public function testRootDirectoryAndExplicitParameterPagesAreDiscovered(): void
    {
        foreach (['index' => 'index.get.php', 'about' => 'about/index.get.php',
            'AdminUser' => 'admin-user/index.get.php', 'admin/users/index.get' => 'admin/users/index.get.php',
            'teams/[teamId]/users/[id].get.php' => 'teams/[teamId]/users/[id].get.php'] as $input => $file) {
            self::assertSame(0, $this->generate([$input])[0]);
            self::assertFileExists($this->directory . '/' . $file);
        }
        $response = (new Router($this->directory))->handle(new ServerRequest('GET', '/teams/7/users/42'));
        self::assertSame(200, $response->getStatusCode());
        self::assertStringContainsString('teams/[teamId]/users/[id]', (string) $response->getBody());
        self::assertFileDoesNotExist($this->directory . '/about/metadata.php');
    }

    public function testExistingFilesAndIndexAliasesArePreserved(): void
    {
        self::assertSame(0, $this->generate(['about.get'])[0]);
        $original = file_get_contents($this->directory . '/about.get.php');
        self::assertSame(1, $this->generate(['about.get'])[0]);
        self::assertSame(1, $this->generate(['about'])[0]);
        self::assertSame($original, file_get_contents($this->directory . '/about.get.php'));
        self::assertDirectoryDoesNotExist($this->directory . '/about');
    }

    public function testInvalidNamesAndNonGetMethodsDoNotCreateDirectories(): void
    {
        foreach ([[], ['../escape'], ['_private/about'], ['users/[id]/[id]'], ['users/[bad-id]'], ['users/[id].post']] as $args) {
            self::assertSame(2, $this->generate($args)[0]);
        }
        self::assertSame([], array_values(array_diff(scandir($this->directory), ['.', '..'])));
    }
}
