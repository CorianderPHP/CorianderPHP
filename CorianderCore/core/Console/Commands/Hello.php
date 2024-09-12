<?php

namespace CorianderCore\Console\Commands;

class Hello
{
    /**
     * Executes the hello command.
     * 
     * This command outputs a friendly greeting message to the user when invoked.
     */
    public function execute()
    {
        echo "Hi! I'm Coriander, how can I help you?" . PHP_EOL;
    }
}
