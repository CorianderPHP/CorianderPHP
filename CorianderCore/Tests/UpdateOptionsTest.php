<?php
declare(strict_types=1);

namespace CorianderCore\Tests;

use CorianderCore\Core\Console\Commands\Update\UpdateOptions;
use PHPUnit\Framework\TestCase;

class UpdateOptionsTest extends TestCase
{
    public function testFromArgsParsesFlagsAndBackupDirectory(): void
    {
        $options = UpdateOptions::fromArgs([
            '--yes',
            '--dry-run',
            '--force',
            '--clear-cache',
            '--rollback',
            '--backup-dir=backups/custom',
        ]);

        $this->assertTrue($options->assumeYes);
        $this->assertTrue($options->dryRun);
        $this->assertTrue($options->force);
        $this->assertTrue($options->clearCache);
        $this->assertTrue($options->rollback);
        $this->assertSame('backups/custom', $options->backupDirectory);
    }

    public function testFromArgsIgnoresEmptyBackupDirectoryValue(): void
    {
        $options = UpdateOptions::fromArgs(['--backup-dir=']);

        $this->assertNull($options->backupDirectory);
    }
}
