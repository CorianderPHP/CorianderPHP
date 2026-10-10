<?php
declare(strict_types=1);

namespace CorianderCore\Core\Http;

use Nyholm\Psr7\ServerRequest;
use Nyholm\Psr7\Stream;
use Nyholm\Psr7\UploadedFile;
use Psr\Http\Message\StreamInterface;
use Psr\Http\Message\UploadedFileInterface;

final class RequestFactory
{
    /**
     * Build a PSR request, bounding the raw body before application parsing.
     *
     * @param array<string,mixed>|null $serverParams
     * @param array<string,mixed>|null $postParams
     * @param array<string,string>|null $headers
     * @param array<string,string>|null $cookieParams
     * @param array<string,mixed>|null $files PHP upload arrays or nested PSR uploaded files.
     * @param int|null $maxBodyBytes Positive byte limit; defaults to API_MAX_BODY_BYTES or 1 MiB.
     * @throws PayloadTooLargeException When declared or actual body size exceeds the limit.
     * @throws BadRequestException For malformed JSON or invalid uploads.
     */
    public static function fromGlobals(?array $serverParams = null, ?array $postParams = null, ?array $headers = null, string|StreamInterface|null $rawBody = null, ?array $cookieParams = null, ?array $files = null, ?int $maxBodyBytes = null): ServerRequest
    {
        $serverParams ??= $_SERVER;
        $postParams ??= $_POST;
        if ($headers === null) {
            $headers = [];
            foreach ($serverParams as $name => $value) {
                if (str_starts_with($name, 'HTTP_') || in_array($name, ['CONTENT_TYPE', 'CONTENT_LENGTH'], true)) {
                    $name = str_starts_with($name, 'HTTP_') ? substr($name, 5) : $name;
                    $headers[str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', $name))))] = (string) $value;
                }
            }
        }
        $maxBodyBytes ??= defined('API_MAX_BODY_BYTES') ? (int) API_MAX_BODY_BYTES : 1_048_576;
        if ($maxBodyBytes < 1 || $maxBodyBytes === PHP_INT_MAX) {
            throw new \InvalidArgumentException('Body limit must be between 1 and PHP_INT_MAX - 1');
        }
        foreach ($headers as $name => $value) {
            if (strcasecmp($name, 'Content-Length') === 0 && ctype_digit(trim($value)) && (float) trim($value) > $maxBodyBytes) {
                throw new PayloadTooLargeException('Request body exceeds the configured limit');
            }
        }
        if (!is_string($rawBody)) {
            $stream = $rawBody ?? Stream::create(fopen('php://input', 'rb'));
            $rawBody = '';
            // Read one extra byte to detect overflow without buffering the whole upload.
            while (!$stream->eof() && strlen($rawBody) <= $maxBodyBytes) {
                $chunk = $stream->read(min(8192, $maxBodyBytes + 1 - strlen($rawBody)));
                if ($chunk === '') {
                    if (!$stream->eof()) {
                        throw new \RuntimeException('Request body stream made no progress');
                    }
                    break;
                }
                $rawBody .= $chunk;
            }
        }
        if (strlen($rawBody) > $maxBodyBytes) {
            throw new PayloadTooLargeException('Request body exceeds the configured limit');
        }

        $protocol = (string) ($serverParams['SERVER_PROTOCOL'] ?? 'HTTP/1.1');
        $version = str_contains($protocol, '/') ? substr($protocol, strpos($protocol, '/') + 1) : '1.1';

        $request = new ServerRequest(
            (string) ($serverParams['REQUEST_METHOD'] ?? 'GET'),
            (string) ($serverParams['REQUEST_URI'] ?? '/'),
            $headers,
            $rawBody,
            $version,
            $serverParams
        );

        $request = $request->withCookieParams($cookieParams ?? $_COOKIE)
            ->withUploadedFiles(self::normalizeFiles($files ?? $_FILES));
        return self::withParsedBody($request, $serverParams, $postParams, $rawBody);
    }

    /**
     * @param array<string,mixed> $serverParams
     * @param array<string,mixed> $postParams
     */
    private static function withParsedBody(ServerRequest $request, array $serverParams, array $postParams, string $rawBody): ServerRequest
    {
        $method = strtoupper((string) ($serverParams['REQUEST_METHOD'] ?? 'GET'));
        if (!in_array($method, ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
            return $request;
        }

        $contentType = strtolower(trim(explode(';', $request->getHeaderLine('Content-Type'))[0]));
        if ($contentType === 'application/json' || str_ends_with($contentType, '+json')) {
            try {
                $decoded = json_decode($rawBody, true, 512, JSON_THROW_ON_ERROR);
            } catch (\JsonException $exception) {
                throw new BadRequestException('Malformed JSON request body', previous: $exception);
            }
            if (!is_array($decoded)) {
                throw new BadRequestException('JSON request body must be an object or array');
            }
            return $request->withParsedBody($decoded);
        }
        if ($contentType === 'application/x-www-form-urlencoded') {
            parse_str($rawBody, $form);
            return $request->withParsedBody($postParams !== [] ? $postParams : $form);
        }
        return $postParams !== [] ? $request->withParsedBody($postParams) : $request;
    }

    /**
     * @param array<string|int,mixed> $files
     * @return array<string|int,UploadedFileInterface|array>
     */
    private static function normalizeFiles(array $files): array
    {
        $normalized = [];
        foreach ($files as $key => $file) {
            if ($file instanceof UploadedFileInterface) {
                $normalized[$key] = $file;
            } elseif (is_array($file) && array_key_exists('tmp_name', $file)) {
                $normalized[$key] = self::normalizeUpload($file);
            } elseif (is_array($file)) {
                $normalized[$key] = self::normalizeFiles($file);
            } else {
                throw new BadRequestException('Invalid uploaded file structure');
            }
        }
        return $normalized;
    }

    private static function normalizeUpload(array $file): array|UploadedFileInterface
    {
        if (is_array($file['tmp_name'])) {
            $children = [];
            foreach ($file['tmp_name'] as $key => $path) {
                $child = [];
                foreach (['tmp_name', 'size', 'error', 'name', 'type'] as $field) {
                    if (!is_array($file[$field] ?? null) || !array_key_exists($key, $file[$field])) {
                        throw new BadRequestException('Invalid nested uploaded file structure');
                    }
                    $child[$field] = $file[$field][$key];
                }
                $children[$key] = self::normalizeUpload($child);
            }
            return $children;
        }
        try {
            return new UploadedFile($file['tmp_name'], $file['size'] ?? -1, $file['error'] ?? -1, $file['name'] ?? null, $file['type'] ?? null);
        } catch (\InvalidArgumentException $exception) {
            throw new BadRequestException('Invalid uploaded file', previous: $exception);
        }
    }
}
