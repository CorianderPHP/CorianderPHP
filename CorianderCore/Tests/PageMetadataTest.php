<?php
declare(strict_types=1);
namespace CorianderCore\Tests;

use CorianderCore\Core\Router\{Router, ViewRenderer};
use Nyholm\Psr7\ServerRequest;

class PageMetadataTest extends RouteFixtureTestCase
{
    public function testParentDefaultsAndShallowChildOverridesAreEscapedOnceForAllPageParts(): void
    {
        $counter = $this->directory . '/metadata-calls';
        $this->put('_metadata.php', 'file_put_contents(' . var_export($counter, true) . ', "root", FILE_APPEND); return ["title" => "<root>", "description" => "shared", "nested" => ["root" => 1]];');
        $this->put('admin/_metadata.php', 'file_put_contents(' . var_export($counter, true) . ', "child", FILE_APPEND); return ["title" => "<admin>", "nested" => ["child" => 2]];');
        $this->put('_header.php', 'echo "header:" . $metadata["title"] . ":";');
        $this->put('_footer.php', 'echo ":footer:" . $metadata["title"];');
        $this->put('admin/users/[id]/index.get.php', 'echo $metadata["description"] . ":" . json_encode($metadata["nested"]);');
        $response = (new Router($this->directory))->handle(new ServerRequest('GET', '/admin/users/42'));
        self::assertSame('header:&lt;admin&gt;:shared:{"child":2}:footer:&lt;admin&gt;', (string) $response->getBody());
        self::assertSame('rootchild', file_get_contents($counter));
    }

    public function testExplicitMetadataOverridesDefaultsWithoutOverwritingRendererFiles(): void
    {
        $this->put('_metadata.php', 'return ["title" => "<default>", "description" => "shared", "__files" => ["wrong.php"], "request" => "wrong"];');
        $this->put('home.php', 'echo $metadata["title"] . ":" . $metadata["description"] . ":" . $name;');
        self::assertSame('&lt;override&gt;:shared:&lt;name&gt;', (string) (new ViewRenderer($this->directory))->response('home', [
            'metadata' => ['title' => '<override>'], 'name' => '<name>', '__files' => ['wrong.php'],
        ])->getBody());
        $this->put('users/[id].get.php', 'echo $request->getAttribute("id");');
        self::assertSame('42', (string) (new Router($this->directory))->handle(new ServerRequest('GET', '/users/42'))->getBody());
    }

    public function testApplicationPageObjectIsPreservedAcrossLayoutsAndFragments(): void
    {
        $this->put('_metadata.php', 'return ["title" => "<default>", "description" => "shared"];');
        $code = 'echo spl_object_id($page) . ":" . $metadata["title"] . ":" . $metadata["description"] . ";";';
        foreach (['_header.php', 'home.php', '_footer.php'] as $file) {
            $this->put($file, $code);
        }
        $page = (object) ['title' => '<document>'];
        foreach ([true, false] as $layout) {
            $response = (new ViewRenderer($this->directory))->response('home', [
                'page' => $page, 'metadata' => ['title' => '<override>'],
            ], layout: $layout);
            self::assertSame(str_repeat(spl_object_id($page) . ':&lt;override&gt;:shared;', $layout ? 3 : 1), (string) $response->getBody());
        }
        self::assertSame('<document>', $page->title);
    }

    public function testApplicationPageValuesDoNotOverrideMetadata(): void
    {
        $this->put('_metadata.php', 'return ["title" => "<metadata>"];');
        $this->put('home.php', 'echo $metadata["title"] . ":" . (is_array($page) ? $page["title"] : ($page ?? "null"));');
        foreach ([['title' => '<app>'], '<app>', null] as $page) {
            self::assertSame('&lt;metadata&gt;:' . ($page === null ? 'null' : '&lt;app&gt;'), (string) (new ViewRenderer($this->directory))->response('home', ['page' => $page])->getBody());
        }
    }

