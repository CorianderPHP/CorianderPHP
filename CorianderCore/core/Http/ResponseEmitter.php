<?php
declare(strict_types=1);

namespace CorianderCore\Core\Http;

use Psr\Http\Message\ResponseInterface;

final class ResponseEmitter
{
    /** Emit headers and permitted content; HEAD and bodyless statuses never read the body. */
    public static function emit(ResponseInterface $response, ?string $requestMethod = null): void
    {
        $status = $response->getStatusCode();
        http_response_code($status);
        foreach ($response->getHeaders() as $name => $values) {
            if (($status < 200 || $status === 204 || $status === 205)
                && in_array(strtolower($name), ['content-length', 'transfer-encoding'], true)) {
                continue;
            }
            foreach ($values as $value) {
                header($name . ': ' . $value, false);
            }
        }
        if ($status === 205) {
            header('Content-Length: 0');
        }

        $method = strtoupper($requestMethod ?? $_SERVER['REQUEST_METHOD'] ?? 'GET');
        if ($method !== 'HEAD' && $status >= 200 && !in_array($status, [204, 205, 304], true)) {
            echo $response->getBody();
        }
    }
}
