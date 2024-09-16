<?php

use PHPUnit\Framework\TestCase;
use CorianderCore\Console\CommandHandler;

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
        $this->commandHandler->handle('hello', []);
        $output = ob_get_clean();

        // Assert that the expected output message contains specific greeting strings
        $this->assertStringContainsString("Hi! I'm", $output);
        $this->assertStringContainsString("Coriander", $output);
        $this->assertStringContainsString(", how can I help you?", $output);
    }

    /**
     * Test that the 'help' command lists all available commands.
     * This test ensures that when the 'help' command is executed,
     * it outputs the list of available commands, including 'hello' and 'help.'
     */
    public function testListCommands()
    {
        // Start output buffering to capture the output of the 'help' command
        ob_start();
        $this->commandHandler->handle('help', []);
        $output = ob_get_clean();

        // Assert that the output contains the expected command list
        $this->assertStringContainsString('Available commands:', $output);
        $this->assertStringContainsString('hello', $output);
        $this->assertStringContainsString('help', $output);
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
        $this->commandHandler->handle('invalidCommand', []);
        $output = ob_get_clean();

        // Assert that the output contains the expected error message and command list
        $this->assertStringContainsString('Unknown command: invalidCommand', $output);
        $this->assertStringContainsString('Available commands:', $output);
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
        $commandsProperty->setAccessible(true);
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
