<?php
declare(strict_types=1);
namespace CorianderCore\Tests;

use CorianderCore\Core\Router\{Router, ViewRenderer};
use CorianderCore\Core\Http\ResponseEmitter;
use CorianderCore\Core\Support\OutputBuffer;
use Nyholm\Psr7\ServerRequest;

class DirectPagesTest extends RouteFixtureTestCase
{
    public function testHtmlAndNestedParametersRenderOnceWithInheritedLayouts(): void
    {
        $this->put('_header.php', 'echo "header:";');
        $this->put('_footer.php', 'echo ":footer";');
        $counter = $this->directory . '/counter';
        $this->put('teams/[team]/users/[id]/index.get.php', 'file_put_contents(' . var_export($counter, true) . ', "x", FILE_APPEND); echo htmlspecialchars($request->getAttribute("team") . ":" . $request->getAttribute("id"), ENT_QUOTES | ENT_SUBSTITUTE, "UTF-8");');
        $response = (new Router($this->directory))->handle(new ServerRequest('GET', '/teams/7/users/%3Cid%3E'));
        self::assertSame(200, $response->getStatusCode());
        self::assertSame('text/html; charset=utf-8', $response->getHeaderLine('Content-Type'));
        self::assertSame('header:7:&lt;id&gt;:footer', (string) $response->getBody());
        self::assertSame('x', file_get_contents($counter));
    }

    public function testPlainHtmlBarePagesAndHeadFallback(): void
    {
        file_put_contents($this->directory . '/index.get.php', '<h1>Welcome</h1>');
        $router = new Router($this->directory);
        self::assertSame('<h1>Welcome</h1>', (string) $router->handle(new ServerRequest('GET', '/'))->getBody());
        $response = $router->handle(new ServerRequest('HEAD', '/'));
        [, $output] = OutputBuffer::capture(fn() => ResponseEmitter::emit($response, 'HEAD'));
        self::assertSame('', $output);
        self::assertSame(405, $router->handle(new ServerRequest('POST', '/'))->getStatusCode());
    }

    public function testJsonRedirectAndPostHandlersSkipAutomaticLayouts(): void
    {
        $this->put('_header.php', 'throw new \\RuntimeException("must not load");');
        $this->put('_footer.php', 'throw new \\RuntimeException("must not load");');
        $this->put('json.get.php', 'return static fn($r) => \\CorianderCore\\Core\\Http\\Responses::json(["id" => 42]);');
        $this->put('redirect.get.php', 'return static fn($r) => \\CorianderCore\\Core\\Http\\Responses::redirect("/target");');
        $this->put('users/[id].post.php', 'return static fn($r) => \\CorianderCore\\Core\\Http\\Responses::json($r->getParsedBody());');
        $router = new Router($this->directory);
        self::assertSame('{"id":42}', (string) $router->handle(new ServerRequest('GET', '/json'))->getBody());
        $redirect = $router->handle(new ServerRequest('GET', '/redirect'));
        self::assertSame(303, $redirect->getStatusCode());
        self::assertSame('/target', $redirect->getHeaderLine('Location'));
        self::assertSame('', (string) $redirect->getBody());
        self::assertSame('{"name":"posted"}', (string) $router->handle((new ServerRequest('POST', '/users/42'))->withParsedBody(['name' => 'posted']))->getBody());
    }

    public function testInvalidReturnsAndMixedOutputAreRejectedWithoutLeakingBuffers(): void
    {
        $level = ob_get_level();
        $cases = ['mixed.get.php' => 'echo "leak"; return static fn($r) => new \\Nyholm\\Psr7\\Response();',
            'invalid.get.php' => 'echo "leak"; return [];',
            'template.post.php' => 'echo "leak";',
            'bad.get.php' => 'echo "leak"; throw new \\RuntimeException("template failure");'];
        foreach ($cases as $name => $code) {
            $this->put($name, $code);
        }
        $router = new Router($this->directory);
        foreach ($cases as $name => $code) {
            try {
                $router->handle(new ServerRequest(str_contains($name, '.post.') ? 'POST' : 'GET', '/' . explode('.', $name)[0]));
            } catch (\UnexpectedValueException | \RuntimeException $error) {
                self::assertSame($level, ob_get_level());
                if ($name !== 'bad.get.php') { self::assertStringContainsString($name, $error->getMessage()); }
                continue;
            }
            self::fail('Invalid execution accepted: ' . $name);
        }
    }

    public function testExplicitViewRenderingCannotExecuteMethodFiles(): void
    {
        $this->put('index.get.php', 'throw new \\RuntimeException("must not execute");');
        $this->expectExceptionMessage('Method files must be dispatched by the router');
        (new ViewRenderer($this->directory))->response('index.get');
    }

    public function testDirectPagesResolveHeaderAndFooterIndependentlyAcrossDirectories(): void
    {
        $this->put('_header.php', 'echo "root:";');
        $this->put('_footer.php', 'echo ":root";');
        $this->put('header/_header.php', 'echo "child:";');
        $this->put('footer/_footer.php', 'echo ":child";');
        $this->put('nested/_header.php', 'echo "nested:";');
        $this->put('nested/users/_footer.php', 'echo ":users";');
        foreach (['header', 'footer', 'nested/users/[id]'] as $directory) {
            $this->put($directory . '/index.get.php', 'echo "body";');
        }
        $router = new Router($this->directory);
        foreach (['/header' => 'child:body:root', '/footer' => 'root:body:child', '/nested/users/42' => 'nested:body:users'] as $path => $expected) {
            self::assertSame($expected, (string) $router->handle(new ServerRequest('GET', $path))->getBody());
        }
    }
}
