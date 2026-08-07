<?php
declare(strict_types=1);

/*
 * CommandHandler orchestrates CLI command execution by mapping command names
 * to handler classes and delegating argument processing to each command.
 */

namespace CorianderCore\Core\Console;

/**
 * Routes console commands to their corresponding handler classes and manages execution.
 *
 * Maintains the registry of available commands, dispatches subcommands, and
 * provides help output when requested or when an unknown command is encountered.
 */
class CommandHandler
{
    /**
     * Available commands and their corresponding handler classes.
     *
     * @var array<string, class-string>
     */
    protected array $commands = [
        'hello' => \CorianderCore\Core\Console\Commands\Hello::class,
        'nodejs' => \CorianderCore\Core\Console\Commands\NodeJS::class,
        'make' => \CorianderCore\Core\Console\Commands\Make::class,
        'benchmark' => \CorianderCore\Core\Console\Commands\Benchmark::class,
        'cache' => \CorianderCore\Core\Console\Commands\Cache::class,
        'version' => \CorianderCore\Core\Console\Commands\Version::class,
        'update' => \CorianderCore\Core\Console\Commands\Update::class,
        'migrate' => \CorianderCore\Core\Console\Commands\Migrate::class,
    ];

    /**
     * @var array<string, array{description:string,usage:list<string>,examples:list<string>}>
     */
    private array $commandHelp = [
        'help' => [
            'description' => 'Show CLI help. Pass a command name for focused help.',
            'usage' => ['php coriander help [command]'],
            'examples' => ['php coriander help make', 'php coriander help nodejs'],
        ],
        'hello' => [
            'description' => 'Print a short CorianderPHP greeting.',
            'usage' => ['php coriander hello'],
            'examples' => ['php coriander hello'],
        ],
        'version' => [
            'description' => 'Show the installed CorianderPHP version.',
            'usage' => ['php coriander version'],
            'examples' => ['php coriander version'],
        ],
        'make' => [
            'description' => 'Generate project files such as views, controllers, routes, databases, sitemaps, and migrations.',
            'usage' => [
                'php coriander make:view <name>',
                'php coriander make:controller <name> [api]',
                'php coriander make:route <name>',
                'php coriander make:migration <name>',
                'php coriander make:database',
                'php coriander make:sitemap',
            ],
            'examples' => [
                'php coriander make:view Home',
                'php coriander make:controller Dashboard',
                'php coriander make:controller Users api',
                'php coriander make:route admin/users',
                'php coriander make:migration CreateUsersTable',
            ],
        ],
        'migrate' => [
            'description' => 'Run, inspect, or roll back database migrations.',
            'usage' => [
                'php coriander migrate',
                'php coriander migrate:status',
                'php coriander migrate:rollback [--step=N] [--dry-run]',
            ],
            'examples' => [
                'php coriander migrate',
                'php coriander migrate:status',
                'php coriander migrate:rollback --step=2',
            ],
        ],
        'nodejs' => [
            'description' => 'Run npm commands from the project nodejs directory using the resolved npm executable.',
            'usage' => [
                'php coriander nodejs install',
                'php coriander nodejs run <script>',
            ],
            'examples' => [
                'php coriander nodejs install',
                'php coriander nodejs run watch-all',
                'php coriander nodejs run build-prod',
            ],
        ],
        'cache' => [
            'description' => 'Build or clear framework caches.',
            'usage' => [
                'php coriander cache controllers',
                'php coriander cache clear',
            ],
            'examples' => ['php coriander cache controllers', 'php coriander cache clear'],
        ],
        'benchmark' => [
            'description' => 'Run framework benchmark helpers.',
            'usage' => ['php coriander benchmark:router'],
            'examples' => ['php coriander benchmark:router'],
        ],
        'update' => [
            'description' => 'Update framework-managed files from the latest stable release.',
            'usage' => ['php coriander update [options]'],
            'examples' => [
                'php coriander update --dry-run',
                'php coriander update --yes',
                'php coriander update --yes --pre-release',
            ],
        ],
    ];

