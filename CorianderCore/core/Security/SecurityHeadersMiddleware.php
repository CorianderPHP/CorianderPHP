<?php
declare(strict_types=1);

namespace CorianderCore\Core\Security;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Adds a secure baseline of HTTP response headers.
 */
class SecurityHeadersMiddleware implements MiddlewareInterface
{
    /**
     * @var array<string,string>
     */
    private array $headers;

    private bool $enabled;

    /**
     * @param array<string,string>|null $headers
     */
    public function __construct(?array $headers = null, ?bool $enabled = null)
    {
        $this->headers = $headers ?? [
            'Content-Security-Policy' => "default-src 'self'; base-uri 'self'; frame-ancestors 'none'; object-src 'none'",
            'X-Content-Type-Options' => 'nosniff',
            'X-Frame-Options' => 'DENY',
            'Referrer-Policy' => 'strict-origin-when-cross-origin',
            'Permissions-Policy' => 'geolocation=(), microphone=(), camera=()',
            'Cross-Origin-Opener-Policy' => 'same-origin',
            'Cross-Origin-Resource-Policy' => 'same-origin',
        ];

        $this->enabled = $enabled ?? $this->resolveEnabledFlag();
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $response = $handler->handle($request);
        if (!$this->enabled) {
            return $response;
        }

        foreach ($this->headers as $header => $value) {
            if ($response->hasHeader($header)) {
                continue;
            }

            $response = $response->withHeader($header, $value);
        }

        if ($this->isSecureRequest($request) && !$response->hasHeader('Strict-Transport-Security')) {
            $response = $response->withHeader('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        return $response;
    }

    private function resolveEnabledFlag(): bool
    {
        $flag = getenv('SECURITY_HEADERS_ENABLED');
        if ($flag === false) {
            return true;
        }

        return !in_array(strtolower(trim((string) $flag)), ['0', 'false', 'no', 'off'], true);
    }

    private function isSecureRequest(ServerRequestInterface $request): bool
    {
        $scheme = strtolower($request->getUri()->getScheme());
        if ($scheme === 'https') {
            return true;
        }

        $forwardedProto = strtolower($request->getHeaderLine('X-Forwarded-Proto'));
        return $forwardedProto === 'https';
    }
}
