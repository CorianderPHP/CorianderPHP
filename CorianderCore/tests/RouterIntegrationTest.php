<?php

namespace CorianderCore\Tests;

use CorianderCore\Core\Router\Router;
use CorianderCore\Core\Security\Csrf;
use CorianderCore\Core\Security\CsrfMiddleware;
use Nyholm\Psr7\Response;
use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\TestCase;

/**
 * Integration tests exercising the full Router pipeline including middleware,
 * complex routes, and error handling.
 */
class RouterIntegrationTest extends TestCase
{
    private Router $router;
    private bool $srcCreated = false;
    private bool $controllersCreated = false;

    public static function setUpBeforeClass(): void
    {
        if (!defined('PROJECT_ROOT')) {
            define('PROJECT_ROOT', dirname(__DIR__, 2));
        }
    }

    protected function setUp(): void
    {
        $this->router = Router::getInstance();

        if (!is_dir(PROJECT_ROOT . '/src')) {
            mkdir(PROJECT_ROOT . '/src', 0777, true);
            $this->srcCreated = true;
        }

        if (!is_dir(PROJECT_ROOT . '/src/Controllers')) {
            mkdir(PROJECT_ROOT . '/src/Controllers', 0777, true);
            $this->controllersCreated = true;
        }
    }

    protected function tearDown(): void
    {
        unset($this->router);

        if ($this->controllersCreated) {
            $this->deleteDirectory(PROJECT_ROOT . '/src/Controllers');
        }

        if ($this->srcCreated) {
            $this->deleteDirectory(PROJECT_ROOT . '/src');
        }
    }

    /**
     * Recursively delete a directory created during the test lifecycle.
     *
     * @param string $path Directory path to remove.
     * @return void
     */
    private function deleteDirectory(string $path): void
    {
        if (!is_dir($path)) {
            return;
        }

        foreach (array_diff(scandir($path), ['.', '..']) as $item) {
            $itemPath = $path . '/' . $item;
            is_dir($itemPath) ? $this->deleteDirectory($itemPath) : unlink($itemPath);
        }

        rmdir($path);
    }

    /**
     * Ensure the router returns a 404 response when no route matches.
     */
    #[\PHPUnit\Framework\Attributes\RunInSeparateProcess]
    public function testDefaultNotFoundResponse(): void
    {
        $request = new ServerRequest('GET', '/missing');
        $response = $this->router->dispatch($request);

        $this->assertSame(404, $response->getStatusCode());
        $this->assertSame('404 Not Found', (string) $response->getBody());
    }

    /**
     * Verify that complex routes with multiple parameters are dispatched correctly.
     */
    #[\PHPUnit\Framework\Attributes\RunInSeparateProcess]
    public function testComplexRouteParameterDispatch(): void
    {
        $this->router->add('GET', '/blog/{year}/{month}/{slug}', function (string $year, string $month, string $slug) {
            return new Response(200, [], "$year-$month-$slug");
        });

        $request = new ServerRequest('GET', '/blog/2024/01/new-year');
        $response = $this->router->dispatch($request);

        $this->assertSame('2024-01-new-year', (string) $response->getBody());
    }

    /**
     * Integration test verifying CSRF middleware rejection of invalid tokens.
     */
    #[\PHPUnit\Framework\Attributes\RunInSeparateProcess]
    public function testCsrfMiddlewareRejectsInvalidToken(): void
    {
        $this->router->addMiddleware(new CsrfMiddleware());
        $this->router->add('POST', '/submit', fn () => new Response(200, [], 'OK'));

        $request = (new ServerRequest('POST', '/submit'))
            ->withParsedBody(['csrf_token' => 'bad']);
        $response = $this->router->dispatch($request);

        $this->assertSame(403, $response->getStatusCode());
    }

    /**
     * Integration test verifying CSRF middleware acceptance of valid tokens.
     */
    #[\PHPUnit\Framework\Attributes\RunInSeparateProcess]
    public function testCsrfMiddlewareAllowsValidToken(): void
    {
        $token = Csrf::token();
        $this->router->addMiddleware(new CsrfMiddleware());
        $this->router->add('POST', '/submit', fn () => new Response(200, [], 'OK'));

        $request = (new ServerRequest('POST', '/submit'))
            ->withParsedBody(['csrf_token' => $token]);
        $response = $this->router->dispatch($request);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('OK', (string) $response->getBody());
    }
}
