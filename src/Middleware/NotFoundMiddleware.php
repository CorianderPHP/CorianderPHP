<?php
declare(strict_types=1);
namespace App\Middleware;

use CorianderCore\Core\Http\Responses;
use Psr\Http\Message\{ResponseInterface, ServerRequestInterface};
use Psr\Http\Server\{MiddlewareInterface, RequestHandlerInterface};

final class NotFoundMiddleware implements MiddlewareInterface
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $response = $handler->handle($request);
        if ($response->getStatusCode() !== 404) {
            return $response;
        }
        $page = Responses::view('_notfound', ['metadata' => ['title' => 'Page not found']], status: 404);
        $response = $response->withBody($page->getBody())->withHeader('Content-Type', $page->getHeaderLine('Content-Type'));
        // The replacement HTML invalidates the original representation's framing and validators.
        foreach (['Content-Length', 'Transfer-Encoding', 'Content-Encoding', 'Content-Range', 'ETag', 'Last-Modified'] as $header) {
            $response = $response->withoutHeader($header);
        }
        return $response;
    }
}
