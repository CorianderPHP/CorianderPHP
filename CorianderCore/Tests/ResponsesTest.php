<?php
declare(strict_types=1);

namespace CorianderCore\Tests;

use CorianderCore\Core\Http\Responses;
use PHPUnit\Framework\TestCase;

class ResponsesTest extends TestCase
{
    public function testExplicitStatusHeadersAndEncoding(): void
    {
        $json = Responses::json(['name' => 'é', 'path' => '/users'], 201);
        self::assertSame(201, $json->getStatusCode());
        self::assertSame('application/json; charset=utf-8', $json->getHeaderLine('Content-Type'));
        self::assertSame('{"name":"é","path":"/users"}', (string) $json->getBody());
        self::assertSame(422, Responses::html('invalid', 422)->getStatusCode());
        $redirect = Responses::redirect('/users/42');
        self::assertSame(303, $redirect->getStatusCode());
        self::assertSame('/users/42', $redirect->getHeaderLine('Location'));
    }

    public function testJsonEncodingFailuresAreNotSilentlyReplaced(): void
    {
        $this->expectException(\JsonException::class);
        Responses::json(['invalid' => "\xFF"]);
    }
}
