<?php
declare(strict_types=1);

namespace CorianderCore\Tests;

use CorianderCore\Core\Console\Services\Updater\GitHubReleaseService;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class GitHubReleaseServiceTest extends TestCase
{
    protected function tearDown(): void
    {
        putenv('CORIANDER_UPDATE_ALLOWED_REPOS');
    }

    public function testExtractStatusCodeReturnsFinalStatusFromRedirectChain(): void
    {
        $service = new GitHubReleaseService('CorianderPHP/CorianderPHP');

        $headers = [
            'HTTP/1.1 302 Found',
            'Location: https://codeload.github.com/CorianderPHP/CorianderPHP/zip/refs/tags/v0.1.1',
            'HTTP/1.1 200 OK',
            'Content-Type: application/zip',
        ];

        $method = new \ReflectionMethod($service, 'extractStatusCode');
        $method->setAccessible(true);

        $statusCode = $method->invoke($service, $headers);

        $this->assertSame(200, $statusCode);
    }

    public function testConstructorRejectsInvalidRepositoryFormat(): void
    {
        $this->expectException(RuntimeException::class);
        new GitHubReleaseService('invalid repository format');
    }

    public function testConstructorRejectsRepositoryOutsideAllowlist(): void
    {
        putenv('CORIANDER_UPDATE_ALLOWED_REPOS=CorianderPHP/CorianderPHP');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('not allowed');
        new GitHubReleaseService('example/other-repo');
    }

    public function testDownloadArchiveRejectsNonHttpsUrl(): void
    {
        $service = new GitHubReleaseService('CorianderPHP/CorianderPHP');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('must use HTTPS');
        $service->downloadArchive('http://github.com/CorianderPHP/CorianderPHP/archive/refs/tags/v0.1.1.zip', sys_get_temp_dir() . '/unused.zip');
    }

    public function testDownloadArchiveRejectsUnknownHost(): void
    {
        $service = new GitHubReleaseService('CorianderPHP/CorianderPHP');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('host is not allowed');
        $service->downloadArchive('https://example.com/release.zip', sys_get_temp_dir() . '/unused.zip');
    }
}
