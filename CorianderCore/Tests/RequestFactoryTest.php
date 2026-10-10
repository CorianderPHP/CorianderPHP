<?php
declare(strict_types=1);

namespace CorianderCore\Tests;

use CorianderCore\Core\Http\RequestFactory;
use PHPUnit\Framework\TestCase;

class RequestFactoryTest extends TestCase
{
    public function testKeepsQueryBodyCookiesAndUploadedFilesSeparate(): void
    {
        $file = PROJECT_ROOT . '/CorianderCore/Tests/_tmp_upload.txt';
        file_put_contents($file, 'upload');
        $request = RequestFactory::fromGlobals(['REQUEST_METHOD' => 'POST', 'REQUEST_URI' => '/users/42?name=query&tags[]=one',
            'CONTENT_TYPE' => 'application/json', 'HTTP_X_TEST' => 'header'], [], null, '{"name":"body"}', ['session' => 'cookie'],
            ['documents' => ['tmp_name' => ['avatar' => $file], 'size' => ['avatar' => 6], 'error' => ['avatar' => UPLOAD_ERR_OK],
                'name' => ['avatar' => 'avatar.txt'], 'type' => ['avatar' => 'text/plain']]]);
        self::assertSame(['name' => 'query', 'tags' => ['one']], $request->getQueryParams());
        self::assertSame(['name' => 'body'], $request->getParsedBody());
        self::assertSame('cookie', $request->getCookieParams()['session']);
        self::assertSame('header', $request->getHeaderLine('X-Test'));
        $upload = $request->getUploadedFiles()['documents']['avatar'];
        self::assertSame('avatar.txt', $upload->getClientFilename());
        self::assertSame('upload', (string) $upload->getStream());
    }

    public function testParsesPutFormAndCaseInsensitiveContentType(): void
    {
        $request = RequestFactory::fromGlobals(['REQUEST_METHOD' => 'PUT', 'REQUEST_URI' => '/users/42'], [],
            ['CONTENT-TYPE' => 'application/x-www-form-urlencoded; charset=UTF-8'], 'name=Alice&roles[]=editor', [], []);
        self::assertSame(['name' => 'Alice', 'roles' => ['editor']], $request->getParsedBody());
    }

    public function testMalformedJsonIsAClientError(): void
    {
        $this->expectException(\CorianderCore\Core\Http\BadRequestException::class);
        RequestFactory::fromGlobals(['REQUEST_METHOD' => 'POST', 'REQUEST_URI' => '/'], [], ['Content-Type' => 'application/json'], '{');
    }

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
