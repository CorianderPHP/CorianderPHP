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
        return $response->getStatusCode() === 404
            ? Responses::view('notfound', ['title' => 'Page not found'], status: 404)
            : $response;
    }
}
