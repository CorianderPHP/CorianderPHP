<?php
declare(strict_types=1);

namespace CorianderCore\Tests;

use CorianderCore\Core\Support\PublicUrl;
use PHPUnit\Framework\TestCase;

class PublicUrlTest extends TestCase
{
    private mixed $previousScriptName = null;
    /** @var list<string> */
    private array $temporaryFiles = [];

    protected function setUp(): void
    {
        $this->previousScriptName = $_SERVER['SCRIPT_NAME'] ?? null;
        putenv('PUBLIC_URL_PREFIX');
        unset($_ENV['PUBLIC_URL_PREFIX'], $_SERVER['PUBLIC_URL_PREFIX'], $_SERVER['SCRIPT_NAME']);
    }

    protected function tearDown(): void
    {
        foreach ($this->temporaryFiles as $file) {
            if (is_file($file)) { unlink($file); }
        }
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

    public function testVersionedCssUsesContentVersionWhenFileExists(): void
    {
        $url = PublicUrl::versionedAsset('assets/css/output.css');

        $this->assertMatchesRegularExpression('#^/assets/css/output\.css\?[a-f0-9]{64}$#', $url);
    }

    public function testChangedCssAndJsInvalidateUrlsWithPreservedTimestamps(): void
    {
        foreach (['css' => '.test{color:red}', 'js' => 'console.log("one");'] as $extension => $content) {
            $file = $this->temporaryFile($extension);
            $path = 'assets/test.' . $extension;
            file_put_contents($file, $content);
            touch($file, 1700000000);
            clearstatcache(true, $file);
            $first = PublicUrl::versionedAsset($path, $file);
            self::assertSame('/' . $path . '?' . hash('sha256', $content), $first);
            file_put_contents($file, $content . ' ');
            touch($file, 1700000000);
            clearstatcache(true, $file);
            self::assertNotSame($first, PublicUrl::versionedAsset($path, $file));
            $changed = PublicUrl::versionedAsset($path, $file);
            touch($file, 1700000001);
            clearstatcache(true, $file);
            self::assertSame($changed, PublicUrl::versionedAsset($path, $file));
        }
    }

    public function testGeneratedMarkersAndEncodedPathsRespectPublicPrefix(): void
    {
        putenv('PUBLIC_URL_PREFIX=/public');
        foreach (['css' => '/* coriander-version:%s */', 'js' => '// coriander-version:%s'] as $extension => $marker) {
            $file = $this->temporaryFile($extension);
            $version = hash('sha256', 'fixture');
            file_put_contents($file, sprintf($marker, $version) . "\n" . str_repeat(' ', 1_048_576));
            self::assertSame('/public/assets/%5Bid%5D%20%26.' . $extension . '?' . $version,
                PublicUrl::versionedAsset('assets/[id] &.' . $extension, $file));
            file_put_contents($file, '/* coriander-version:invalid */');
            self::assertSame('/public/assets/test.' . $extension . '?' . hash_file('sha256', $file),
                PublicUrl::versionedAsset('assets/test.' . $extension, $file));
        }
    }

    public function testOtherAssetsRetainTimestampsAndMissingFilesRemainOptional(): void
    {
        $file = $this->temporaryFile('png');
        file_put_contents($file, 'fixture');
        touch($file, 1700000000);
        self::assertSame('/assets/test.png?1700000000', PublicUrl::versionedAsset('assets/test.png', $file));
        unlink($file);
        clearstatcache(true, $file);
        self::assertSame('/assets/test%20file.css', PublicUrl::versionedAsset('assets/test file.css', $file));
    }

    private function temporaryFile(string $extension): string
    {
        $file = PROJECT_ROOT . '/CorianderCore/Tests/_tmp_asset_' . bin2hex(random_bytes(6)) . '.' . $extension;
        $this->temporaryFiles[] = $file;
        return $file;
    }
}
