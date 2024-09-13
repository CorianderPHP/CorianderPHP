<?php

namespace CorianderCore\Console\Commands;

class Hello
{
    /**
     * Executes the 'hello' command.
     * 
     * This method outputs a friendly greeting message to the user when the
     * 'hello' command is invoked through the CommandHandler. It serves as 
     * a basic example of a command that can be executed in the console.
     */
    public function execute()
    {
        echo "Hi! I'm Coriander, how can I help you?" . PHP_EOL;
    }
}

