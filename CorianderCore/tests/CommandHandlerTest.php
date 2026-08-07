<?php

namespace CorianderCore\Tests;

use PHPUnit\Framework\TestCase;
use CorianderCore\Core\Console\CommandHandler;
use CorianderCore\Core\Console\CommandExitCode;

class CommandHandlerTest extends TestCase
{
    /**
     * @var CommandHandler|\PHPUnit\Framework\MockObject\MockObject $commandHandler
     * Holds either an instance of CommandHandler or a mock object of CommandHandler for testing.
     */
    protected $commandHandler;

    /**
     * This method is executed before each test.
     * It initializes a new instance of CommandHandler to ensure a clean state for each test case.
     */
    protected function setUp(): void
    {
        // Initialize a new instance of CommandHandler before each test
        $this->commandHandler = new CommandHandler();
    }

    /**
     * Test that the 'hello' command outputs the correct response.
     * This test verifies that when the 'hello' command is executed,
     * the correct message is printed to the output, specifically that
     * the output contains a greeting from "Coriander."
     */
    public function testHelloCommandOutputsCorrectMessage()
    {
        // Start output buffering to capture the output of the 'hello' command
        ob_start();
        $exitCode = $this->commandHandler->handle('hello', []);
        $output = ob_get_clean();

        $this->assertSame(CommandExitCode::SUCCESS, $exitCode);
        // Assert that the expected output message contains specific greeting strings
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
        $this->assertStringContainsString('php coriander make:controller <name> [api]', $output);
        $this->assertStringContainsString('php coriander make:route admin/users', $output);
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

    /**
     * Test that an invalid command triggers an appropriate error message.
     * This test checks if attempting to execute an invalid or unknown command
     * results in an error message and displays the list of available commands.
     */
    public function testInvalidCommandHandling()
    {
        // Start output buffering to capture the output of an invalid command
        ob_start();
        $exitCode = $this->commandHandler->handle('invalidCommand', []);
        $output = ob_get_clean();

        $this->assertSame(CommandExitCode::UNKNOWN_COMMAND, $exitCode);
        // Assert that the output contains the expected error message and command list
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

    /**
     * Test that a command without an 'execute' method throws an exception.
     * This test creates a mock class that lacks the required 'execute' method,
     * and ensures that CommandHandler throws an appropriate exception
     * when trying to execute such a command.
     */
    public function testMissingExecuteMethod()
    {
        // Create a mock class without an 'execute' method
        $mockCommandClass = get_class(new class {
            // No execute method present in this mock class
        });

        // Partially mock CommandHandler to prevent it from calling real methods
        $this->commandHandler = $this->getMockBuilder(CommandHandler::class)
            ->onlyMethods(['listCommands']) // Only mock the 'listCommands' method
            ->getMock();

        // Ensure 'listCommands' is never called during this test
        $this->commandHandler->expects($this->never())->method('listCommands');

        // Modify the 'commands' property of CommandHandler to include the mock command
        $commandsProperty = (new \ReflectionClass(CommandHandler::class))->getProperty('commands');
        $commands = $commandsProperty->getValue($this->commandHandler);
        $commands['noExecute'] = $mockCommandClass; // Add the mock command without execute method
        $commandsProperty->setValue($this->commandHandler, $commands);

        // Expect an exception when attempting to execute a command that lacks an 'execute' method
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage("Command noExecute does not have an execute method.");

        // Attempt to handle the 'noExecute' command, which should trigger an exception
        $this->commandHandler->handle('noExecute', []);
    }
}


