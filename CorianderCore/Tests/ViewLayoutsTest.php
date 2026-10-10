<?php
declare(strict_types=1);

namespace CorianderCore\Tests;

use CorianderCore\Core\Router\ViewRenderer;

class ViewLayoutsTest extends RouteFixtureTestCase
{
    public function testNearestPairSeparatesAdminAndPublicAndSharesEscapedData(): void
    {
        $this->put('_header.php', 'echo "public:" . $title;');
        $this->put('_footer.php', 'echo ":footer:" . $title;');
        $this->put('home.php', 'echo ":" . $items["name"];');
        $this->put('admin/_header.php', 'echo "admin:" . $title;');
        $this->put('admin/_footer.php', 'echo ":adminfooter";');
        $this->put('admin/users/show.php', 'echo ":" . $items["name"];');
        $renderer = new ViewRenderer($this->directory);
        $data = ['title' => '<title>', 'items' => ['name' => '<name>']];
        self::assertSame('public:&lt;title&gt;:&lt;name&gt;:footer:&lt;title&gt;', (string) $renderer->response('home', $data)->getBody());
        self::assertSame('admin:&lt;title&gt;:&lt;name&gt;:adminfooter', (string) $renderer->response('admin/users/show', $data)->getBody());
        self::assertSame('public:new:&lt;name&gt;:footer:new', (string) $renderer->response('home.php', ['title' => 'new', 'items' => $data['items']])->getBody());
    }

    public function testBareContentAndExplicitFragments(): void
    {
        $this->put('home.php', 'echo "body";');
        $renderer = new ViewRenderer($this->directory);
        self::assertSame('body', (string) $renderer->response('home')->getBody());
        $this->put('_header.php', 'echo "header";');
        $this->put('_footer.php', 'echo "footer";');
        $response = $renderer->response('home', status: 201, layout: false);
        self::assertSame('body', (string) $response->getBody());
        self::assertSame(201, $response->getStatusCode());
        self::assertSame('text/html; charset=utf-8', $response->getHeaderLine('Content-Type'));
    }

    public function testIncompleteChildPairCannotMixWithParent(): void
    {
        $this->put('_header.php', '');
        $this->put('_footer.php', '');
        $this->put('admin/_header.php', '');
        $this->put('admin/home.php', '');
        $this->expectExceptionMessage('Incomplete view layout');
        (new ViewRenderer($this->directory))->response('admin/home');
    }

    public function testUserDataCannotOverwriteRendererFileList(): void
    {
        $this->put('home.php', 'echo $name;');
        $this->put('other.php', 'echo "wrong";');
        self::assertSame('&lt;safe&gt;', (string) (new ViewRenderer($this->directory))->response('home', [
            'name' => '<safe>', '__files' => [$this->directory . '/other.php'], '__data' => [], '__file' => 'other.php',
        ])->getBody());
    }

    public function testUnsafeAndMissingViewsAreRejectedWithoutOutput(): void
    {
        $renderer = new ViewRenderer($this->directory);
        $level = ob_get_level();
        foreach (['../outside', '/absolute', 'C:/outside', "bad\0name", '_header', 'missing'] as $name) {
            try {
                $renderer->response($name);
                self::fail('Unsafe view accepted');
            } catch (\InvalidArgumentException | \RuntimeException) {
                self::assertSame($level, ob_get_level());
            }
        }
        $this->put('bad.php', 'echo "partial"; throw new \\RuntimeException("template failure");');
        try {
            $renderer->response('bad');
        } catch (\RuntimeException) {
            self::assertSame($level, ob_get_level());
        }
    }

    public function testSymlinkCannotIncludeTemplateOutsideViewRoot(): void
    {
        mkdir($this->directory . '/Views');
        $this->put('outside.php', 'echo "secret";');
        $link = $this->directory . '/Views/escape.php';
        if (!@symlink($this->directory . '/outside.php', $link)) {
            self::markTestSkipped('Symlink creation requires host privileges');
        }
        $this->expectExceptionMessage('escapes its directory');
        (new ViewRenderer($this->directory . '/Views'))->response('escape');
    }
}
