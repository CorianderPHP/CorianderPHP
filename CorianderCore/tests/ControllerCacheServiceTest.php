<?php

namespace CorianderCore\Tests;

use CorianderCore\Core\Router\Services\ControllerCacheService;
use PHPUnit\Framework\TestCase;

class ControllerCacheServiceTest extends TestCase
{
    private static string $testPath;
    private bool $srcCreatedDuringTest = false;
    private bool $controllersCreatedDuringTest = false;

    public static function setUpBeforeClass(): void
    {
        if (!defined('PROJECT_ROOT')) {
            define('PROJECT_ROOT', dirname(__DIR__, 2));
        }
        self::$testPath = PROJECT_ROOT . '/CorianderCore/tests/_tmp';
        if (!is_dir(self::$testPath)) {
            mkdir(self::$testPath, 0777, true);
        }
    }

    protected function setUp(): void
    {
        if (!is_dir(PROJECT_ROOT . '/src')) {
            mkdir(PROJECT_ROOT . '/src', 0777, true);
            $this->srcCreatedDuringTest = true;
        }
        if (!is_dir(PROJECT_ROOT . '/src/Controllers')) {
            mkdir(PROJECT_ROOT . '/src/Controllers', 0777, true);
            $this->controllersCreatedDuringTest = true;
        }
    }

    protected function tearDown(): void
    {
        $controllerFile = PROJECT_ROOT . '/src/Controllers/TestController.php';
        if (file_exists($controllerFile)) {
            unlink($controllerFile);
        }
        if ($this->controllersCreatedDuringTest) {
            $this->deleteDirectory(PROJECT_ROOT . '/src/Controllers');
        }
        if ($this->srcCreatedDuringTest) {
            $this->deleteDirectory(PROJECT_ROOT . '/src');
        }

        $cacheDir = self::$testPath . '/cache';
        $cacheFile = $cacheDir . '/controllers.php';
        if (file_exists($cacheFile)) {
            unlink($cacheFile);
        }
        if (is_dir($cacheDir)) {
            rmdir($cacheDir);
        }
    }

    private function deleteDirectory(string $dirPath): void
    {
        if (!is_dir($dirPath)) {
            return;
        }
        $files = array_diff(scandir($dirPath), ['.', '..']);
        foreach ($files as $file) {
            $filePath = "$dirPath/$file";
            is_dir($filePath) ? $this->deleteDirectory($filePath) : unlink($filePath);
        }
        rmdir($dirPath);
    }

    public function testBuildCreatesCacheFile(): void
    {
        $controllerFile = PROJECT_ROOT . '/src/Controllers/TestController.php';
        $controllerCode = "<?php\\nnamespace Controllers;\\nclass TestController { }\\n";
        file_put_contents($controllerFile, $controllerCode);

        $cacheDir = self::$testPath . '/cache';
        mkdir($cacheDir, 0777, true);
        $cacheFile = $cacheDir . '/controllers.php';

        $service = new ControllerCacheService($cacheFile);
        $service->build(PROJECT_ROOT . '/src/Controllers');

        $this->assertFileExists($cacheFile);
        $cache = require $cacheFile;
        $expectedClass = 'Controllers\\TestController';
        $this->assertArrayHasKey($expectedClass, $cache);
        $this->assertSame($controllerFile, $cache[$expectedClass]);
    }

    public function testClearRemovesCacheFile(): void
    {
        $controllerFile = PROJECT_ROOT . '/src/Controllers/TestController.php';
        $controllerCode = "<?php\\nnamespace Controllers;\\nclass TestController { }\\n";
        file_put_contents($controllerFile, $controllerCode);

        $cacheDir = self::$testPath . '/cache';
        mkdir($cacheDir, 0777, true);
        $cacheFile = $cacheDir . '/controllers.php';

        $service = new ControllerCacheService($cacheFile);
        $service->build(PROJECT_ROOT . '/src/Controllers');

        $expectedClass = 'Controllers\\TestController';
        $this->assertTrue($service->has($expectedClass));
        $this->assertFileExists($cacheFile);

        $service->clear();

        $this->assertFalse($service->has($expectedClass));
        $this->assertFileDoesNotExist($cacheFile);
    }
}
