<?php
declare(strict_types=1);
namespace CorianderCore\Tests;
use CorianderCore\Tests\Support\HttpServer;
use PHPUnit\Framework\TestCase;

class HttpIntegrationTest extends TestCase
{
    public function testSessionsStartOnDemandAndPersistAfterClosing(): void
    {
        $root = __DIR__ . '/fixtures/http';
        $server = new HttpServer($root, $root . '/router.php');
        try {
            foreach (['/' => 200, '/missing' => 404, '/failure' => 500] as $path => $status) {
                $response = $server->request('GET', $path);
                self::assertSame($status, $response['status']);
                self::assertArrayNotHasKey('set-cookie', $response['headers']);
            }
            $first = $server->request('GET', '/session-counter');
            $cookie = explode(';', $first['headers']['set-cookie'][0])[0];
            self::assertSame(['visits' => 1], json_decode($first['body'], true));
            self::assertStringContainsString('HttpOnly', $first['headers']['set-cookie'][0]);
            self::assertStringContainsString('SameSite=Lax', $first['headers']['set-cookie'][0]);
            self::assertArrayNotHasKey('set-cookie', $server->request('GET', '/', headers: ['Cookie' => $cookie])['headers']);
            $second = $server->request('GET', '/session-counter', headers: ['Cookie' => $cookie]);
            self::assertSame(['visits' => 2], json_decode($second['body'], true));
            self::assertSame(200, $server->request('GET', '/session-auth', headers: ['Cookie' => $cookie])['status']);
            self::assertSame(403, $server->request('GET', '/session-auth')['status']);
            $other = $server->request('GET', '/session-counter');
            self::assertSame(['visits' => 1], json_decode($other['body'], true));
            self::assertNotSame($cookie, explode(';', $other['headers']['set-cookie'][0])[0]);
        } finally { $server->stop(); }
    }

    public function testBodylessStatusesAndFramingOverHttp(): void
    {
        $root = __DIR__ . '/fixtures/http';
        $server = new HttpServer($root, $root . '/router.php');
        try {
            foreach ([204, 205, 304] as $status) {
                $response = $server->request('GET', '/empty/' . $status);
                self::assertSame($status, $response['status']);
                self::assertSame('', $response['body']);
                self::assertSame(['preserved'], $response['headers']['x-fixture']);
                if ($status === 204) {
                    self::assertArrayNotHasKey('content-length', $response['headers']);
                } elseif ($status === 205) {
                    self::assertSame(['0'], $response['headers']['content-length']);
                } else {
                    self::assertSame(['999'], $response['headers']['content-length']);
                }
                if ($status !== 304) {
                    self::assertArrayNotHasKey('transfer-encoding', $response['headers']);
                }
            }
        } finally { $server->stop(); }
    }

    public function testStarterOverActualHttpServesPagesAssetsAndErrors(): void
    {
        $server = new HttpServer(PROJECT_ROOT . '/public', PROJECT_ROOT . '/public/index.php');
        try {
            $home = $server->request('GET', '/');
            self::assertSame(200, $home['status']);
            self::assertStringContainsString('<title>Welcome to CorianderPHP</title>', $home['body']);
            self::assertSame(['nosniff'], $home['headers']['x-content-type-options']);
            self::assertArrayNotHasKey('set-cookie', $home['headers']);
            self::assertSame(200, $server->request('GET', '/assets/css/output.css')['status']);
            foreach (['/missing' => 404, '/src/Routes/index.get.php' => 404, '/sitemap.xml' => 200] as $path => $status) {
                $response = $server->request('HEAD', $path);
                self::assertSame($status, $response['status']);
                self::assertSame('', $response['body']);
                self::assertArrayNotHasKey('set-cookie', $response['headers']);
            }
        } finally { $server->stop(); }
    }

    public function testParametersBodiesCsrfAuthLayoutsAndMethodErrorsOverHttp(): void
    {
        $root = __DIR__ . '/fixtures/http';
        $server = new HttpServer($root, $root . '/router.php');
        try {
            $response = $server->request('GET', '/teams/5/users/alice%20smith?q=search');
            self::assertSame(200, $response['status']);
            self::assertSame(['team' => '5', 'id' => 'alice smith', 'query' => ['q' => 'search']], json_decode($response['body'], true));
            self::assertSame(403, $server->request('POST', '/form', 'name=bad', ['Content-Type' => 'application/x-www-form-urlencoded'])['status']);
            $token = $server->request('GET', '/form');
            $cookie = explode(';', $token['headers']['set-cookie'][0])[0];
            foreach (['application/json' => json_encode(['csrf_token' => $token['body'], 'name' => 'json']),
                'application/x-www-form-urlencoded' => http_build_query(['csrf_token' => $token['body'], 'name' => 'form'])] as $type => $body) {
                $response = $server->request('POST', '/form', $body, ['Content-Type' => $type, 'Cookie' => $cookie]);
                self::assertSame(200, $response['status']);
                self::assertArrayHasKey('name', json_decode($response['body'], true));
            }
            self::assertSame(400, $server->request('POST', '/form', '{broken', ['Content-Type' => 'application/json'])['status']);
            self::assertSame(413, $server->request('POST', '/form', str_repeat('x', 1025))['status']);
            self::assertSame(413, $server->request('POST', '/form', str_repeat('{', 1025), ['Content-Type' => 'application/json'])['status']);
            self::assertSame(403, $server->request('GET', '/admin')['status']);
            $admin = $server->request('GET', '/admin', headers: ['Authorization' => 'Bearer fixture']);
            self::assertSame(200, $admin['status']);
            self::assertStringContainsString('admin-header', $admin['body']);
            self::assertStringNotContainsString('public-header', $admin['body']);
            $public = $server->request('GET', '/');
            self::assertStringContainsString('public-header', $public['body']);
            $method = $server->request('OPTIONS', '/');
            self::assertSame(405, $method['status']);
            self::assertSame(['GET, HEAD'], $method['headers']['allow']);
            $head = $server->request('HEAD', '/');
            self::assertSame(200, $head['status']);
            self::assertSame('', $head['body']);
            self::assertSame(404, $server->request('GET', '/missing')['status']);
        } finally { $server->stop(); }
    }
}
