<?php
declare(strict_types=1);

namespace CorianderCore\Tests;

use CorianderCore\Core\Http\RequestFactory;
use PHPUnit\Framework\TestCase;

class RequestFactoryTest extends TestCase
{
    public function testCreatesRequestFromServerValues(): void
    {
        $request = RequestFactory::fromGlobals(
            [
                'REQUEST_METHOD' => 'GET',
                'REQUEST_URI' => '/users',
                'SERVER_PROTOCOL' => 'HTTP/2',
            ],
            [],
            ['Accept' => 'application/json'],
            ''
        );

        $this->assertSame('GET', $request->getMethod());
        $this->assertSame('/users', $request->getUri()->getPath());
        $this->assertSame('2', $request->getProtocolVersion());
        $this->assertSame('application/json', $request->getHeaderLine('Accept'));
    }

    public function testParsesJsonBodyForMutatingRequests(): void
    {
        $request = RequestFactory::fromGlobals(
            [
                'REQUEST_METHOD' => 'POST',
                'REQUEST_URI' => '/api/users',
            ],
            [],
            ['Content-Type' => 'application/json'],
            '{"name":"Lohan"}'
        );

        $this->assertSame(['name' => 'Lohan'], $request->getParsedBody());
    }

    public function testUsesPostParamsForFormRequests(): void
    {
        $request = RequestFactory::fromGlobals(
            [
                'REQUEST_METHOD' => 'POST',
                'REQUEST_URI' => '/users',
            ],
            ['name' => 'Lohan'],
            ['Content-Type' => 'application/x-www-form-urlencoded'],
            'name=Lohan'
        );

        $this->assertSame(['name' => 'Lohan'], $request->getParsedBody());
    }
}