    public function testDirectPagesCanUsePageWithoutAFrameworkAlias(): void
    {
        $this->put('_metadata.php', 'return ["title" => "<metadata>"];');
        $this->put('index.get.php', 'echo array_key_exists("page", get_defined_vars()) ? "reserved" : "free"; $page = (object) ["id" => 42]; echo ":" . $page->id . ":" . $metadata["title"];');
        self::assertSame('free:42:&lt;metadata&gt;', (string) (new Router($this->directory))->handle(new ServerRequest('GET', '/'))->getBody());
    }

    public function testMissingAndLegacyMetadataAreIgnoredAndEditsAreVisibleWithCachedRoutes(): void
    {
        $this->put('index.get.php', 'echo $metadata["title"] ?? "empty";');
        $this->put('metadata.php', 'throw new \\RuntimeException("legacy metadata is not loaded");');
        $router = new Router($this->directory);
        self::assertSame('empty', (string) $router->handle(new ServerRequest('GET', '/'))->getBody());
        $this->put('_metadata.php', 'return ["title" => "new"];');
        self::assertSame('new', (string) $router->handle(new ServerRequest('GET', '/'))->getBody());
        $this->put('_metadata.php', 'return ["title" => "edited"];');
        self::assertSame('edited', (string) $router->handle(new ServerRequest('GET', '/'))->getBody());
        unlink($this->directory . '/_metadata.php');
        self::assertSame('empty', (string) $router->handle(new ServerRequest('GET', '/'))->getBody());
    }

    public function testInvalidMetadataAndPrintedOutputNameTheSourceAndCleanBuffers(): void
    {
        $this->put('index.get.php', 'echo "page";');
        $level = ob_get_level();
        foreach (['return "invalid";', 'echo "leak"; return [];'] as $code) {
            $this->put('_metadata.php', $code);
            try {
                (new Router($this->directory))->handle(new ServerRequest('GET', '/'));
            } catch (\UnexpectedValueException $error) {
                self::assertStringContainsString('_metadata.php', $error->getMessage());
                self::assertSame($level, ob_get_level());
                continue;
            }
            self::fail('Invalid metadata accepted');
        }
    }

    public function testNonGetHandlersSkipMetadataAndGetResponsesRemainUndecorated(): void
    {
        $this->put('_metadata.php', 'return ["title" => "<title>"];');
        $this->put('_header.php', 'throw new \\RuntimeException("must not render");');
        $this->put('index.get.php', 'return static fn($r) => \\CorianderCore\\Core\\Http\\Responses::json(["id" => 42]);');
        $this->response('index.post.php', 'posted');
        $router = new Router($this->directory);
        self::assertSame('{"id":42}', (string) $router->handle(new ServerRequest('GET', '/'))->getBody());
        $this->put('_metadata.php', 'throw new \\RuntimeException("must not load on POST");');
        self::assertSame('posted', (string) $router->handle(new ServerRequest('POST', '/'))->getBody());
    }

    public function testInvalidExplicitMetadataIsRejected(): void
    {
        $this->put('home.php', 'echo "home";');
        $this->expectExceptionMessage('View metadata must be an array');
        (new ViewRenderer($this->directory))->response('home', ['metadata' => 'invalid']);
    }

    public function testReservedHelpersCannotBeRenderedExplicitly(): void
    {
        foreach (['_header', '_footer', '_middleware', '_metadata'] as $name) {
            $this->put($name . '.php', 'throw new \\RuntimeException("must not load");');
            try {
                (new ViewRenderer($this->directory))->response($name);
            } catch (\InvalidArgumentException $error) {
                self::assertSame('Invalid view name', $error->getMessage());
                continue;
            }
            self::fail('Reserved helper rendered');
        }
    }

    public function testMetadataSymlinkCannotEscapeThePageRoot(): void
    {
        mkdir($this->directory . '/Views');
        $this->put('Views/index.get.php', 'echo "page";');
        $this->put('outside.php', 'return ["title" => "secret"];');
        if (!@symlink($this->directory . '/outside.php', $this->directory . '/Views/_metadata.php')) {
            self::markTestSkipped('Symlink creation requires host privileges');
        }
        $this->expectExceptionMessage('escapes its directory');
        (new Router($this->directory . '/Views'))->handle(new ServerRequest('GET', '/'));
    }
}
