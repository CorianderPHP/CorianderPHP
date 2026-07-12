<?php
declare(strict_types=1);

/*
 * NodeJS command bridges to npm allowing execution of Node scripts from the
 * CorianderPHP console.
 */

namespace CorianderCore\Core\Console\Commands;

use CorianderCore\Core\Console\ConsoleOutput;
use CorianderCore\Core\Console\CommandExitCode;
use CorianderCore\Core\Console\Services\Node\NpmExecutableResolver;

class NodeJS
{
    private NpmExecutableResolver $npmExecutableResolver;

    public function __construct(?string $npmExecutableOverride = null, ?callable $executableLocator = null)
    {
        $this->npmExecutableResolver = new NpmExecutableResolver($npmExecutableOverride, $executableLocator);
    }

    /**
     * Executes the provided Node.js (npm) command.
     *
     * @param array<int, string> $args The arguments passed to the 'nodejs' command (e.g., 'install', 'run build').
     * @return int Process exit code.
     */
    public function execute(array $args): int
    {
        if (empty($args)) {
            ConsoleOutput::print("&4[Error]&7 Please provide a Node.js command to run. (e.g, php coriander nodejs run watch-tw)");
            return CommandExitCode::INVALID_USAGE;
        }

        $nodeDir = PROJECT_ROOT . '/nodejs';
        if (!is_dir($nodeDir)) {
            ConsoleOutput::print('&4[Error]&7 Node.js directory not found: ' . $nodeDir);
            return CommandExitCode::FAILURE;
        }

        if ($this->isWatchCommand($args)) {
            ConsoleOutput::print('&2Starting watcher...&7 Press Ctrl+C to stop.');
        }

        $npmExecutable = $this->resolveNpmExecutable();
        $command = array_merge([$npmExecutable], array_values($args));

        $descriptors = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        $process = proc_open($command, $descriptors, $pipes, $nodeDir);
        if (!is_resource($process)) {
            ConsoleOutput::print('&4[Error]&7 Could not start the npm process.');
            return CommandExitCode::FAILURE;
        }

        fclose($pipes[0]);
        stream_set_blocking($pipes[1], false);
        stream_set_blocking($pipes[2], false);

        $observedExitCode = $this->streamProcessOutput($process, $pipes[1], $pipes[2]);

        fclose($pipes[1]);
        fclose($pipes[2]);

        $closedExitCode = proc_close($process);
        $exitCode = $closedExitCode === -1 && $observedExitCode !== null
            ? $observedExitCode
            : $closedExitCode;
        if ($exitCode !== 0) {
            ConsoleOutput::print('&4[Error]&7 npm command failed with exit code ' . (string) $exitCode . '.');
            ConsoleOutput::print('&8|&7 Resolved npm executable: ' . $npmExecutable);
            return is_int($exitCode) ? max(CommandExitCode::FAILURE, min(255, $exitCode)) : CommandExitCode::FAILURE;
        }

        return CommandExitCode::SUCCESS;
    }

    /**
     * @param resource $process
     * @param resource $stdoutPipe
     * @param resource $stderrPipe
     */
    private function streamProcessOutput($process, $stdoutPipe, $stderrPipe): ?int
    {
        while (true) {
            $read = [$stdoutPipe, $stderrPipe];
            $write = null;
            $except = null;
            $changed = @stream_select($read, $write, $except, 0, 200000);

            if ($changed === false) {
                return null;
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

                return $this->normalizeObservedExitCode($status['exitcode'] ?? null);
            }
        }
    }

    private function normalizeObservedExitCode(mixed $exitCode): ?int
    {
        if (!is_int($exitCode) || $exitCode < 0) {
            return null;
        }

        return $exitCode;
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
        return $this->npmExecutableResolver->resolve();
    }
}
