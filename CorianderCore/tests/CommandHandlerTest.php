<?php

use PHPUnit\Framework\TestCase;
use CorianderCore\Console\CommandHandler;

class CommandHandlerTest extends TestCase
{
    protected $commandHandler;

    protected function setUp(): void
    {
        // Initialize a new instance of CommandHandler before each test
        $this->commandHandler = new CommandHandler();
    }

    /**
     * Test that the 'hello' command outputs the correct response.
     * This test verifies that when the 'hello' command is executed, 
     * the correct message is printed to the output.
     */
    public function testHelloCommandOutputsCorrectMessage()
    {
        // Start output buffering to capture command output
        ob_start();
        $this->commandHandler->handle('hello', []);
        $output = ob_get_clean();

        // Assert that the expected output message is present
        $this->assertStringContainsString("Hi! I'm Coriander, how can I help you?", $output);
    }

    /**
     * Test that the 'help' command lists all available commands.
     * This test ensures that when the 'help' command is executed, 
     * it correctly outputs the list of available commands.
     */
    public function testListCommands()
    {
        // Start output buffering to capture command output
        ob_start();
        $this->commandHandler->handle('help', []);
        $output = ob_get_clean();

        // Assert that the output contains the list of commands
        $this->assertStringContainsString('Available commands:', $output);
        $this->assertStringContainsString('hello', $output);
        $this->assertStringContainsString('help', $output);
    }

    /**
     * Test that an invalid command triggers an appropriate error message.
     * This test checks if attempting to execute an invalid or unknown command
     * results in an error message being displayed, along with the list of available commands.
     */
    public function testInvalidCommandHandling()
    {
        // Start output buffering to capture command output
        ob_start();
        $this->commandHandler->handle('invalidCommand', []);
        $output = ob_get_clean();

        // Assert that the output contains the expected error message and command list
        $this->assertStringContainsString('Unknown command: invalidCommand', $output);
        $this->assertStringContainsString('Available commands:', $output);
    }

    /**
     * Test that a command without an 'execute' method throws an exception.
     * This test creates a mock class that lacks the required 'execute' method
     * and ensures that the CommandHandler throws an appropriate exception when attempting 
     * to execute the command.
     */
    public function testMissingExecuteMethod()
    {
        // Create a mock class without an 'execute' method
        $mockCommandClass = get_class(new class {
            // No execute method present
        });

        // Partially mock CommandHandler to prevent calling real methods
        $this->commandHandler = $this->getMockBuilder(CommandHandler::class)
            ->onlyMethods(['listCommands'])
            ->getMock();

        // Ensure 'listCommands' is never called during this test
        $this->commandHandler->expects($this->never())->method('listCommands');

        // Modify the 'commands' array in the CommandHandler to include the mock command
        $commandsProperty = (new \ReflectionClass(CommandHandler::class))->getProperty('commands');
        $commandsProperty->setAccessible(true);
        $commands = $commandsProperty->getValue($this->commandHandler);
        $commands['noExecute'] = $mockCommandClass;
        $commandsProperty->setValue($this->commandHandler, $commands);

        // Expect an exception to be thrown when attempting to execute the 'noExecute' command
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage("Command noExecute does not have an execute method.");

        // Attempt to handle the 'noExecute' command
        $this->commandHandler->handle('noExecute', []);
    }
}
