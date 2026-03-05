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

        if ($this->isWatchCommand($args)) {
            ConsoleOutput::print('&2Starting watcher...&7 Press Ctrl+C to stop.');
        }

        $command = array_merge([$this->resolveNpmExecutable()], array_values($args));

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
        stream_set_blocking($pipes[1], false);
        stream_set_blocking($pipes[2], false);

        $this->streamProcessOutput($process, $pipes[1], $pipes[2]);

        fclose($pipes[1]);
        fclose($pipes[2]);

        $exitCode = proc_close($process);
        if ($exitCode !== 0) {
            ConsoleOutput::print('&4[Error]&7 npm command failed with exit code ' . (string) $exitCode . '.');
        }
    }

    /**
     * @param resource $process
     * @param resource $stdoutPipe
     * @param resource $stderrPipe
     */
    private function streamProcessOutput($process, $stdoutPipe, $stderrPipe): void
    {
        while (true) {
            $read = [$stdoutPipe, $stderrPipe];
            $write = null;
            $except = null;
            $changed = @stream_select($read, $write, $except, 0, 200000);

            if ($changed === false) {
                break;
            }

            foreach ($read as $pipe) {
                $chunk = stream_get_contents($pipe);
                if (!is_string($chunk) || $chunk === '') {
                    continue;
                }

                echo $chunk;
            }

            $status = proc_get_status($process);
            if (!$status['running']) {
                $remainingStdout = stream_get_contents($stdoutPipe);
                if (is_string($remainingStdout) && $remainingStdout !== '') {
                    echo $remainingStdout;
                }

                $remainingStderr = stream_get_contents($stderrPipe);
                if (is_string($remainingStderr) && $remainingStderr !== '') {
                    echo $remainingStderr;
                }

                break;
            }
        }
    }

    /**
     * @param array<int,string> $args
     */
    private function isWatchCommand(array $args): bool
    {
        if (($args[0] ?? null) !== 'run') {
            return false;
        }

        $script = strtolower((string) ($args[1] ?? ''));
        return str_contains($script, 'watch');
    }

    private function resolveNpmExecutable(): string
    {
        if (DIRECTORY_SEPARATOR !== '\\') {
            return 'npm';
        }

        $candidates = [];
        $programFiles = getenv('ProgramFiles');
        if (is_string($programFiles) && $programFiles !== '') {
            $candidates[] = rtrim($programFiles, '\\/') . '\\nodejs\\npm.cmd';
        }

        $programFilesX86 = getenv('ProgramFiles(x86)');
        if (is_string($programFilesX86) && $programFilesX86 !== '') {
            $candidates[] = rtrim($programFilesX86, '\\/') . '\\nodejs\\npm.cmd';
        }

        foreach ($candidates as $candidate) {
            if (is_file($candidate)) {
                return $candidate;
            }
        }

        return 'npm.cmd';
    }
}
