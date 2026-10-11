<?php
declare(strict_types=1);

namespace CorianderCore\Tests;

use CorianderCore\Core\Console\Commands\NodeJS;
use CorianderCore\Core\Console\CommandExitCode;
use CorianderCore\Core\Console\Services\Node\NpmExecutableResolver;
use PHPUnit\Framework\TestCase;

class NodeJSTest extends TestCase
{
    use \CorianderCore\Tests\Support\TestDirectoryHelperTrait;

    private ?string $previousCorianderNpmExecutable = null;
    private ?string $previousNpmExecutable = null;

    protected function setUp(): void
    {
        $this->previousCorianderNpmExecutable = getenv('CORIANDER_NPM_EXECUTABLE') === false
            ? null
            : (string) getenv('CORIANDER_NPM_EXECUTABLE');
        $this->previousNpmExecutable = getenv('NPM_EXECUTABLE') === false
            ? null
            : (string) getenv('NPM_EXECUTABLE');
    }

    protected function tearDown(): void
    {
        $this->restoreEnvironment('CORIANDER_NPM_EXECUTABLE', $this->previousCorianderNpmExecutable);
        $this->restoreEnvironment('NPM_EXECUTABLE', $this->previousNpmExecutable);
    }

    public function testExecuteWithoutArgsPrintsError(): void
    {
        $command = new NodeJS();

        ob_start();
        $exitCode = $command->execute([]);
        $output = (string) ob_get_clean();

        $this->assertSame(CommandExitCode::INVALID_USAGE, $exitCode);
        $this->assertStringContainsString('Please provide a Node.js command to run', $output);
    }

    public function testWatchCommandPrintsStartupMessageAndForwardsChildOutput(): void
    {
        $command = new NodeJS(PHP_BINARY);

        ob_start();
        $exitCode = $command->execute(['run', 'watch-tw']);
        $output = (string) ob_get_clean();

        $this->assertSame(CommandExitCode::FAILURE, $exitCode);
        $this->assertStringContainsString('Starting watcher... Press Ctrl+C to stop.', $output);
        $this->assertStringContainsString('Could not open input file', $output);
        $this->assertStringContainsString('npm command failed with exit code', $output);
    }

    public function testSuccessfulWrappedCommandReturnsSuccess(): void
    {
        $scriptPath = $this->createTemporaryPhpScript(<<<'PHP'
<?php
echo "npm completed\n";
exit(0);
PHP);

        try {
            $command = new NodeJS(PHP_BINARY);

            ob_start();
            $exitCode = $command->execute([$scriptPath]);
            $output = (string) ob_get_clean();

            $this->assertSame(CommandExitCode::SUCCESS, $exitCode);
            $this->assertStringContainsString('npm completed', $output);
            $this->assertStringNotContainsString('npm command failed', $output);
        } finally {
            @unlink($scriptPath);
        }
    }

    public function testWatchKeepsChildInputOpenWhileBuildReceivesEof(): void
    {
        $probe = proc_open(['node', '--version'], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $probePipes);
        if (!is_resource($probe)) {
            self::markTestSkipped('Optional Node.js is unavailable');
        }
        foreach ($probePipes as $pipe) {
            stream_get_contents($pipe);
            fclose($pipe);
        }
        if (proc_close($probe) !== 0) {
            self::markTestSkipped('Optional Node.js is unavailable');
        }
        $root = $this->createTemporaryDirectory('_tmp_nodejs_stdin');
        mkdir($root . '/nodejs');
        file_put_contents($root . '/nodejs/run', <<<'JS'
const timer = setTimeout(() => { console.log('input-open'); process.stdin.pause(); }, 100);
process.stdin.once('end', () => { clearTimeout(timer); console.log('input-closed'); });
process.stdin.resume();
JS);
        try {
            foreach (['watch-all' => 'input-open', 'build-prod' => 'input-closed'] as $script => $expected) {
                $code = 'define("PROJECT_ROOT", ' . var_export($root, true) . '); require '
                    . var_export(PROJECT_ROOT . '/vendor/autoload.php', true) . '; exit((new '
                    . '\\CorianderCore\\Core\\Console\\Commands\\NodeJS("node"))->execute(["run", '
                    . var_export($script, true) . ']));';
                $process = proc_open([PHP_BINARY, '-r', $code], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, PROJECT_ROOT);
                self::assertIsResource($process);
                $output = stream_get_contents($pipes[1]);
                $errors = stream_get_contents($pipes[2]);
                fclose($pipes[1]);
                fclose($pipes[2]);
                self::assertSame(0, proc_close($process), $errors);
                self::assertStringContainsString($expected, $output);
            }
        } finally {
            $this->deleteDirectory($root);
        }
    }

