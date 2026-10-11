<?php
declare(strict_types=1);
namespace CorianderCore\Tests;

use CorianderCore\Core\Router\ViewRenderer;
use CorianderCore\Core\Support\PublicUrl;
use Nyholm\Psr7\ServerRequest;

class PageScriptsTest extends RouteFixtureTestCase
{
    private function script(string $name): void
    {
        $file = $this->directory . '/assets/' . $name;
        if (!is_dir(dirname($file))) {
            mkdir(dirname($file), 0775, true);
        }
        file_put_contents($file, 'console.log("fixture");');
        touch($file, 1700000000);
    }

    private function renderer(): ViewRenderer
    {
        return new ViewRenderer($this->directory, $this->directory . '/assets');
    }

    private function tag(string $name): string
    {
        $url = PublicUrl::asset('assets/js/pages/' . implode('/', array_map('rawurlencode', explode('/', $name)))) . '?1700000000';
        return '<script type="module" src="' . htmlspecialchars($url, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '"></script>';
    }

    public function testDirectoryScriptsAccumulateBeforeTheFooterAndPageRunsLast(): void
    {
        $this->put('_header.php', 'echo "header:";');
        $this->put('_footer.php', 'echo ":footer";');
        $this->put('teams/[team]/users/[id].get.php', 'echo $request->getAttribute("id");');
        foreach (['_script.js', 'teams/_script.js', 'teams/[team]/_script.js',
            'teams/[team]/users/_script.js', 'teams/[team]/users/[id].js'] as $name) {
            $this->script($name);
        }
        $expected = 'header:42' . $this->tag('_script.js') . $this->tag('teams/_script.js')
            . $this->tag('teams/[team]/_script.js') . $this->tag('teams/[team]/users/_script.js')
            . $this->tag('teams/[team]/users/[id].js') . ':footer';
        $response = $this->renderer()->dispatch('teams/[team]/users/[id].get.php', (new ServerRequest('GET', '/'))->withAttribute('id', '42'));
        self::assertSame($expected, (string) $response->getBody());
        self::assertStringNotContainsString('/42.js', $expected);
    }

    public function testMissingAssetsAndMissingLayoutsAreOptional(): void
    {
        $this->put('index.get.php', 'echo "body";');
        self::assertSame('body', (string) $this->renderer()->dispatch('index.get.php', new ServerRequest('GET', '/'))->getBody());
        $this->script('index.js');
        $expected = 'body' . $this->tag('index.js');
        self::assertSame($expected, (string) $this->renderer()->dispatch('index.get.php', new ServerRequest('GET', '/'))->getBody());
        $this->put('_header.php', 'echo "header:";');
        self::assertSame('header:' . $expected, (string) $this->renderer()->dispatch('index.get.php', new ServerRequest('GET', '/'))->getBody());
        unlink($this->directory . '/_header.php');
        $this->put('_footer.php', 'echo ":footer";');
        self::assertSame($expected . ':footer', (string) $this->renderer()->dispatch('index.get.php', new ServerRequest('GET', '/'))->getBody());
    }

    public function testFragmentsAndExplicitHandlersKeepTheirResponses(): void
    {
        $this->script('_script.js');
        $this->script('json.js');
        $this->put('json.get.php', 'return static fn($r) => \\CorianderCore\\Core\\Http\\Responses::json(["ok" => true]);');
        $this->put('redirect.get.php', 'return static fn($r) => \\CorianderCore\\Core\\Http\\Responses::redirect("/target");');
        $this->put('post.post.php', 'return static fn($r) => \\CorianderCore\\Core\\Http\\Responses::html("explicit");');
        $this->put('fragment.php', 'echo "fragment";');
        $renderer = $this->renderer();
        self::assertSame('{"ok":true}', (string) $renderer->dispatch('json.get.php', new ServerRequest('GET', '/'))->getBody());
        $redirect = $renderer->dispatch('redirect.get.php', new ServerRequest('GET', '/'));
        self::assertSame('', (string) $redirect->getBody());
        self::assertSame('/target', $redirect->getHeaderLine('Location'));
        self::assertSame('explicit', (string) $renderer->dispatch('post.post.php', new ServerRequest('POST', '/'))->getBody());
        self::assertSame('fragment', (string) $renderer->response('fragment', layout: false)->getBody());
        self::assertSame('fragment' . $this->tag('_script.js'), (string) $renderer->response('fragment')->getBody());
    }

    public function testCustomViewsDoNotUseAnotherApplicationsAssetsByDefault(): void
    {
        $this->put('index.get.php', 'echo "custom";');
        $this->script('index.js');
        self::assertSame('custom', (string) (new ViewRenderer($this->directory))->dispatch('index.get.php', new ServerRequest('GET', '/'))->getBody());
    }

    public function testAssetNamesAreUrlEncodedAndPrivateTemplatesHaveNoPageBundle(): void
    {
        $this->put('space & quote\'/index.get.php', 'echo "body";');
        $this->script('space & quote\'/index.js');
        self::assertSame('body' . $this->tag('space & quote\'/index.js'), (string) $this->renderer()->dispatch('space & quote\'/index.get.php', new ServerRequest('GET', '/'))->getBody());
        $this->put('_private.php', 'echo "private";');
        $this->script('_private.js');
        self::assertSame('private', (string) $this->renderer()->response('_private')->getBody());
    }

    public function testScriptSymlinksCannotEscapeTheirRoot(): void
    {
        $this->put('index.get.php', 'echo "body";');
        $this->script('_script.js');
        file_put_contents($this->directory . '/outside.js', 'secret');
        if (!@symlink($this->directory . '/outside.js', $this->directory . '/assets/index.js')) {
            self::markTestSkipped('Symlink creation requires host privileges');
        }
        $this->expectExceptionMessage('escapes its directory');
        $this->renderer()->dispatch('index.get.php', new ServerRequest('GET', '/'));
    }
}
