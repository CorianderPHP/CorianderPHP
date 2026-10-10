<?php
declare(strict_types=1);
namespace CorianderCore\Tests;

use CorianderCore\Core\Router\SafePath;

class GeneratorPathTest extends RouteFixtureTestCase
{
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
        } finally { unlink($link); }
    }
}