    public function testFailingWrappedCommandPreservesChildExitCode(): void
    {
        $scriptPath = $this->createTemporaryPhpScript(<<<'PHP'
<?php
fwrite(STDERR, "npm failed\n");
exit(7);
PHP);

        try {
            $command = new NodeJS(PHP_BINARY);

            ob_start();
            $exitCode = $command->execute([$scriptPath]);
            $output = (string) ob_get_clean();

            $this->assertSame(7, $exitCode);
            $this->assertStringContainsString('npm failed', $output);
            $this->assertStringContainsString('npm command failed with exit code 7', $output);
        } finally {
            @unlink($scriptPath);
        }
    }

    public function testResolveNpmExecutableUsesEnvironmentOverride(): void
    {
        putenv('CORIANDER_NPM_EXECUTABLE=C:\Tools\nodejs\npm.cmd');
        putenv('NPM_EXECUTABLE');

        $resolver = new NpmExecutableResolver();

        $this->assertSame('C:\Tools\nodejs\npm.cmd', $resolver->resolve());
    }

    public function testResolveNpmExecutableSkipsBrokenNodeSiblingNpm(): void
    {
        if (DIRECTORY_SEPARATOR !== '\\') {
            $this->markTestSkipped('Windows npm resolution is only used on Windows.');
        }

        $root = sys_get_temp_dir() . '/coriander-nodejs-' . bin2hex(random_bytes(4));
        $brokenNodeDir = $root . '/broken-node';
        $validNodeDir = $root . '/valid-node';
        mkdir($brokenNodeDir, 0777, true);
        mkdir($validNodeDir . '/node_modules/npm/bin', 0777, true);

        $brokenNode = $brokenNodeDir . DIRECTORY_SEPARATOR . 'node.exe';
        $brokenNpm = $brokenNodeDir . DIRECTORY_SEPARATOR . 'npm.cmd';
        $validNode = $validNodeDir . DIRECTORY_SEPARATOR . 'node.exe';
        $validNpm = $validNodeDir . DIRECTORY_SEPARATOR . 'npm.cmd';
        $validNpmCli = $validNodeDir . DIRECTORY_SEPARATOR . 'node_modules/npm/bin/npm-cli.js';

        touch($brokenNode);
        touch($brokenNpm);
        touch($validNode);
        touch($validNpm);
        touch($validNpmCli);

        try {
            $resolver = new NpmExecutableResolver(null, static fn(string $name): array => $name === 'node'
                ? [$brokenNode, $validNode]
                : [$brokenNpm, $validNpm]);

            $this->assertSame($validNpm, $resolver->resolve());
        } finally {
            @unlink($validNpmCli);
            @unlink($validNpm);
            @unlink($validNode);
            @unlink($brokenNpm);
            @unlink($brokenNode);
            @rmdir($validNodeDir . '/node_modules/npm/bin');
            @rmdir($validNodeDir . '/node_modules/npm');
            @rmdir($validNodeDir . '/node_modules');
            @rmdir($validNodeDir);
            @rmdir($brokenNodeDir);
            @rmdir($root);
        }
    }

    private function restoreEnvironment(string $name, ?string $value): void
    {
        if ($value === null) {
            putenv($name);
            return;
        }

        putenv($name . '=' . $value);
    }

    private function createTemporaryPhpScript(string $contents): string
    {
        $scriptPath = sys_get_temp_dir() . '/coriander-nodejs-command-' . bin2hex(random_bytes(4)) . '.php';
        file_put_contents($scriptPath, $contents);

        return $scriptPath;
    }
}
