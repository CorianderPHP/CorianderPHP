<?php

namespace CorianderCore\Console\Commands;

use CorianderCore\Console\ConsoleOutput;

class NodeJS
{
    /**
     * Executes the provided Node.js (npm) command.
     *
     * This method takes any arguments passed to the 'nodejs' command, builds
     * a full npm command, and executes it within the Node.js project directory.
     * It captures and outputs the real-time stdout and stderr from the npm process.
     *
     * @param array $args The arguments passed to the 'nodejs' command (e.g., 'install', 'run build').
     */
    public function execute(array $args)
    {
        // Check if any arguments were passed; if not, request a command.
        if (empty($args)) {
            ConsoleOutput::print("&4[Error]&7 Please provide a Node.js command to run. (e.g, php coriander nodejs run watch-tw)");
            return;
        }

        // Build the full npm command by joining the arguments (e.g., 'npm install', 'npm run build')
        $npmCommand = 'npm ' . implode(' ', $args);

        // Change the current working directory to the Node.js runtime folder
        $nodeDir = PROJECT_ROOT . '/nodejs';
        chdir($nodeDir);

        // Set up process descriptors to handle stdin, stdout, and stderr
        $descriptors = [
            0 => ['pipe', 'r'], // stdin (not used here)
            1 => ['pipe', 'w'], // stdout (for capturing standard output)
            2 => ['pipe', 'w'], // stderr (for capturing error output)
        ];

        // Start the npm process using proc_open, which allows real-time output handling
        $process = proc_open($npmCommand, $descriptors, $pipes);

        if (is_resource($process)) {
            // Read and output the real-time stdout content
            while (!feof($pipes[1])) {
                echo fgets($pipes[1]);
            }

            // Read and output the real-time stderr content (if any errors occur)
            while (!feof($pipes[2])) {
                echo fgets($pipes[2]);
            }

            // Close the pipes and terminate the process
            fclose($pipes[1]);
            fclose($pipes[2]);
            proc_close($process);
        } else {
            // If proc_open failed to start the npm process, output an error message
            ConsoleOutput::print("&4[Error]&7 Could not start the process for {$npmCommand}.");
        }
    }
}
