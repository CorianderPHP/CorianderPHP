<?php
declare(strict_types=1);

/*
 * NodeJS command bridges to npm allowing execution of Node scripts from the
 * CorianderPHP console.
 */

namespace CorianderCore\Core\Console\Commands;

use CorianderCore\Core\Console\ConsoleOutput;

class NodeJS
{
    /**
     * Executes the provided Node.js (npm) command.
     *
     * @param array<int, string> $args The arguments passed to the 'nodejs' command (e.g., 'install', 'run build').
     * @return void
     */
    public function execute(array $args): void
    {
        if (empty($args)) {
            ConsoleOutput::print("&4[Error]&7 Please provide a Node.js command to run. (e.g, php coriander nodejs run watch-tw)");
            return;
        }

        $nodeDir = PROJECT_ROOT . '/nodejs';
        if (!is_dir($nodeDir)) {
            ConsoleOutput::print('&4[Error]&7 Node.js directory not found: ' . $nodeDir);
            return;
        }

        $command = array_merge(['npm'], array_values($args));

        $descriptors = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        $process = proc_open($command, $descriptors, $pipes, $nodeDir);
        if (!is_resource($process)) {
            ConsoleOutput::print('&4[Error]&7 Could not start the npm process.');
            return;
        }

        fclose($pipes[0]);

        $stdout = stream_get_contents($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);

        fclose($pipes[1]);
        fclose($pipes[2]);

        $exitCode = proc_close($process);

        if (is_string($stdout) && $stdout !== '') {
            echo $stdout;
        }

        if (is_string($stderr) && $stderr !== '') {
            echo $stderr;
        }

        if ($exitCode !== 0) {
            ConsoleOutput::print('&4[Error]&7 npm command failed with exit code ' . (string) $exitCode . '.');
        }
    }
}
