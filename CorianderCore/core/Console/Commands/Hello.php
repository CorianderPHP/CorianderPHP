<?php
declare(strict_types=1);

/*
 * Hello command outputs a friendly greeting used primarily for testing the
 * console infrastructure.
 */

namespace CorianderCore\Core\Console\Commands;

use CorianderCore\Core\Console\ConsoleOutput;

class Hello
{
    /**
     * Executes the 'hello' command.
     * 
     * This method outputs a friendly greeting message to the user when the
     * 'hello' command is invoked through the CommandHandler. It serves as 
     * a basic example of a command that can be executed in the console.
     *
     * @return void
     */
    public function execute(): void
    {
        ConsoleOutput::print("Hi! I'm &2Coriander&7, how can I help you?");
    }
}

