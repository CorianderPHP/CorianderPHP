<?php
declare(strict_types=1);

namespace CorianderCore\Tests;

use Controllers\HttpMethodsTestController;
use CorianderCore\Core\Router\Handlers\WebControllerHandler;
use CorianderCore\Core\Router\Router;
use CorianderCore\Core\Security\CsrfMiddleware;
use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\TestCase;

class WebControllerMethodsTest extends TestCase
{
    public function testSafeRequestsCannotInvokeWriteActions(): void
    {
        HttpMethodsTestController::$calls = [];
        $router = new Router(automaticRouting: true);
        $router->addMiddleware(new CsrfMiddleware());
        foreach (['store' => 'POST', 'update' => 'POST, PUT, PATCH', 'delete' => 'POST, DELETE', 'destroy' => 'POST, DELETE', 'reset' => 'POST'] as $action => $allow) {
            foreach (['GET', 'HEAD'] as $method) {
                $response = $router->dispatch(new ServerRequest($method, '/http-methods-test/' . strtoupper($action)));
                $this->assertSame(405, $response->getStatusCode());
                $this->assertSame($allow, $response->getHeaderLine('Allow'));
            }
        }
        $this->assertSame([], HttpMethodsTestController::$calls);
    }

    public function testApprovedMethodsPreserveActionsAndParameters(): void
    {
        HttpMethodsTestController::$calls = [];
        $handler = new WebControllerHandler();
        foreach ([['POST', ''], ['POST', '/store'], ['PATCH', '/update'], ['DELETE', '/delete'], ['POST', '/destroy'], ['POST', '/reset/42']] as [$method, $suffix]) {
            $this->assertSame(200, $handler->dispatch('http-methods-test' . $suffix, $method)->getStatusCode());
        }
        $this->assertSame(['store', 'store', 'update', 'delete', 'destroy', 'reset:42'], HttpMethodsTestController::$calls);
        $this->assertSame(405, $handler->dispatch('http-methods-test/index', 'PUT')->getStatusCode());
        $this->assertSame(200, $handler->dispatch('http-methods-test/index', 'HEAD')->getStatusCode());
    }
}

namespace Controllers;

use CorianderCore\Core\Router\HttpMethods;

class HttpMethodsTestController
{
    public static array $calls = [];
    public function index(): string { return 'index'; }
    public function store(): void { self::$calls[] = 'store'; }
    public function update(): void { self::$calls[] = 'update'; }
    public function delete(): void { self::$calls[] = 'delete'; }
    public function destroy(): void { self::$calls[] = 'destroy'; }
    #[HttpMethods('POST')]
    public function reset(string $id = ''): void { self::$calls[] = 'reset:' . $id; }
}
