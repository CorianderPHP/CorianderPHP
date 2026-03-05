<?php
declare(strict_types=1);

namespace CorianderCore\Core\Console\Services\Updater;

use RuntimeException;

class GitHubReleaseService
{
    private const API_BASE = 'https://api.github.com/repos/';
    private const MAX_RETRIES = 3;
    private const ALLOWED_DOWNLOAD_HOSTS = ['github.com', 'api.github.com', 'codeload.github.com'];

    private string $repository;

    public function __construct(?string $repository = null)
    {
        $candidate = $repository ?? $this->detectRepositoryFromComposer() ?? 'CorianderPHP/CorianderPHP';
        $this->assertRepositoryIsAllowed($candidate);
        $this->repository = $candidate;
    }

    /**
     * @return array{tag:string, zip_url:string}
     */
    public function fetchLatestRelease(): array
    {
        $release = $this->requestJson(self::API_BASE . $this->repository . '/releases/latest', true);
        if (is_array($release) && isset($release['tag_name'])) {
            return [
                'tag' => (string) $release['tag_name'],
                'zip_url' => (string) ($release['zipball_url'] ?? $this->buildZipUrl((string) $release['tag_name'])),
            ];
        }

        $tags = $this->requestJson(self::API_BASE . $this->repository . '/tags?per_page=1', false);
        if (!is_array($tags) || !isset($tags[0]['name'])) {
            throw new RuntimeException('Unable to find any GitHub release or tag for updates.');
        }

        $tag = (string) $tags[0]['name'];
        return [
            'tag' => $tag,
            'zip_url' => $this->buildZipUrl($tag),
        ];
    }

    public function downloadArchive(string $url, string $destination): void
    {
        $this->assertDownloadUrlIsAllowed($url);

        $attempt = 0;
        $lastFailure = 'Unknown network error while downloading update archive.';

        while ($attempt < self::MAX_RETRIES) {
            $attempt++;

            $response = $this->requestRaw($url, [
                'Accept: application/octet-stream',
            ], 60);

            if ($response['status'] >= 200 && $response['status'] < 300 && $response['body'] !== '') {
                if (@file_put_contents($destination, $response['body']) === false) {
                    throw new RuntimeException('Failed to save downloaded update archive.');
                }
                return;
            }

            $this->assertNotRateLimited($response['status'], $response['headers']);
            $lastFailure = $this->formatHttpFailureMessage('download update archive', $response['status']);

            if (!$this->shouldRetry($response['status']) || $attempt >= self::MAX_RETRIES) {
                break;
            }

            usleep($this->retryDelayMicroseconds($attempt));
        }

        throw new RuntimeException($lastFailure);
    }


    private function assertRepositoryIsAllowed(string $repository): void
    {
        if (preg_match('/^[A-Za-z0-9_.-]+\/[A-Za-z0-9_.-]+$/', $repository) !== 1) {
            throw new RuntimeException('Invalid GitHub repository format. Expected "owner/repository".');
        }
    }

    private function assertDownloadUrlIsAllowed(string $url): void
    {
        $parts = parse_url($url);
        if (!is_array($parts)) {
            throw new RuntimeException('Invalid update archive URL.');
        }

        $scheme = strtolower((string) ($parts['scheme'] ?? ''));
        $host = strtolower((string) ($parts['host'] ?? ''));

        if ($scheme !== 'https') {
            throw new RuntimeException('Update archive URL must use HTTPS.');
        }

        if (!in_array($host, self::ALLOWED_DOWNLOAD_HOSTS, true)) {
            throw new RuntimeException('Update archive URL host is not allowed.');
        }
    }
    private function buildZipUrl(string $tag): string
    {
        return 'https://github.com/' . $this->repository . '/archive/refs/tags/' . rawurlencode($tag) . '.zip';
    }

    private function detectRepositoryFromComposer(): ?string
    {
        if (!defined('PROJECT_ROOT')) {
            return null;
        }

        $composerPath = PROJECT_ROOT . '/composer.json';
        if (!file_exists($composerPath)) {
            return null;
        }

        $composer = json_decode((string) file_get_contents($composerPath), true);
        if (!is_array($composer)) {
            return null;
        }

        $homepage = $composer['homepage'] ?? null;
        if (!is_string($homepage)) {
            return null;
        }

        if (!preg_match('#github\.com/([^/]+/[^/]+)#i', $homepage, $matches)) {
            return null;
        }

        return rtrim($matches[1], '/');
    }

