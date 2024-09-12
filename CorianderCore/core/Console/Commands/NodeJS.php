<?php

namespace CorianderCore\Console\Commands;

class NodeJS
{
    /**
     * Executes the nodejs command.
     *
     * @param array $args The arguments passed to the nodejs command.
     */
    public function execute(array $args)
    {
        // Check if any argument was passed
        if (empty($args)) {
            echo "Please provide a Node.js command to run, e.g., 'install', 'watch-ts', 'build-ts', etc." . PHP_EOL;
            return;
        }

        // Map supported npm commands
        $npmCommands = [
            'install' => 'npm install',
            'watch-ts' => 'npm run watch-ts',
            'build-ts' => 'npm run build-ts',
            'watch-js' => 'npm run watch-js',
            'build-js' => 'npm run build-js',
            'watch-tailwind' => 'npm run watch-tailwind',
            'build-tailwind' => 'npm run build-tailwind',
            'build-all' => 'npm run build-all',
            'build-prod' => 'npm run build-prod',
        ];

        // Get the command from the arguments
        $command = $args[0];

        // Check if the command is valid
        if (!array_key_exists($command, $npmCommands)) {
            echo "Unknown command: {$command}. Available commands are: " . implode(', ', array_keys($npmCommands)) . PHP_EOL;
            return;
        }

        // Change to the Node directory
        $nodeDir = PROJECT_ROOT . '/nodejs';
        chdir($nodeDir);

        // Execute the npm command using proc_open to capture real-time output
        $npmCommand = $npmCommands[$command];
        $descriptors = [
            0 => ['pipe', 'r'], // stdin
            1 => ['pipe', 'w'], // stdout
            2 => ['pipe', 'w'], // stderr
        ];

        $process = proc_open($npmCommand, $descriptors, $pipes);

        if (is_resource($process)) {
            // Capture real-time output from the stdout and stderr
            while (!feof($pipes[1])) {
                echo fgets($pipes[1]);
            }
            while (!feof($pipes[2])) {
                echo fgets($pipes[2]);
            }

            // Close pipes and terminate the process
            fclose($pipes[1]);
            fclose($pipes[2]);
            proc_close($process);
        } else {
            echo "Error: Could not start the process for {$npmCommand}." . PHP_EOL;
        }
    }
}
