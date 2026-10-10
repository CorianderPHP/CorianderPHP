<?php
declare(strict_types=1);

namespace CorianderCore\Core\Http;

use Nyholm\Psr7\ServerRequest;
use Nyholm\Psr7\UploadedFile;
use Psr\Http\Message\UploadedFileInterface;

final class RequestFactory
{
    /**
     * @param array<string,mixed>|null $serverParams
     * @param array<string,mixed>|null $postParams
     * @param array<string,string>|null $headers
     */
    public static function fromGlobals(?array $serverParams = null, ?array $postParams = null, ?array $headers = null, ?string $rawBody = null, ?array $cookieParams = null, ?array $files = null): ServerRequest
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
        $rawBody ??= (string) file_get_contents('php://input');

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
        return self::withParsedBody($request, $serverParams, $headers, $postParams, $rawBody);
    }

    /**
     * @param array<string,mixed> $serverParams
     * @param array<string,string> $headers
     * @param array<string,mixed> $postParams
     */
    private static function withParsedBody(ServerRequest $request, array $serverParams, array $headers, array $postParams, string $rawBody): ServerRequest
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
