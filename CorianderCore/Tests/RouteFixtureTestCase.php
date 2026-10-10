<?php
declare(strict_types=1);

namespace CorianderCore\Tests;

use PHPUnit\Framework\TestCase;

abstract class RouteFixtureTestCase extends TestCase
{
    protected string $directory;

    protected function setUp(): void
    {
        $this->directory = PROJECT_ROOT . '/CorianderCore/Tests/_tmp_routes_' . bin2hex(random_bytes(5));
        mkdir($this->directory, 0775, true);
    }

    protected function tearDown(): void
    {
        \corianderDeleteDirectoryRecursive($this->directory);
    }

    protected function put(string $path, string $code): void
    {
        $file = $this->directory . '/' . $path;
        if (!is_dir(dirname($file))) {
            mkdir(dirname($file), 0775, true);
        }
        file_put_contents($file, '<?php ' . $code);
        if (function_exists('opcache_invalidate')) { opcache_invalidate($file, true); }
    }

    protected function response(string $path, string $body = 'fixture', int $status = 200): void
    {
        $this->put($path, 'return static fn($request) => new \\Nyholm\\Psr7\\Response(' . $status . ', [], ' . var_export($body, true) . ');');
    }
}
