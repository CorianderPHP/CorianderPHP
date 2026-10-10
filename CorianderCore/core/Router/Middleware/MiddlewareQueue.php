<?php
declare(strict_types=1);

namespace CorianderCore\Core\Router\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Stateful PSR-15 pipeline; instantiate a new queue for each request.
 */
class MiddlewareQueue implements RequestHandlerInterface
{
    private int $index = 0;

    /**
     * @param list<MiddlewareInterface> $middleware Ordered middleware list.
     */
    public function __construct(
        private array $middleware,
        private RequestHandlerInterface $handler
    ) {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        if ($this->index >= count($this->middleware)) {
            return $this->handler->handle($request);
        }

        $middleware = $this->middleware[$this->index++];
        return $middleware->process($request, $this);
    }
}
