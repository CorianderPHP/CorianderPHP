<?php
declare(strict_types=1);

namespace CorianderCore\Core\Console\Services\Node;

/**
 * Resolves the npm launcher in a way that avoids project-local Windows shims.
 */
class NpmExecutableResolver
{
    /**
     * @var callable(string):array<int,string>|null
     */
    private $executableLocator;

    public function __construct(private ?string $executableOverride = null, ?callable $executableLocator = null)
    {
        $this->executableLocator = $executableLocator;
    }

    public function resolve(): string
    {
        if (is_string($this->executableOverride) && trim($this->executableOverride) !== '') {
            return trim($this->executableOverride);
        }

        $environmentOverride = $this->resolveEnvironmentOverride();
        if ($environmentOverride !== null) {
            return $environmentOverride;
        }

        if (DIRECTORY_SEPARATOR !== '\\') {
            return 'npm';
        }

        foreach ($this->windowsCandidates() as $candidate) {
            if ($this->isUsableNpmExecutable($candidate)) {
                return $candidate;
            }
        }

        return 'npm.cmd';
    }

    private function resolveEnvironmentOverride(): ?string
    {
        foreach (['CORIANDER_NPM_EXECUTABLE', 'NPM_EXECUTABLE'] as $name) {
            $value = getenv($name);
            if (is_string($value) && trim($value) !== '') {
                return trim($value);
            }
        }

        return null;
    }

    /**
     * @return array<int,string>
     */
    private function windowsCandidates(): array
    {
        $candidates = [];

        foreach ($this->locateExecutable('node') as $nodeExecutable) {
            if (is_file($nodeExecutable)) {
                $candidates[] = dirname($nodeExecutable) . '\\npm.cmd';
            }
        }

        foreach (['ProgramFiles', 'ProgramFiles(x86)'] as $environmentName) {
            $path = getenv($environmentName);
            if (is_string($path) && $path !== '') {
                $candidates[] = rtrim($path, '\\/') . '\\nodejs\\npm.cmd';
            }
        }

        foreach ($this->locateExecutable('npm') as $npmExecutable) {
            $candidates[] = $npmExecutable;
        }

        return array_values(array_unique($candidates));
    }

    /**
     * @return array<int,string>
     */
    private function locateExecutable(string $name): array
    {
        if ($this->executableLocator !== null) {
            $locator = $this->executableLocator;
            $located = $locator($name);

            return is_array($located)
                ? $this->normalizeExecutableList($located)
                : [];
        }

        if (DIRECTORY_SEPARATOR !== '\\') {
            return [];
        }

        $descriptors = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        $process = @proc_open(['where.exe', $name], $descriptors, $pipes);
        if (!is_resource($process)) {
            return [];
        }

        fclose($pipes[0]);
        $output = stream_get_contents($pipes[1]);
        fclose($pipes[1]);
        fclose($pipes[2]);

        if (proc_close($process) !== 0 || !is_string($output)) {
            return [];
        }

        return $this->normalizeExecutableList(preg_split('/\R/', $output) ?: []);
    }

    /**
     * @param array<int,mixed> $paths
     * @return array<int,string>
     */
    private function normalizeExecutableList(array $paths): array
    {
        return array_values(array_filter(
            array_map(static fn(mixed $path): string => is_string($path) ? trim($path) : '', $paths),
            static fn(string $path): bool => $path !== ''
        ));
    }

    private function isUsableNpmExecutable(string $candidate): bool
    {
        $candidate = trim($candidate, "\" \t\n\r\0\x0B");
        if ($candidate === '' || !is_file($candidate)) {
            return false;
        }

        if (strtolower(substr($candidate, -4)) !== '.cmd') {
            return true;
        }

        return is_file(dirname($candidate) . '\\node_modules\\npm\\bin\\npm-cli.js');
    }
}
