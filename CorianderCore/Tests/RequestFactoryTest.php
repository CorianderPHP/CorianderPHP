<?php
declare(strict_types=1);

namespace CorianderCore\Tests;

use CorianderCore\Core\Http\RequestFactory;
use PHPUnit\Framework\TestCase;

class RequestFactoryTest extends TestCase
{
    public function testUriUsesHostAndHttpsWhilePreservingEncodedPathAndQuery(): void
    {
        $request = RequestFactory::fromGlobals(['REQUEST_URI' => '/users/%3Cid%3E?tags%5B%5D=one&name=a%20b', 'HTTPS' => 'on', 'SERVER_PORT' => '443'], [], ['hOsT' => 'Example.test:8443'], '', [], []);
        self::assertSame('https://example.test:8443/users/%3Cid%3E?tags%5B%5D=one&name=a%20b', (string) $request->getUri());
        self::assertSame('Example.test:8443', $request->getHeaderLine('Host'));
        self::assertSame(['tags' => ['one'], 'name' => 'a b'], $request->getQueryParams());
        self::assertSame('/users/%3Cid%3E?tags%5B%5D=one&name=a%20b', $request->getRequestTarget());
    }

    public function testUriHandlesIpv6AndDefaultPorts(): void
    {
        foreach ([['[::1]:8080', [], 'http://[::1]:8080/'], ['example.test:443', ['HTTPS' => 'on'], 'https://example.test/'], ['example.test:80', ['HTTPS' => 'on'], 'https://example.test:80/']] as [$host, $server, $expected]) {
            self::assertSame($expected, (string) RequestFactory::fromGlobals($server, [], ['Host' => $host], '', [], [])->getUri());
        }
    }

    public function testUriUsesServerNameOrAddressOnlyWhenHostIsAbsent(): void
    {
        foreach ([['SERVER_NAME' => 'example.test', 'SERVER_PORT' => '8080'], ['SERVER_ADDR' => '::1', 'SERVER_PORT' => '8080']] as $server) {
            $request = RequestFactory::fromGlobals($server, [], [], '', [], []);
            self::assertSame(8080, $request->getUri()->getPort());
            self::assertSame('http', $request->getUri()->getScheme());
            self::assertSame($server['SERVER_NAME'] ?? '[::1]', $request->getUri()->getHost());
        }
        self::assertSame('/about?q=one', (string) RequestFactory::fromGlobals(['REQUEST_URI' => '/about?q=one'], [], [], '', [], [])->getUri());
    }

    public function testUriUsesExistingProxyTrustWithoutForwardedHostOrListenerPort(): void
    {
        foreach (['127.0.0.1' => 'https', '192.0.2.1' => 'http'] as $remote => $scheme) {
            $request = RequestFactory::fromGlobals(['REMOTE_ADDR' => $remote, 'HTTP_X_FORWARDED_PROTO' => 'https', 'HTTP_X_FORWARDED_HOST' => 'wrong.test', 'HTTP_HOST' => 'example.test', 'SERVER_PORT' => '8080'], [], null, '', [], []);
            self::assertSame($scheme . '://example.test/', (string) $request->getUri());
        }
    }

    public function testMalformedHostsBecomeClientErrors(): void
    {
        foreach (['', 'example.test/path', 'user@example.test', 'example.test?query', 'example.test#fragment', 'example.test:65536', '[:::]', '[1]', "example.test\r\nX-Test: value"] as $host) {
            try {
                RequestFactory::fromGlobals([], [], ['Host' => $host], '', [], []);
            } catch (\CorianderCore\Core\Http\BadRequestException $exception) {
                self::assertSame(400, \CorianderCore\Core\Http\ErrorResponse::fromException($exception)->getStatusCode());
                continue;
            }
            self::fail('Malformed host accepted: ' . $host);
        }
    }

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
