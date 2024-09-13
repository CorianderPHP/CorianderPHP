<?php

namespace CorianderCore\Console\Commands;

class NodeJS
{
    /**
     * Executes the nodejs command with any user-provided command.
     *
     * @param array $args The arguments passed to the nodejs command.
     */
    public function execute(array $args)
    {
        // Check if any argument was passed
        if (empty($args)) {
            echo "Please provide a Node.js command to run." . PHP_EOL;
            return;
        }

        // Build the full npm command from the provided arguments
        $npmCommand = 'npm ' . implode(' ', $args);

        // Change to the Node directory
        $nodeDir = PROJECT_ROOT . '/nodejs';
        chdir($nodeDir);

        // Execute the npm command using proc_open to capture real-time output
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
