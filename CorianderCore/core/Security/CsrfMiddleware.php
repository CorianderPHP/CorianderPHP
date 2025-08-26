<?php

namespace CorianderCore\Core\Security;

use Nyholm\Psr7\Response;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Middleware validating CSRF tokens on POST requests.
 *
 * Workflow:
 * 1. For non-POST requests the middleware is bypassed.
 * 2. On POST, the token from the parsed body is validated via {@see Csrf::validate()}.
 * 3. When validation fails a 403 response is returned.
 */
class CsrfMiddleware implements MiddlewareInterface
{
    /**
     * {@inheritDoc}
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        if (strtoupper($request->getMethod()) !== 'POST') {
            return $handler->handle($request);
        }

        $parsedBody = $request->getParsedBody();
        $token = is_array($parsedBody) ? ($parsedBody['csrf_token'] ?? null) : null;
        if (!Csrf::validate($token)) {
            return new Response(403, [], 'Invalid CSRF token');
        }

        return $handler->handle($request);
    }
}
