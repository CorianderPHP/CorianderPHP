<?php
declare(strict_types=1);

namespace CorianderCore\Tests;

use CorianderCore\Core\Support\PublicUrl;
use PHPUnit\Framework\TestCase;

class PublicUrlTest extends TestCase
{
    private mixed $previousScriptName = null;

    protected function setUp(): void
    {
        $this->previousScriptName = $_SERVER['SCRIPT_NAME'] ?? null;
        putenv('PUBLIC_URL_PREFIX');
        unset($_ENV['PUBLIC_URL_PREFIX'], $_SERVER['PUBLIC_URL_PREFIX'], $_SERVER['SCRIPT_NAME']);
    }

    protected function tearDown(): void
    {
        putenv('PUBLIC_URL_PREFIX');
        unset($_ENV['PUBLIC_URL_PREFIX'], $_SERVER['PUBLIC_URL_PREFIX']);

        if ($this->previousScriptName === null) {
            unset($_SERVER['SCRIPT_NAME']);
        } else {
            $_SERVER['SCRIPT_NAME'] = $this->previousScriptName;
        }
    }

    public function testAssetUsesConfiguredPublicUrlPrefix(): void
    {
        putenv('PUBLIC_URL_PREFIX=/public');

        $this->assertSame('/public/assets/css/output.css', PublicUrl::asset('assets/css/output.css'));
    }

    public function testAssetRemovesPublicSegmentWhenPublicIsDocumentRoot(): void
    {
        $this->assertSame('/assets/css/output.css', PublicUrl::asset('assets/css/output.css'));
    }

    public function testAssetDetectsProjectRootServedThroughPublicIndex(): void
    {
        $_SERVER['SCRIPT_NAME'] = '/public/index.php';

        $this->assertSame('/public/assets/css/output.css', PublicUrl::asset('assets/css/output.css'));
    }

    public function testVersionedAssetAddsFileMtimeWhenFileExists(): void
    {
        $url = PublicUrl::versionedAsset('assets/css/output.css');

        $this->assertMatchesRegularExpression('#^/assets/css/output\.css\?\d+$#', $url);
    }
}
