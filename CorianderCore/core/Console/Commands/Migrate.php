<?php
declare(strict_types=1);

namespace CorianderCore\Core\Console\Commands;

use CorianderCore\Core\Console\Commands\Migrate\MigrateEnvironmentPolicy;
use CorianderCore\Core\Console\Commands\Migrate\MigrateOptions;
use CorianderCore\Core\Console\Commands\Migrate\MigrateOutputPresenter;
use CorianderCore\Core\Database\DatabaseHandler;
use CorianderCore\Core\Database\Migrations\MigrationManager;
use RuntimeException;

class Migrate
{
    public function __construct(
        private ?MigrateEnvironmentPolicy $policy = null,
        private ?MigrateOutputPresenter $presenter = null,
    ) {
        $this->policy ??= new MigrateEnvironmentPolicy();
        $this->presenter ??= new MigrateOutputPresenter();
    }

    /**
     * @param array<int, string> $args
     */
    public function execute(array $args = []): void
    {
        $options = MigrateOptions::fromArgs($args);
        $this->policy->assertAllowed($options);

        $manager = $this->buildMigrationManager();

        switch ($options->action) {
            case 'status':
                $this->presenter->printStatus($manager->status($options->allowChanged));
                return;

            case 'rollback':
                $this->presenter->printRollbackResult(
                    $manager->rollback($options->step, $options->dryRun),
                    $options->dryRun
                );
                return;

            case 'up':
            default:
                $this->presenter->printMigrateResult(
                    $manager->migrate($options->dryRun, $options->allowChanged),
                    $options->dryRun
                );
                return;
        }
    }

    private function buildMigrationManager(): MigrationManager
    {
        if (!defined('DB_TYPE')) {
            throw new RuntimeException('DB_TYPE is not defined. Configure your database before running migrations.');
        }

        $databaseHandler = new DatabaseHandler();
        $pdo = $databaseHandler->getPDO();

        if ($pdo === null) {
            throw new RuntimeException('No database connection available. Check your database configuration.');
        }

        return new MigrationManager($pdo, strtolower((string) DB_TYPE), PROJECT_ROOT . '/database/migrations');
    }
}
