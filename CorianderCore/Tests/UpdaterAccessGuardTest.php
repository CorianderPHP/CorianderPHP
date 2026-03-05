<?php
declare(strict_types=1);

namespace CorianderCore\Tests;

use CorianderCore\Core\Console\Services\Updater\UpdaterAccessGuard;
use PHPUnit\Framework\TestCase;

class UpdaterAccessGuardTest extends TestCase
{
    private string $projectRoot;

    protected function setUp(): void
    {
        $this->projectRoot = PROJECT_ROOT . '/CorianderCore/Tests/_tmp_updater_guard_' . bin2hex(random_bytes(4));
        mkdir($this->projectRoot, 0777, true);

        putenv('APP_ENV=development');
        putenv('CORIANDER_UPDATER_ALLOW_PRODUCTION=1');
        putenv('CORIANDER_UPDATER_MAX_ATTEMPTS_PER_HOUR=2');
        putenv('CORIANDER_UPDATER_AUTH_TOKEN=');
        putenv('CORIANDER_UPDATER_RATE_LIMIT_FILE=cache/rate-limit.json');
    }

    protected function tearDown(): void
    {
        putenv('APP_ENV');
        putenv('CORIANDER_UPDATER_ALLOW_PRODUCTION');
        putenv('CORIANDER_UPDATER_MAX_ATTEMPTS_PER_HOUR');
        putenv('CORIANDER_UPDATER_AUTH_TOKEN');
        putenv('CORIANDER_UPDATER_RATE_LIMIT_FILE');

        $this->deleteDirectory($this->projectRoot);
    }

    public function testAuthTokenIsRequiredWhenConfigured(): void
    {
        putenv('CORIANDER_UPDATER_AUTH_TOKEN=secret-token');

        $guard = new UpdaterAccessGuard($this->projectRoot);
        $args = $guard->assertCanRun(['--auth-token=secret-token', '--yes']);

        $this->assertSame(['--yes'], $args);
    }

    public function testInvalidAuthTokenIsRejected(): void
    {
        putenv('CORIANDER_UPDATER_AUTH_TOKEN=secret-token');

        $guard = new UpdaterAccessGuard($this->projectRoot);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Invalid updater auth token');
        $guard->assertCanRun(['--auth-token=wrong']);
    }

    public function testRateLimitCapsAttemptsPerHour(): void
    {
        $guard = new UpdaterAccessGuard($this->projectRoot, static fn(): int => 1_700_000_000);

        $guard->assertCanRun(['--yes']);
        $guard->assertCanRun(['--dry-run']);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('rate limit reached');
        $guard->assertCanRun(['--rollback']);
    }

    public function testRateLimitFileRejectsPathTraversal(): void
    {
        putenv('CORIANDER_UPDATER_RATE_LIMIT_FILE=../outside/state.json');

        $guard = new UpdaterAccessGuard($this->projectRoot);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('traversal');
        $guard->assertCanRun(['--dry-run']);
    }

    private function deleteDirectory(string $path): void
    {
        if (!is_dir($path)) {
            return;
        }

        $items = scandir($path);
        if ($items === false) {
            return;
        }

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $itemPath = $path . '/' . $item;
            if (is_dir($itemPath)) {
                $this->deleteDirectory($itemPath);
            } elseif (is_file($itemPath)) {
                unlink($itemPath);
            }
        }

        rmdir($path);
    }
}