    /**
     * Handles the execution of the given command.
     *
     * @param string $command The command name to execute
     * @param array $args The arguments passed to the command
     * @throws \Exception If the command does not exist or the command class lacks an 'execute' method.
     * @return int Process exit code.
     */
    public function handle(string $command, array $args): int
    {
        ConsoleOutput::hr();
        $command = trim($command);

        if ($command === '') {
            $this->listCommands();
            ConsoleOutput::hr();
            return CommandExitCode::SUCCESS;
        }

        if ($this->isHelpAlias(strtolower($command))) {
            $command = 'help';
        }

        $splitCommand = explode(':', $command);

        $mainCommand = $splitCommand[0];
        $subCommand = $splitCommand[1] ?? null;

        if (strtolower($mainCommand) === 'help') {
            $this->listHelp($args[0] ?? null);
            ConsoleOutput::hr();
            return CommandExitCode::SUCCESS;
        }

        if (!isset($this->commands[$mainCommand])) {
            ConsoleOutput::print("&4[Error]&7 Unknown command: {$mainCommand}\n");
            $this->listCommands();
            ConsoleOutput::hr();
            return CommandExitCode::UNKNOWN_COMMAND;
        }

        $commandClass = $this->commands[$mainCommand];

        if (!class_exists($commandClass)) {
            throw new \Exception("Command class {$commandClass} not found.");
        }

        $commandInstance = new $commandClass();

        if ($subCommand) {
            array_unshift($args, $subCommand);
        }

        if ($this->hasHelpFlag($args)) {
            $this->listHelp($mainCommand);
            ConsoleOutput::hr();
            return CommandExitCode::SUCCESS;
        }

        if (!method_exists($commandInstance, 'execute')) {
            throw new \Exception("Command {$mainCommand} does not have an execute method.");
        }

        $result = $commandInstance->execute($args);
        ConsoleOutput::hr();

        return $this->normalizeExitCode($result);
    }

    /**
     * Lists all available commands, including 'help'.
     *
     * @return void
     */
    protected function listCommands(): void
    {
        ConsoleOutput::print('CorianderPHP CLI');
        ConsoleOutput::print('Usage: php coriander <command> [arguments]');
        ConsoleOutput::print('');
        ConsoleOutput::print('Common commands:');

        ConsoleOutput::print('| - help');

        foreach ($this->commands as $cmd => $class) {
            ConsoleOutput::print("| - {$cmd}");
        }

        ConsoleOutput::print('');
        ConsoleOutput::print('Run php coriander help for detailed usage.');
    }

    private function listHelp(?string $topic = null): void
    {
        $topic = $topic !== null ? strtolower(trim($topic)) : null;

        if ($topic !== null && $topic !== '') {
            $this->listCommandHelp($topic);
            return;
        }

        ConsoleOutput::print('CorianderPHP CLI Help');
        ConsoleOutput::print('Usage: php coriander <command> [arguments]');
        ConsoleOutput::print('');
        ConsoleOutput::print('Commands:');

        foreach (array_keys($this->commandHelp) as $command) {
            $this->printCommandSummary($command);
        }

        ConsoleOutput::print('');
        ConsoleOutput::print('Common examples:');
        ConsoleOutput::print('  php coriander make:controller Dashboard');
        ConsoleOutput::print('  php coriander make:route admin/users');
        ConsoleOutput::print('  php coriander migrate:status');
        ConsoleOutput::print('  php coriander nodejs run build-prod');
        ConsoleOutput::print('  php coriander update --dry-run');
        ConsoleOutput::print('');
        ConsoleOutput::print('Run php coriander help <command> for focused help.');
    }

    private function listCommandHelp(string $command): void
    {
        $command = explode(':', $command)[0];

        if (!isset($this->commandHelp[$command])) {
            ConsoleOutput::print("&4[Error]&7 Unknown help topic: {$command}");
            ConsoleOutput::print('');
            $this->listHelp();
            return;
        }

        $help = $this->commandHelp[$command];
        ConsoleOutput::print($command);
        ConsoleOutput::print('  ' . $help['description']);
        ConsoleOutput::print('');
        ConsoleOutput::print('Usage:');
        foreach ($help['usage'] as $usage) {
            ConsoleOutput::print('  ' . $usage);
        }

        ConsoleOutput::print('');
        ConsoleOutput::print('Examples:');
        foreach ($help['examples'] as $example) {
            ConsoleOutput::print('  ' . $example);
        }
    }

    private function printCommandSummary(string $command): void
    {
        $description = $this->commandHelp[$command]['description'];
        ConsoleOutput::print("  {$command}");
        ConsoleOutput::print("    {$description}");
    }

    /**
     * @param array<int, string> $args
     */
    private function hasHelpFlag(array $args): bool
    {
        return in_array('--help', $args, true) || in_array('-h', $args, true);
    }

    private function isHelpAlias(string $command): bool
    {
        return $command === '--help' || $command === '-h';
    }

    private function normalizeExitCode(mixed $result): int
    {
        if (is_int($result)) {
            return max(0, min(255, $result));
        }

        if (is_bool($result)) {
            return $result ? CommandExitCode::SUCCESS : CommandExitCode::FAILURE;
        }

        return CommandExitCode::SUCCESS;
    }
}




