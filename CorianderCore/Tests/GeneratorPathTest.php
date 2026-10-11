<?php
declare(strict_types=1);
namespace CorianderCore\Tests;

use CorianderCore\Core\Router\SafePath;
use CorianderCore\Core\Console\{CommandExitCode, Commands\Make\Route\MakeRoute, Commands\Make\View\MakeView, Commands\Make\Sitemap\MakeSitemap};
use CorianderCore\Core\Support\OutputBuffer;

class GeneratorPathTest extends RouteFixtureTestCase
{
    public function testGeneratorsNeverFollowDanglingDestinationSymlinks(): void
    {
        $root = $this->directory . '/root';
        $outside = $this->directory . '/outside';
        mkdir($root);
        mkdir($outside);
        $generators = [
            'page.get.php' => fn() => (new MakeView($root))->execute(['page.get']),
            'handler.get.php' => fn() => (new MakeRoute($root))->execute(['handler.get']),
            'sitemap.xml.get.php' => fn() => (new MakeSitemap($root))->execute(),
        ];
        foreach ($generators as $name => $generate) {
            if (!@symlink($outside . '/' . $name, $root . '/' . $name)) {
                self::markTestSkipped('Symlink creation requires host privileges');
            }
            [$code, $output] = OutputBuffer::capture($generate);
            self::assertSame(CommandExitCode::FAILURE, $code);
            self::assertStringContainsString('symlink', $output);
            self::assertFileDoesNotExist($outside . '/' . $name);
            unlink($root . '/' . $name);
        }
    }

    public function testEscapingSymlinkIsRejectedBeforeCreatingNestedDirectories(): void
    {
        mkdir($this->directory . '/root');
        mkdir($this->directory . '/outside');
        $link = $this->directory . '/root/link';
        if (!@symlink($this->directory . '/outside', $link)) {
            self::markTestSkipped('Symlink creation requires host privileges');
        }
        try {
            SafePath::destination($this->directory . '/root', 'link/new/file.php');
            self::fail('Escaping symlink accepted');
        } catch (\RuntimeException $e) {
            self::assertStringContainsString('escapes', $e->getMessage());
            self::assertDirectoryDoesNotExist($this->directory . '/outside/new');
            [$code, $output] = OutputBuffer::capture(fn() => (new MakeRoute($this->directory . '/root'))->execute(['link/new/index.get']));
            self::assertSame(CommandExitCode::FAILURE, $code);
            self::assertStringContainsString('escapes', $output);
            self::assertDirectoryDoesNotExist($this->directory . '/outside/new');
            [$code, $output] = OutputBuffer::capture(fn() => (new MakeView($this->directory . '/root'))->execute(['link/new/page']));
            self::assertSame(CommandExitCode::FAILURE, $code);
            self::assertStringContainsString('escapes', $output);
            self::assertDirectoryDoesNotExist($this->directory . '/outside/new');
        } finally { unlink($link); }
    }
}