    /**
     * @return mixed
     */
    private function requestJson(string $url, bool $allowNotFound): mixed
    {
        $attempt = 0;
        $lastFailure = 'Unknown network error while contacting GitHub API.';

        while ($attempt < self::MAX_RETRIES) {
            $attempt++;

            $response = $this->requestRaw($url, [
                'Accept: application/vnd.github+json',
            ], 30);

            if ($response['status'] === 404 && $allowNotFound) {
                return null;
            }

            if ($response['status'] >= 200 && $response['status'] < 300) {
                $decoded = json_decode($response['body'], true);
                if (json_last_error() !== JSON_ERROR_NONE) {
                    throw new RuntimeException('Invalid response from GitHub update API.');
                }
                return $decoded;
            }

            $this->assertNotRateLimited($response['status'], $response['headers']);
            $lastFailure = $this->formatHttpFailureMessage('reach GitHub update API', $response['status']);

            if (!$this->shouldRetry($response['status']) || $attempt >= self::MAX_RETRIES) {
                break;
            }

            usleep($this->retryDelayMicroseconds($attempt));
        }

        throw new RuntimeException($lastFailure);
    }

    /**
     * @param string[] $extraHeaders
     * @return array{status:int, body:string, headers: array<int, string>}
     */
    private function requestRaw(string $url, array $extraHeaders, int $timeout): array
    {
        $headers = array_merge([
            'User-Agent: CorianderPHP-Updater',
        ], $extraHeaders);

        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'header' => implode("\r\n", $headers),
                'ignore_errors' => true,
                'timeout' => $timeout,
            ],
        ]);

        $body = @file_get_contents($url, false, $context);
        $responseHeaders = $http_response_header ?? [];

        return [
            'status' => $this->extractStatusCode($responseHeaders),
            'body' => is_string($body) ? $body : '',
            'headers' => $responseHeaders,
        ];
    }

    private function shouldRetry(int $statusCode): bool
    {
        return $statusCode === 0 || $statusCode === 429 || $statusCode >= 500;
    }

    private function retryDelayMicroseconds(int $attempt): int
    {
        return match ($attempt) {
            1 => 250_000,
            2 => 500_000,
            default => 1_000_000,
        };
    }

    /**
     * @param array<int, string> $headers
     */
    private function assertNotRateLimited(int $statusCode, array $headers): void
    {
        if ($statusCode !== 403 && $statusCode !== 429) {
            return;
        }

        $remaining = $this->extractHeaderValue($headers, 'x-ratelimit-remaining');
        if ($remaining !== '0') {
            return;
        }

        $resetValue = $this->extractHeaderValue($headers, 'x-ratelimit-reset');
        if ($resetValue !== null && ctype_digit($resetValue)) {
            $resetTimestamp = (int) $resetValue;
            $waitSeconds = max(0, $resetTimestamp - time());
            throw new RuntimeException('GitHub API rate limit reached. Retry in about ' . $waitSeconds . ' seconds.');
        }

        throw new RuntimeException('GitHub API rate limit reached. Retry later.');
    }

    private function formatHttpFailureMessage(string $action, int $statusCode): string
    {
        if ($statusCode === 0) {
            return 'Unable to ' . $action . ': network error or timeout.';
        }

        return 'Unable to ' . $action . ': HTTP ' . $statusCode . '.';
    }

    /**
     * @param array<int, string> $headers
     */
    private function extractStatusCode(array $headers): int
    {
        $statusCode = 0;

        foreach ($headers as $header) {
            if (preg_match('#^HTTP/\S+\s+(\d{3})#', $header, $matches)) {
                $statusCode = (int) $matches[1];
            }
        }

        return $statusCode;
    }

    /**
     * @param array<int, string> $headers
     */
    private function extractHeaderValue(array $headers, string $name): ?string
    {
        $needle = strtolower($name) . ':';

        foreach ($headers as $header) {
            if (str_starts_with(strtolower($header), $needle)) {
                return trim(substr($header, strlen($needle)));
            }
        }

        return null;
    }
}

