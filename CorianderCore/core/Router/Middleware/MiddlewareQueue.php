<?php

namespace CorianderCore\Core\Router\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Executes a queue of PSR-15 middleware.
 *
 * Workflow:
 * 1. Middleware are processed in FIFO order.
 * 2. Each middleware decides whether to continue by invoking the next handler.
 * 3. Once the queue is exhausted, the final handler produces the response.
 */
class MiddlewareQueue implements RequestHandlerInterface
{
    /** @var int Current middleware index */
    private int $index = 0;

    /**
     * @param MiddlewareInterface[] $middleware Ordered middleware list.
     */
    public function __construct(
        private array $middleware,
        private RequestHandlerInterface $handler
    ) {
    }

    /**
     * Handle the request by processing middleware sequentially.
     *
     * @param ServerRequestInterface $request Incoming request.
     * @return ResponseInterface Generated response.
     */
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        if ($this->index >= count($this->middleware)) {
            return $this->handler->handle($request);
        }

        $middleware = $this->middleware[$this->index++];
        return $middleware->process($request, $this);
    }
}
