<?php
declare(strict_types=1);

namespace CorianderCore\Tests;

use CorianderCore\Core\Router\{Router, RouteCache, RouteMap};
use Nyholm\Psr7\ServerRequest;

class RouteCacheTest extends RouteFixtureTestCase
{
    private function counter(): RouteMap
    {
        return new class extends RouteMap {
            public int $calls = 0;
            public function discover(string $directory): array
            {
                ++$this->calls;
                return parent::discover($directory);
            }
        };
    }

    public function testProductionReusesPersistedMapAndDevelopmentSeesNewFiles(): void
    {
        $this->response('index.get.php');
        $counter = $this->counter();
        $file = $this->directory . '/cache/map.php';
        $cache = new RouteCache($this->directory, $file, true, 30, $counter);
        $expected = $cache->get();
        self::assertSame($expected, $cache->get());
        self::assertSame($expected, (new RouteCache($this->directory, $file, true, 30, $counter))->get());
        self::assertSame(1, $counter->calls);
        $this->response('new.get.php');
        self::assertArrayNotHasKey('new', $cache->get()['static']);
        $dev = new RouteCache($this->directory, $file, false, 30, $counter);
        self::assertArrayHasKey('new', $dev->get()['static']);
        $this->response('next.get.php');
        self::assertArrayHasKey('next', $dev->get()['static']);
        $cache->clear();
        self::assertArrayHasKey('next', $cache->get()['static']);
    }

    public function testExpiredCorruptAndUnwritableCachesRebuildAutomatically(): void
    {
        $this->response('index.get.php');
        $file = $this->directory . '/map.php';
        $counter = $this->counter();
        $cache = new RouteCache($this->directory, $file, true, 0, $counter);
        $cache->get();
        $this->response('new.get.php');
        self::assertArrayHasKey('new', $cache->get()['static']);
        self::assertSame(2, $counter->calls);
        file_put_contents($file, '<?php invalid code');
        self::assertArrayHasKey('new', (new RouteCache($this->directory, $file, true))->get()['static']);
        self::assertArrayHasKey('new', (new RouteCache($this->directory, $file . '/impossible.php', true))->get()['static']);
    }

    public function testMiddlewareChangesAreImmediateAndMissingKnownFilesFailClosed(): void
    {
        $this->response('admin/index.get.php');
        $cache = new RouteCache($this->directory, $this->directory . '/map.php', true);
        $router = new Router($this->directory, $cache);
        self::assertSame(200, $router->handle(new ServerRequest('GET', '/admin'))->getStatusCode());
        $this->put('admin/_middleware.php', 'return [new \\CorianderCore\\Tests\\FixtureMiddleware("auth", true)];');
        self::assertSame(403, $router->handle(new ServerRequest('GET', '/admin'))->getStatusCode());
        $cache->clear();
        $cache->get();
        unlink($this->directory . '/admin/_middleware.php');
        $this->expectExceptionMessage('Required application file is missing');
        $router->handle(new ServerRequest('GET', '/admin'));
    }

    public function testDeletedCachedHandlerFailsClosed(): void
    {
        $this->response('index.get.php');
        $cache = new RouteCache($this->directory, $this->directory . '/map.php', true);
        $cache->get();
        unlink($this->directory . '/index.get.php');
        $this->expectExceptionMessage('Required application file is missing');
        (new Router($this->directory, $cache))->handle(new ServerRequest('GET', '/'));
    }

    public function testInvalidRoutesDoNotReplaceTheLastCompleteMap(): void
    {
        $this->response('index.get.php');
        $file = $this->directory . '/map.php';
        $cache = new RouteCache($this->directory, $file, true, 0);
        $cache->get();
        $saved = file_get_contents($file);
        $this->response('users/[id].get.php');
        $this->response('users/[name].post.php');
        try {
            $cache->get();
            self::fail('Ambiguous routes must fail');
        } catch (\RuntimeException $e) {
            self::assertStringContainsString('Ambiguous', $e->getMessage());
        }
        self::assertSame($saved, file_get_contents($file));
    }

    public function testCompetingRefreshUsesLastCompleteMap(): void
    {
        $this->response('index.get.php');
        $file = $this->directory . '/map.php';
        (new RouteCache($this->directory, $file, true))->get();
        $lock = fopen($file . '.lock', 'c');
        flock($lock, LOCK_EX);
        try {
            $counter = $this->counter();
            self::assertArrayHasKey('', (new RouteCache($this->directory, $file, true, 0, $counter))->get()['static']);
            self::assertSame(0, $counter->calls);
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }
}
