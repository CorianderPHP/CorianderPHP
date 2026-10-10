<?php
declare(strict_types=1);

namespace CorianderCore\Tests;

use CorianderCore\Core\Router\ViewRenderer;

class ViewLayoutsTest extends RouteFixtureTestCase
{
    public function testNearestLayoutsSeparateAdminAndPublicAndShareEscapedData(): void
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

    public function testChildHeaderInheritsRootFooter(): void
    {
        $this->put('_header.php', 'echo "rootheader:";');
        $this->put('_footer.php', 'echo ":rootfooter:" . $title;');
        $this->put('admin/_header.php', 'echo "adminheader:" . $title . ":";');
        $this->put('admin/users/home.php', 'echo "body";');
        self::assertSame('adminheader:&lt;title&gt;:body:rootfooter:&lt;title&gt;', (string)
            (new ViewRenderer($this->directory))->response('admin/users/home', ['title' => '<title>'])->getBody());
    }

    public function testChildFooterInheritsRootHeader(): void
    {
        $this->put('_header.php', 'echo "rootheader:";');
        $this->put('_footer.php', 'echo ":rootfooter";');
        $this->put('admin/_footer.php', 'echo ":adminfooter";');
        $this->put('admin/users/home.php', 'echo "body";');
        self::assertSame('rootheader:body:adminfooter', (string)
            (new ViewRenderer($this->directory))->response('admin/users/home')->getBody());
    }

    public function testHeaderAndFooterCanComeFromDifferentNestedAncestors(): void
    {
        $this->put('_header.php', 'echo "rootheader:";');
        $this->put('_footer.php', 'echo ":rootfooter";');
        $this->put('admin/_header.php', 'echo "adminheader:";');
        $this->put('admin/users/_footer.php', 'echo ":usersfooter";');
        $this->put('admin/users/profile/home.php', 'echo "body";');
        self::assertSame('adminheader:body:usersfooter', (string)
            (new ViewRenderer($this->directory))->response('admin/users/profile/home')->getBody());
    }

    public function testHeaderWithoutAnyFooterAndFooterWithoutAnyHeader(): void
    {
        $this->put('header-only/_header.php', 'echo "header:";');
        $this->put('header-only/pages/home.php', 'echo "body";');
        $this->put('footer-only/_footer.php', 'echo ":footer";');
        $this->put('footer-only/pages/home.php', 'echo "body";');
        $renderer = new ViewRenderer($this->directory);
        self::assertSame('header:body', (string) $renderer->response('header-only/pages/home')->getBody());
        self::assertSame('body:footer', (string) $renderer->response('footer-only/pages/home')->getBody());
        self::assertSame('body', (string) $renderer->response('header-only/pages/home', layout: false)->getBody());
        self::assertSame('body', (string) $renderer->response('footer-only/pages/home', layout: false)->getBody());
    }

    public function testExistingInvalidLayoutFileIsNotSilentlyOmitted(): void
    {
        $this->put('home.php', 'echo "body";');
        mkdir($this->directory . '/_header.php');
        $this->expectExceptionMessage('Required application file is missing or invalid');
        (new ViewRenderer($this->directory))->response('home');
    }

    public function testLayoutSymlinksCannotEscapeViewRoot(): void
    {
        $this->put('outside.php', 'echo "secret";');
        foreach (['_header.php', '_footer.php'] as $part) {
            $root = $this->directory . '/' . $part . '-views';
            mkdir($root);
            $this->put($part . '-views/home.php', 'echo "body";');
            if (!@symlink($this->directory . '/outside.php', $root . '/' . $part)) {
                self::markTestSkipped('Symlink creation requires host privileges');
            }
            try {
                (new ViewRenderer($root))->response('home');
                self::fail('Escaping layout symlink accepted');
            } catch (\RuntimeException $error) {
                self::assertStringContainsString('escapes its directory', $error->getMessage());
            }
        }
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
