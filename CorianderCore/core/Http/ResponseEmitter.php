<?php
declare(strict_types=1);

namespace CorianderCore\Core\Http;

use Psr\Http\Message\ResponseInterface;

final class ResponseEmitter
{
    public static function emit(ResponseInterface $response, ?string $requestMethod = null): void
    {
        http_response_code($response->getStatusCode());
        foreach ($response->getHeaders() as $name => $values) {
            foreach ($values as $value) {
                header($name . ': ' . $value, false);
            }
        }

        $method = strtoupper($requestMethod ?? $_SERVER['REQUEST_METHOD'] ?? 'GET');
        if ($method !== 'HEAD') {
            echo $response->getBody();
        }
    }
}
