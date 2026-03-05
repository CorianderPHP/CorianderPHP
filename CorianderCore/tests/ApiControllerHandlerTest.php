<?php
namespace ApiControllers;

use Nyholm\Psr7\Response;

/**
 * Stub controller for ApiControllerHandler tests.
 */
class SampleController
{
    /**
     * Records invoked actions.
     *
     * @var array<int, array{0:string,1:array}>
     */
    public static array $calls = [];

    public function get(...$params): void
    {
        self::$calls[] = ['get', $params];
    }

    public function post_create(...$params): void
    {
        self::$calls[] = ['post_create', $params];
    }

    public function get_payload(): array
    {
        return ['ok' => true];
    }

    public function get_response(): Response
    {
        return new Response(201, ['Content-Type' => 'application/json'], '{"created":true}');
    }
}


class SampleRestrictedController
{
    protected function get_secret(): void
    {
        // Intentionally non-public to verify dispatch safety.
    }
}
namespace CorianderCore\Tests;

use ApiControllers\SampleController;
use CorianderCore\Core\Router\Handlers\ApiControllerHandler;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for ApiControllerHandler verifying dispatch logic and fallbacks.
 */
class ApiControllerHandlerTest extends TestCase
{
    public static function setUpBeforeClass(): void
    {
        if (!defined('PROJECT_ROOT')) {
            define('PROJECT_ROOT', dirname(__DIR__, 2));
        }
    }

    protected function setUp(): void
    {
        SampleController::$calls = [];
    }

    public function testControllerMethodResolutionForBasicRoutes(): void
    {
        $handler = new ApiControllerHandler();
        $result = $handler->handle('api/sample', 'GET');

        $this->assertTrue($result, 'Handler should dispatch to existing controller.');
        $this->assertSame([
            ['get', []],
        ], SampleController::$calls, 'GET dispatch should call get without parameters.');
    }

    public function testParamHandlingFromUri(): void
    {
        $handler = new ApiControllerHandler();
        $result = $handler->handle('api/sample/create/42/foo', 'POST');

        $this->assertTrue($result, 'Handler should dispatch to existing controller method.');
        $this->assertSame([
            ['post_create', ['42', 'foo']],
        ], SampleController::$calls, 'POST dispatch should pass URI parameters.');
    }

    public function testFallbackWhenControllerMissing(): void
    {
        $handler = new ApiControllerHandler();
        $result = $handler->handle('api/unknown', 'GET');

        $this->assertFalse($result, 'Handler should return false for missing controller.');
    }

    public function testFallbackWhenMethodMissing(): void
    {
        $handler = new ApiControllerHandler();
        $result = $handler->handle('api/sample/unknown', 'GET');

        $this->assertFalse($result, 'Handler should return false for missing method.');
        $this->assertSame([], SampleController::$calls, 'No method should be invoked when missing.');
    }


    public function testFallbackWhenActionExistsButIsNotPublic(): void
    {
        $handler = new ApiControllerHandler();

        $this->assertFalse($handler->handle('api/sample-restricted/secret', 'GET'));
        $this->assertNull($handler->dispatch('api/sample-restricted/secret', 'GET'));
    }
    public function testDispatchReturnsJsonResponseForArrayPayload(): void
    {
        $handler = new ApiControllerHandler();
        $response = $handler->dispatch('api/sample/payload', 'GET');

        $this->assertNotNull($response);
        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('application/json; charset=utf-8', $response->getHeaderLine('Content-Type'));
        $this->assertSame('{"ok":true}', (string) $response->getBody());
    }

    public function testDispatchKeepsControllerProvidedResponse(): void
    {
        $handler = new ApiControllerHandler();
        $response = $handler->dispatch('api/sample/response', 'GET');

        $this->assertNotNull($response);
        $this->assertSame(201, $response->getStatusCode());
        $this->assertSame('application/json', $response->getHeaderLine('Content-Type'));
        $this->assertSame('{"created":true}', (string) $response->getBody());
    }

    /**
     * Ensure API controllers can be loaded from src/ApiControllers even when
     * they are not preloaded by autoload.
     */
    public function testLoadsApiControllerFromProjectFileWhenNotPreloaded(): void
    {
        $apiDir = PROJECT_ROOT . '/src/ApiControllers';
        $apiDirCreated = false;
        if (!is_dir($apiDir)) {
            mkdir($apiDir, 0777, true);
            $apiDirCreated = true;
        }

        $controllerFile = $apiDir . '/FileBackedController.php';
        file_put_contents($controllerFile, <<<'PHP'
<?php
namespace ApiControllers;

class FileBackedController
{
    public function get(): void
    {
        // no-op, invocation success is validated via handler return value
    }
}
PHP
        );

        try {
            $handler = new ApiControllerHandler();
            $result = $handler->handle('api/file-backed', 'GET');
            $this->assertTrue($result, 'Handler should include src/ApiControllers file and dispatch.');
        } finally {
            if (file_exists($controllerFile)) {
                unlink($controllerFile);
            }
            if ($apiDirCreated && is_dir($apiDir)) {
                rmdir($apiDir);
            }
        }
    }
}

