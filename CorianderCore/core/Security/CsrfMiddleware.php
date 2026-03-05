<?php
declare(strict_types=1);

namespace CorianderCore\Core\Security;

use Nyholm\Psr7\Response;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Middleware validating CSRF tokens on mutating requests.
 *
 * Workflow:
 * 1. For methods outside the protected set the middleware is bypassed.
 * 2. On protected methods, the token from parsed body (or recoverable raw body)
 *    is validated via {@see Csrf::validate()}.
 * 3. When validation fails a 403 response is returned.
 */
class CsrfMiddleware implements MiddlewareInterface
{
    /**
     * @var array<int,string>
     */
    private array $protectedMethods;

    /**
     * @param array<int,string>|null $protectedMethods HTTP methods requiring CSRF validation.
     */
    public function __construct(?array $protectedMethods = null)
    {
        $methods = $protectedMethods ?? ['POST', 'PUT', 'PATCH', 'DELETE'];

        $this->protectedMethods = [];
        foreach ($methods as $method) {
            if (!is_string($method)) {
                continue;
            }

            $normalized = strtoupper(trim($method));
            if ($normalized !== '') {
                $this->protectedMethods[] = $normalized;
            }
        }

        $this->protectedMethods = array_values(array_unique($this->protectedMethods));
    }

    /**
     * {@inheritDoc}
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        if (!$this->requiresValidation($request->getMethod())) {
            return $handler->handle($request);
        }

        $token = $this->extractToken($request);
        if (!Csrf::validate($token)) {
            return new Response(403, [], 'Invalid CSRF token');
        }

        return $handler->handle($request);
    }

    private function extractToken(ServerRequestInterface $request): ?string
    {
        $parsedBody = $request->getParsedBody();
        if (is_array($parsedBody)) {
            $token = $parsedBody['csrf_token'] ?? null;
            return is_string($token) ? $token : null;
        }

        $contentType = strtolower($request->getHeaderLine('Content-Type'));
        $rawBody = (string) $request->getBody();

        if (str_contains($contentType, 'application/x-www-form-urlencoded')) {
            $formBody = [];
            parse_str($rawBody, $formBody);
            $token = $formBody['csrf_token'] ?? null;
            return is_string($token) ? $token : null;
        }

        if (str_contains($contentType, 'application/json')) {
            $json = json_decode($rawBody, true);
            if (is_array($json)) {
                $token = $json['csrf_token'] ?? null;
                return is_string($token) ? $token : null;
            }
        }

        if (isset($_POST['csrf_token']) && is_string($_POST['csrf_token'])) {
            return $_POST['csrf_token'];
        }

        return null;
    }

    private function requiresValidation(string $method): bool
    {
        return in_array(strtoupper($method), $this->protectedMethods, true);
    }
}
