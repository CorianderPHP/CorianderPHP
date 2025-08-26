<?php
namespace ApiControllers;

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

    /**
     * Handle GET requests.
     *
     * @param mixed ...$params Parameters passed from URI.
     * @return void
     */
    public function get(...$params): void
    {
        self::$calls[] = ['get', $params];
    }

    /**
     * Handle POST create action.
     *
     * @param mixed ...$params Parameters passed from URI.
     * @return void
     */
    public function post_create(...$params): void
    {
        self::$calls[] = ['post_create', $params];
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
    /**
     * Ensure PROJECT_ROOT constant is defined for autoloading.
     */
    public static function setUpBeforeClass(): void
    {
        if (!defined('PROJECT_ROOT')) {
            define('PROJECT_ROOT', dirname(__DIR__, 2));
        }
    }

    /**
     * Reset stub call history.
     */
    protected function setUp(): void
    {
        SampleController::$calls = [];
    }

    /**
     * Test controller/method resolution for basic GET routes.
     *
     * @return void
     */
    public function testControllerMethodResolutionForBasicRoutes(): void
    {
        $handler = new ApiControllerHandler();
        $result = $handler->handle('api/sample', 'GET');

        $this->assertTrue($result, 'Handler should dispatch to existing controller.');
        $this->assertSame([
            ['get', []],
        ], SampleController::$calls, 'GET dispatch should call get without parameters.');
    }

    /**
     * Check parameter handling from URI and POST sub-action resolution.
     *
     * @return void
     */
    public function testParamHandlingFromUri(): void
    {
        $handler = new ApiControllerHandler();
        $result = $handler->handle('api/sample/create/42/foo', 'POST');

        $this->assertTrue($result, 'Handler should dispatch to existing controller method.');
        $this->assertSame([
            ['post_create', ['42', 'foo']],
        ], SampleController::$calls, 'POST dispatch should pass URI parameters.');
    }

    /**
     * Verify fallback behavior when the controller is missing.
     *
     * @return void
     */
    public function testFallbackWhenControllerMissing(): void
    {
        $handler = new ApiControllerHandler();
        $result = $handler->handle('api/unknown', 'GET');

        $this->assertFalse($result, 'Handler should return false for missing controller.');
    }

    /**
     * Verify fallback behavior when the method is missing.
     *
     * @return void
     */
    public function testFallbackWhenMethodMissing(): void
    {
        $handler = new ApiControllerHandler();
        $result = $handler->handle('api/sample/unknown', 'GET');

        $this->assertFalse($result, 'Handler should return false for missing method.');
        $this->assertSame([], SampleController::$calls, 'No method should be invoked when missing.');
    }
}
