<?php
declare(strict_types=1);

namespace CorianderCore\Core\Console\Services\Updater;

class PostUpdateTasksService
{
    /**
     * @return array{composer_dump_autoload: array{success: bool, exit_code: int, output: string}, cache_clear: array{success: bool, exit_code: int, output: string}|null}
     */
    public function run(bool $clearCache): array
    {
        $composerResult = $this->runComposerDumpAutoload();
        $cacheResult = null;

        if ($clearCache) {
            $php = escapeshellarg(PHP_BINARY);
            $coriander = escapeshellarg((defined('PROJECT_ROOT') ? PROJECT_ROOT : getcwd()) . '/coriander');
            $cacheResult = $this->runCommand("{$php} {$coriander} cache clear");
        }

        return [
            'composer_dump_autoload' => $composerResult,
            'cache_clear' => $cacheResult,
        ];
    }

    /**
     * @return array{success: bool, exit_code: int, output: string}
     */
    private function runComposerDumpAutoload(): array
    {
        $commands = [
            'composer dump-autoload',
            escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg((defined('PROJECT_ROOT') ? PROJECT_ROOT : getcwd()) . '/composer.phar') . ' dump-autoload',
        ];

        $lastResult = [
            'success' => false,
            'exit_code' => 1,
            'output' => 'Unable to run composer dump-autoload.',
        ];

        foreach ($commands as $command) {
            $result = $this->runCommand($command);
            if ($result['success']) {
                return $result;
            }

            $lastResult = $result;

            if (!str_contains(strtolower($result['output']), 'not recognized')
                && !str_contains(strtolower($result['output']), 'not found')
                && !str_contains(strtolower($result['output']), 'could not open input file')) {
                return $result;
            }
        }

        return $lastResult;
    }

    /**
     * @return array{success: bool, exit_code: int, output: string}
     */
    private function runCommand(string $command): array
    {
        if (!function_exists('proc_open')) {
            return [
                'success' => false,
                'exit_code' => 1,
                'output' => 'proc_open is not available in this PHP environment.',
            ];
        }

        $descriptors = [
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        $process = proc_open($command, $descriptors, $pipes, defined('PROJECT_ROOT') ? PROJECT_ROOT : null);
        if (!is_resource($process)) {
            return [
                'success' => false,
                'exit_code' => 1,
                'output' => 'Unable to start process: ' . $command,
            ];
        }

        $stdout = stream_get_contents($pipes[1]);
        fclose($pipes[1]);

        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[2]);

        $exitCode = proc_close($process);
        $output = trim((string) $stdout . "\n" . (string) $stderr);

        return [
            'success' => $exitCode === 0,
            'exit_code' => $exitCode,
            'output' => $output,
        ];
    }
}
