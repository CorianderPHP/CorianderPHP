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
        $url = PublicUrl::asset('assets/js/pages/' . implode('/', array_map('rawurlencode', explode('/', $name)))) . '?' . hash('sha256', 'console.log("fixture");');
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

    public function testContentChangesInvalidateGeneratedAndLegacyScriptsWithTheSameTimestamp(): void
    {
        $this->put('index.get.php', 'echo "body";');
        $this->script('index.js');
        $file = $this->directory . '/assets/index.js';
        $renderer = $this->renderer();
        $request = new ServerRequest('GET', '/');
        foreach ([false, true] as $generated) {
            $bodies = [];
            foreach (['console.log("one");', 'console.log("two");'] as $content) {
                $version = hash('sha256', $content);
                file_put_contents($file, ($generated ? '// coriander-version:' . $version . "\n" : '') . $content);
                touch($file, 1700000000);
                clearstatcache(true, $file);
                $bodies[] = (string) $renderer->dispatch('index.get.php', $request)->getBody();
                self::assertStringContainsString('index.js?' . $version, end($bodies));
            }
            self::assertNotSame($bodies[0], $bodies[1]);
            touch($file, 1700000001);
            clearstatcache(true, $file);
            self::assertSame($bodies[1], (string) $renderer->dispatch('index.get.php', $request)->getBody());
        }
        file_put_contents($file, "// coriander-version:invalid\nconsole.log('manual');");
        self::assertStringContainsString('index.js?' . hash_file('sha256', $file),
            (string) $renderer->dispatch('index.get.php', $request)->getBody());
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


    public function testPrebuiltDynamicModulesWorkOverHttpWithoutNodeOrSources(): void
    {
        $this->put('src/Views/teams/[team]/users/[id].get.php', 'echo "user:" . htmlspecialchars($request->getAttribute("id"), ENT_QUOTES, "UTF-8");');
        $this->put('src/Views/json.get.php', 'return static fn($r) => \\CorianderCore\\Core\\Http\\Responses::json(["ok" => true]);');
        $this->put('src/Views/redirect.get.php', 'return static fn($r) => \\CorianderCore\\Core\\Http\\Responses::redirect("/target");');
        $this->put('src/Views/index.post.php', 'return static fn($r) => \\CorianderCore\\Core\\Http\\Responses::html("posted");');
        $assets = $this->directory . '/public/assets/js/pages';
        $names = ['_script.js', 'teams/_script.js', 'teams/[team]/users/[id].js'];
        foreach ($names as $name) {
            $file = $assets . '/' . $name;
            if (!is_dir(dirname($file))) { mkdir(dirname($file), 0775, true); }
            file_put_contents($file, 'console.log("prebuilt");');
        }
        $this->put('public/index.php', 'define("PROJECT_ROOT", dirname(__DIR__)); require ' . var_export(PROJECT_ROOT . '/vendor/autoload.php', true) . '; require ' . var_export(PROJECT_ROOT . '/config/config.php', true) . ';
            $path = parse_url($_SERVER["REQUEST_URI"], PHP_URL_PATH);
            if (str_starts_with($path, "/assets/")) {
                \\CorianderCore\\Core\\Router\\SafePath::resolveFile(__DIR__, ltrim(rawurldecode($path), "/"));
                return false;
            }
            $response = (new \\CorianderCore\\Core\\Router\\Router())->handle(\\CorianderCore\\Core\\Http\\RequestFactory::fromGlobals());
            \\CorianderCore\\Core\\Http\\ResponseEmitter::emit($response, $_SERVER["REQUEST_METHOD"]);');
        $server = new \CorianderCore\Tests\Support\HttpServer($this->directory . '/public', $this->directory . '/public/index.php',
            ['APP_ENV' => 'production', 'PUBLIC_URL_PREFIX' => '/']);
        try {
            $first = $server->request('GET', '/teams/7/users/42');
            self::assertSame(200, $first['status']);
            self::assertStringStartsWith('user:42<script', $first['body']);
            preg_match_all('/<script type="module" src="([^"]+)"><\/script>/', $first['body'], $matches);
            self::assertCount(3, $matches[1]);
            self::assertStringContainsString('teams/%5Bteam%5D/users/%5Bid%5D.js?', $matches[1][2]);
            foreach ($matches[1] as $url) {
                $asset = $server->request('GET', html_entity_decode($url));
                self::assertSame(200, $asset['status']);
                self::assertSame('console.log("prebuilt");', $asset['body']);
            }
            $second = $server->request('GET', '/teams/9/users/99');
            self::assertSame(str_replace('user:42', 'user:99', $first['body']), $second['body']);
            $head = $server->request('HEAD', '/teams/7/users/42');
            self::assertSame(200, $head['status']);
            self::assertSame('', $head['body']);
            self::assertSame('{"ok":true}', $server->request('GET', '/json')['body']);
            self::assertSame('', $server->request('GET', '/redirect')['body']);
            self::assertSame('posted', $server->request('POST', '/')['body']);
            foreach ($names as $name) { unlink($assets . '/' . $name); }
            self::assertSame('user:42', $server->request('GET', '/teams/7/users/42')['body']);
            self::assertDirectoryDoesNotExist($this->directory . '/nodejs');
            self::assertFileDoesNotExist($this->directory . '/src/Views/teams/[team]/users/[id].ts');
        } finally { $server->stop(); }
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
