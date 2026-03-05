<?php
declare(strict_types=1);

namespace CorianderCore\Core\Security;

use Nyholm\Psr7\Response;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Enforces API request body size limits and per-request execution timeout.
 */
class ApiRequestLimitsMiddleware implements MiddlewareInterface
{
    private int $maxBodyBytes;
    private int $timeoutSeconds;

    /**
     * @var array<int,string>
     */
    private array $apiPrefixes;

    /**
     * @param array<int,string>|null $apiPrefixes
     */
    public function __construct(?int $maxBodyBytes = null, ?int $timeoutSeconds = null, ?array $apiPrefixes = null)
    {
        $this->maxBodyBytes = $this->normalizePositiveInt($maxBodyBytes, (int) (getenv('API_MAX_BODY_BYTES') ?: 1_048_576));
        $this->timeoutSeconds = $this->normalizePositiveInt($timeoutSeconds, (int) (getenv('API_TIMEOUT_SECONDS') ?: 15));
        $this->apiPrefixes = $apiPrefixes ?? ['api'];
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        if (!$this->isApiRequest($request)) {
            return $handler->handle($request);
        }

        $this->applyTimeout();

        $declaredLength = $this->extractContentLength($request->getHeaderLine('Content-Length'));
        if ($declaredLength !== null && $declaredLength > $this->maxBodyBytes) {
            return $this->payloadTooLargeResponse('Request body exceeds configured Content-Length limit.');
        }

        $actualSize = $this->resolveBodySize($request);
        if ($actualSize !== null && $actualSize > $this->maxBodyBytes) {
            return $this->payloadTooLargeResponse('Request body exceeds configured size limit.');
        }

        return $handler->handle($request);
    }

    private function normalizePositiveInt(?int $value, int $fallback): int
    {
        $candidate = $value ?? $fallback;
        return $candidate > 0 ? $candidate : $fallback;
    }

    private function applyTimeout(): void
    {
        // Avoid changing global CLI timeout (tests/commands), enforce only for web requests.
        if ($this->timeoutSeconds <= 0 || PHP_SAPI === 'cli') {
            return;
        }

        if (function_exists('set_time_limit')) {
            @set_time_limit($this->timeoutSeconds);
        }

        @ini_set('max_execution_time', (string) $this->timeoutSeconds);
        @ini_set('max_input_time', (string) $this->timeoutSeconds);
    }

    private function extractContentLength(string $header): ?int
    {
        $trimmed = trim($header);
        if ($trimmed === '' || !ctype_digit($trimmed)) {
            return null;
        }

        return (int) $trimmed;
    }

    private function resolveBodySize(ServerRequestInterface $request): ?int
    {
        $body = $request->getBody();
        $size = $body->getSize();
        if (is_int($size)) {
            return $size;
        }

        if (!$body->isSeekable()) {
            // Do not consume non-seekable streams to preserve downstream behavior.
            return null;
        }

        $currentPosition = $body->tell();
        $body->seek(0, SEEK_END);
        $endPosition = $body->tell();
        $body->seek($currentPosition);

        return $endPosition;
    }

    private function isApiRequest(ServerRequestInterface $request): bool
    {
        $path = trim($request->getUri()->getPath(), '/');
        if ($path === '') {
            return false;
        }

        foreach ($this->apiPrefixes as $prefix) {
            $normalizedPrefix = trim($prefix, '/');
            if ($normalizedPrefix === '') {
                continue;
            }

            if ($path === $normalizedPrefix || str_starts_with($path, $normalizedPrefix . '/')) {
                return true;
            }
        }

        return false;
    }

    private function payloadTooLargeResponse(string $message): ResponseInterface
    {
        $payload = json_encode(['error' => 'payload_too_large', 'message' => $message], JSON_UNESCAPED_SLASHES);
        if (!is_string($payload)) {
            $payload = '{"error":"payload_too_large"}';
        }

        return new Response(413, ['Content-Type' => 'application/json; charset=utf-8'], $payload);
    }
}

