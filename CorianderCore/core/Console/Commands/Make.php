<?php
declare(strict_types=1);

namespace CorianderCore\Core\Console\Commands;

use CorianderCore\Core\Console\Commands\Make\Controller\MakeController;
use CorianderCore\Core\Console\Commands\Make\Database\MakeDatabase;
use CorianderCore\Core\Console\Commands\Make\Migration\MakeMigration;
use CorianderCore\Core\Console\Commands\Make\Sitemap\MakeSitemap;
use CorianderCore\Core\Console\Commands\Make\View\MakeView;
use CorianderCore\Core\Console\ConsoleOutput;

class Make
{
    /**
     * @var list<string>
     */
    protected array $validSubcommands = [
        'view',
        'controller',
        'database',
        'sitemap',
        'migration',
    ];

    protected MakeView $makeViewInstance;
    protected MakeController $makeControllerInstance;
    protected MakeDatabase $makeDatabaseInstance;
    protected MakeSitemap $makeSitemapInstance;
    protected MakeMigration $makeMigrationInstance;

    public function __construct()
    {
        $this->makeViewInstance = new MakeView();
        $this->makeControllerInstance = new MakeController();
        $this->makeDatabaseInstance = new MakeDatabase();
        $this->makeSitemapInstance = new MakeSitemap();
        $this->makeMigrationInstance = new MakeMigration();
    }

    /**
     * @param array<int, string> $args
     */
    public function execute(array $args): void
    {
        if ($args === [] || !isset($args[0])) {
            $this->listCommands();
            return;
        }

        $subcommand = strtolower($args[0]);
        if (!in_array($subcommand, $this->validSubcommands, true)) {
            ConsoleOutput::print("&4[Error]&7 Unknown make command: make:{$subcommand}");
            $this->listCommands();
            return;
        }

        $resourceArgs = array_slice($args, 1);

        switch ($subcommand) {
            case 'view':
                $this->makeView($resourceArgs);
                return;

            case 'controller':
                $this->makeController($resourceArgs);
                return;

            case 'database':
                $this->makeDatabase($resourceArgs);
                return;

            case 'sitemap':
                $this->makeSitemap($resourceArgs);
                return;

            case 'migration':
                $this->makeMigration($resourceArgs);
                return;
        }
    }

    /**
     * @param array<int, string> $args
     */
    protected function makeView(array $args): void
    {
        if ($args === []) {
            ConsoleOutput::print("&4[Error]&7 Please specify a view name, e.g., 'make:view agenda'.");
            return;
        }

        $this->makeViewInstance->execute($args);
    }

    /**
     * @param array<int, string> $args
     */
    protected function makeController(array $args): void
    {
        if ($args === []) {
            ConsoleOutput::print("&4[Error]&7 Please specify a controller name, e.g., 'make:controller Agenda'.");
            return;
        }

        $this->makeControllerInstance->execute($args);
    }

    /**
     * @param array<int, string> $args
     */
    protected function makeDatabase(array $args): void
    {
        $this->makeDatabaseInstance->execute();
    }

    /**
     * @param array<int, string> $args
     */
    protected function makeSitemap(array $args): void
    {
        $this->makeSitemapInstance->execute($args);
    }

    /**
     * @param array<int, string> $args
     */
    protected function makeMigration(array $args): void
    {
        if ($args === []) {
            ConsoleOutput::print("&4[Error]&7 Please specify a migration name, e.g., 'make:migration CreateUsersTable'.");
            return;
        }

        $this->makeMigrationInstance->execute($args);
    }

    protected function listCommands(): void
    {
        ConsoleOutput::print('Available make commands:');
        foreach ($this->validSubcommands as $cmd) {
            ConsoleOutput::print('| - make:' . $cmd);
        }
    }
}


