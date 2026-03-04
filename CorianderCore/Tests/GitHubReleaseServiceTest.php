<?php
declare(strict_types=1);

namespace CorianderCore\Tests;

use CorianderCore\Core\Console\Services\Updater\GitHubReleaseService;
use PHPUnit\Framework\TestCase;

class GitHubReleaseServiceTest extends TestCase
{
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
}
