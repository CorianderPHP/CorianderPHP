<?php
declare(strict_types=1);

namespace CorianderCore\Tests;

use CorianderCore\Core\Router\Router;
use Nyholm\Psr7\Response;
use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

class ExplicitRoutingTest extends TestCase
{
    public function testAutomaticAliasesCannotBypassProtectedRoutes(): void
    {
        \Controllers\AliasGuardController::$calls = 0;
        $router = new Router();
        $deny = new class implements MiddlewareInterface {
            public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
            {
                return new Response(403);
            }
        };
        $router->get('alias-guard', fn() => (new \Controllers\AliasGuardController())->index(), [$deny]);
        $this->assertSame(403, $router->dispatch(new ServerRequest('GET', '/alias-guard'))->getStatusCode());
        foreach (['/alias-guard/index', '/AliasGuard/index', '/alias-guard-controller/index', '/api/alias-guard', '/home'] as $path) {
            $this->assertSame(404, $router->dispatch(new ServerRequest('GET', $path))->getStatusCode(), $path);
        }
        $this->assertSame(0, \Controllers\AliasGuardController::$calls);
    }

    public function testAutomaticRoutingRequiresExplicitOptIn(): void
    {
        $router = new Router(automaticRouting: true);
        $this->assertSame('private', (string) $router->dispatch(new ServerRequest('GET', '/alias-guard/index'))->getBody());
        $this->assertSame('private-api', (string) $router->dispatch(new ServerRequest('GET', '/api/alias-guard'))->getBody());
    }

    #[\PHPUnit\Framework\Attributes\RunInSeparateProcess]
    public function testStarterHomepageIsExplicitlyRegistered(): void
    {
        $router = new Router();
        $notFound = static fn() => new Response(404);
        require PROJECT_ROOT . '/public/routes.php';
        $response = $router->dispatch(new ServerRequest('GET', '/'));
        $this->assertSame(200, $response->getStatusCode());
        $this->assertStringContainsString('Welcome to CorianderPHP', (string) $response->getBody());
    }
}

namespace Controllers;
class AliasGuardController
{
    public static int $calls = 0;
    public function index(): string { self::$calls++; return 'private'; }
}

namespace ApiControllers;
class AliasGuardController
{
    public function get(): \Nyholm\Psr7\Response { return new \Nyholm\Psr7\Response(200, [], 'private-api'); }
}
