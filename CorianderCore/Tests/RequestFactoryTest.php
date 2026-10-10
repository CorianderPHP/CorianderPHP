<?php
declare(strict_types=1);

namespace CorianderCore\Tests;

use CorianderCore\Core\Http\RequestFactory;
use PHPUnit\Framework\TestCase;

class RequestFactoryTest extends TestCase
{
    public function testBodyLimitAcceptsBoundaryAndRejectsActualOverflowBeforeJsonParsing(): void
    {
        $request = RequestFactory::fromGlobals([], [], [], '1234', [], [], maxBodyBytes: 4);
        self::assertSame('1234', (string) $request->getBody());
        $this->expectException(\CorianderCore\Core\Http\PayloadTooLargeException::class);
        RequestFactory::fromGlobals(['REQUEST_METHOD' => 'POST'], [], ['Content-Type' => 'application/json', 'Content-Length' => '1'], '{{{{{', [], [], maxBodyBytes: 4);
    }

    public function testDeclaredOverflowIsRejectedBeforeReadingStream(): void
    {
        $stream = \Nyholm\Psr7\Stream::create('body');
        try {
            RequestFactory::fromGlobals([], [], ['CONTENT-LENGTH' => '5'], $stream, [], [], maxBodyBytes: 4);
            self::fail('Oversized declared body accepted');
        } catch (\CorianderCore\Core\Http\PayloadTooLargeException) {
            self::assertSame(0, $stream->tell());
        }
    }

    public function testBodyWithoutDeclaredLengthReadsOnlyLimitPlusOneBytes(): void
    {
        $stream = \Nyholm\Psr7\Stream::create(str_repeat('x', 100));
        try {
            RequestFactory::fromGlobals([], [], [], $stream, [], [], maxBodyBytes: 4);
            self::fail('Oversized stream accepted');
        } catch (\CorianderCore\Core\Http\PayloadTooLargeException) {
            self::assertSame(5, $stream->tell());
        }
        $request = RequestFactory::fromGlobals([], [], [], \Nyholm\Psr7\Stream::create('1234'), [], [], maxBodyBytes: 4);
        self::assertSame('1234', (string) $request->getBody());
    }

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
