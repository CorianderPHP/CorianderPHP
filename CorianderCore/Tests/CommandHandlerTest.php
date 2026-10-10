<?php

namespace CorianderCore\Tests;

use PHPUnit\Framework\TestCase;
use CorianderCore\Core\Console\CommandHandler;
use CorianderCore\Core\Console\CommandExitCode;

class CommandHandlerTest extends TestCase
{
    /** @var CommandHandler|\PHPUnit\Framework\MockObject\MockObject */
    protected $commandHandler;

    protected function setUp(): void
    {
        $this->commandHandler = new CommandHandler();
    }

    public function testHelloCommandOutputsCorrectMessage()
    {
        ob_start();
        $exitCode = $this->commandHandler->handle('hello', []);
        $output = ob_get_clean();

        $this->assertSame(CommandExitCode::SUCCESS, $exitCode);
        $this->assertStringContainsString("Hi! I'm", $output);
        $this->assertStringContainsString("Coriander", $output);
        $this->assertStringContainsString(", how can I help you?", $output);
    }

    public function testEmptyCommandOutputsShortOverview()
    {
        ob_start();
        $exitCode = $this->commandHandler->handle('', []);
        $output = ob_get_clean();

        $this->assertSame(CommandExitCode::SUCCESS, $exitCode);
        $this->assertStringContainsString('CorianderPHP CLI', $output);
        $this->assertStringContainsString('Common commands:', $output);
        $this->assertStringContainsString('Run php coriander help for detailed usage.', $output);
    }

    public function testHelpCommandOutputsDetailedUsage()
    {
        ob_start();
        $exitCode = $this->commandHandler->handle('help', []);
        $output = ob_get_clean();

        $this->assertSame(CommandExitCode::SUCCESS, $exitCode);
        $this->assertStringContainsString('CorianderPHP CLI Help', $output);
        $this->assertStringContainsString('Commands:', $output);
        $this->assertStringContainsString('Show CLI help.', $output);
        $this->assertStringContainsString('Generate project files', $output);
        $this->assertStringContainsString('php coriander nodejs run build-prod', $output);
    }

    public function testFocusedHelpOutputsCommandUsage(): void
    {
        ob_start();
        $exitCode = $this->commandHandler->handle('help', ['make']);
        $output = ob_get_clean();

        $this->assertSame(CommandExitCode::SUCCESS, $exitCode);
        $this->assertStringContainsString('make', $output);
        $this->assertStringNotContainsString('make:controller', $output);
        $this->assertStringContainsString('php coriander make:route "users/[id].post"', $output);
    }

    public function testHelpFlagsOutputDetailedUsage(): void
    {
        ob_start();
        $exitCode = $this->commandHandler->handle('--help', []);
        $output = ob_get_clean();

        $this->assertSame(CommandExitCode::SUCCESS, $exitCode);
        $this->assertStringContainsString('CorianderPHP CLI Help', $output);
    }

    public function testCommandHelpFlagOutputsFocusedUsage(): void
    {
        ob_start();
        $exitCode = $this->commandHandler->handle('nodejs', ['--help']);
        $output = ob_get_clean();

        $this->assertSame(CommandExitCode::SUCCESS, $exitCode);
        $this->assertStringContainsString('nodejs', $output);
        $this->assertStringContainsString('php coriander nodejs install', $output);
    }

    public function testInvalidCommandHandling()
    {
        ob_start();
        $exitCode = $this->commandHandler->handle('invalidCommand', []);
        $output = ob_get_clean();

        $this->assertSame(CommandExitCode::UNKNOWN_COMMAND, $exitCode);
        $this->assertStringContainsString('Unknown command: invalidCommand', $output);
        $this->assertStringContainsString('Common commands:', $output);
    }

    public function testCommandReturnCodeIsPropagated(): void
    {
        $mockCommandClass = get_class(new class {
            public function execute(array $args): int
            {
                return 7;
            }
        });

        $commandsProperty = (new \ReflectionClass(CommandHandler::class))->getProperty('commands');
        $commands = $commandsProperty->getValue($this->commandHandler);
        $commands['returnsCode'] = $mockCommandClass;
        $commandsProperty->setValue($this->commandHandler, $commands);

        ob_start();
        $exitCode = $this->commandHandler->handle('returnsCode', []);
        ob_end_clean();

        $this->assertSame(7, $exitCode);
    }

    public function testMissingExecuteMethod()
    {
        $mockCommandClass = get_class(new class {
        });

        $this->commandHandler = $this->getMockBuilder(CommandHandler::class)
            ->onlyMethods(['listCommands'])
            ->getMock();

        $this->commandHandler->expects($this->never())->method('listCommands');

        $commandsProperty = (new \ReflectionClass(CommandHandler::class))->getProperty('commands');
        $commands = $commandsProperty->getValue($this->commandHandler);
        $commands['noExecute'] = $mockCommandClass;
        $commandsProperty->setValue($this->commandHandler, $commands);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage("Command noExecute does not have an execute method.");

        $this->commandHandler->handle('noExecute', []);
    }
}

