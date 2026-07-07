<?php
declare(strict_types=1);

namespace CorianderCore\Core\Http;

use Nyholm\Psr7\ServerRequest;

final class RequestFactory
{
    /**
     * @param array<string,mixed>|null $serverParams
     * @param array<string,mixed>|null $postParams
     * @param array<string,string>|null $headers
     */
    public static function fromGlobals(?array $serverParams = null, ?array $postParams = null, ?array $headers = null, ?string $rawBody = null): ServerRequest
    {
        $serverParams ??= $_SERVER;
        $postParams ??= $_POST;
        $headers ??= function_exists('getallheaders') ? getallheaders() : [];
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

        $contentType = strtolower((string) ($headers['Content-Type'] ?? $headers['content-type'] ?? ''));
        if (str_contains($contentType, 'application/json')) {
            $decoded = json_decode($rawBody !== '' ? $rawBody : '', true);
            return is_array($decoded) ? $request->withParsedBody($decoded) : $request;
        }

        return $postParams !== [] ? $request->withParsedBody($postParams) : $request;
    }
}
