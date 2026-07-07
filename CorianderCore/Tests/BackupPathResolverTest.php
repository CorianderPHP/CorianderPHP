<?php
declare(strict_types=1);

namespace CorianderCore\Tests;

use CorianderCore\Core\Console\Services\Updater\BackupPathResolver;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class BackupPathResolverTest extends TestCase
{
    public function testNormalizesBackupDirectoryAndScope(): void
    {
        $resolver = new BackupPathResolver('/project/root', ' backups/custom// ');

        $this->assertSame('backups/custom', $resolver->getDefaultBackupDirectory());
        $this->assertSame('scope/name', $resolver->normalizeBackupScope(' scope//name/ '));
        $this->assertSame('/project/root/backups/custom/scope/name', $resolver->resolveScopePath('scope/name'));
    }

    public function testRejectsTraversalBackupDirectory(): void
    {
        $resolver = new BackupPathResolver('/project/root');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('path traversal');

        $resolver->normalizeBackupDirectory('../outside');
    }

    public function testRejectsTraversalBackupScope(): void
    {
        $resolver = new BackupPathResolver('/project/root');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('path traversal');

        $resolver->normalizeBackupScope('../outside');
    }
}
