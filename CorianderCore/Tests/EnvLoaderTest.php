<?php
declare(strict_types=1);

namespace CorianderCore\Tests;

use CorianderCore\Core\Bootstrap\EnvLoader;
use CorianderCore\Tests\Support\TestDirectoryHelperTrait;
use PHPUnit\Framework\TestCase;

class EnvLoaderTest extends TestCase
{
    use TestDirectoryHelperTrait;

    private string $tempRoot;

    /**
     * @var string[]
     */
    private array $variables = [
        'ENV_LOADER_SIMPLE',
        'ENV_LOADER_QUOTED',
        'ENV_LOADER_INLINE',
        'ENV_LOADER_EXPORTED',
        'ENV_LOADER_EXISTING',
        'ENV_LOADER_RELOAD',
        'ENV_LOADER_OVERWRITE_RELOAD',
    ];

    protected function setUp(): void
    {
        $this->tempRoot = $this->createTemporaryDirectory('_tmp_env_loader');
        $this->clearVariables();
    }

    protected function tearDown(): void
    {
        $this->clearVariables();
        $this->deleteDirectory($this->tempRoot);
    }

    public function testLoadsEnvFileAndCreatesItFromExampleWhenMissing(): void
    {
        file_put_contents(
            $this->tempRoot . '/.env-example',
            implode(PHP_EOL, [
                '# Local variables',
                'ENV_LOADER_SIMPLE=value',
                'ENV_LOADER_QUOTED="hello world"',
                'ENV_LOADER_INLINE=value # comment',
                'export ENV_LOADER_EXPORTED=yes',
            ])
        );

        EnvLoader::load($this->tempRoot);

        $this->assertFileExists($this->tempRoot . '/.env');
        $this->assertSame('value', getenv('ENV_LOADER_SIMPLE'));
        $this->assertSame('hello world', getenv('ENV_LOADER_QUOTED'));
        $this->assertSame('value', getenv('ENV_LOADER_INLINE'));
        $this->assertSame('yes', getenv('ENV_LOADER_EXPORTED'));
        $this->assertSame('value', $_ENV['ENV_LOADER_SIMPLE']);
        $this->assertSame('value', $_SERVER['ENV_LOADER_SIMPLE']);
    }

    public function testDoesNotOverwriteExistingEnvironmentByDefault(): void
    {
        putenv('ENV_LOADER_EXISTING=server');

        file_put_contents($this->tempRoot . '/.env', 'ENV_LOADER_EXISTING=file');

        EnvLoader::load($this->tempRoot);

        $this->assertSame('server', getenv('ENV_LOADER_EXISTING'));
    }

    public function testCanOverwriteExistingEnvironmentWhenRequested(): void
    {
        putenv('ENV_LOADER_EXISTING=server');

        file_put_contents($this->tempRoot . '/.env', 'ENV_LOADER_EXISTING=file');

        EnvLoader::load($this->tempRoot, overwrite: true);

        $this->assertSame('file', getenv('ENV_LOADER_EXISTING'));
    }

    public function testSkipsAlreadyLoadedPathWithoutOverwrite(): void
    {
        file_put_contents($this->tempRoot . '/.env', 'ENV_LOADER_RELOAD=first');

        EnvLoader::load($this->tempRoot);
        putenv('ENV_LOADER_RELOAD');
        unset($_ENV['ENV_LOADER_RELOAD'], $_SERVER['ENV_LOADER_RELOAD']);
        file_put_contents($this->tempRoot . '/.env', 'ENV_LOADER_RELOAD=second');

        EnvLoader::load($this->tempRoot);

        $this->assertFalse(getenv('ENV_LOADER_RELOAD'));
        $this->assertArrayNotHasKey('ENV_LOADER_RELOAD', $_ENV);
        $this->assertArrayNotHasKey('ENV_LOADER_RELOAD', $_SERVER);
    }

    public function testOverwriteReloadsAlreadyLoadedPath(): void
    {
        file_put_contents($this->tempRoot . '/.env', 'ENV_LOADER_OVERWRITE_RELOAD=first');

        EnvLoader::load($this->tempRoot);
        file_put_contents($this->tempRoot . '/.env', 'ENV_LOADER_OVERWRITE_RELOAD=second');

        EnvLoader::load($this->tempRoot, overwrite: true);

        $this->assertSame('second', getenv('ENV_LOADER_OVERWRITE_RELOAD'));
    }

    private function clearVariables(): void
    {
        foreach ($this->variables as $variable) {
            putenv($variable);
            unset($_ENV[$variable], $_SERVER[$variable]);
        }
    }
}
