<?php
declare(strict_types=1);

namespace CorianderCore\Core\Router;

use CorianderCore\Core\Support\OutputBuffer;
use CorianderCore\Core\Router\Middleware\MiddlewareQueue;
use Nyholm\Psr7\Response;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Http\Server\MiddlewareInterface;

class Router implements RequestHandlerInterface
{
    private RouteCache $cache;

    public function __construct(private string $directory = PROJECT_ROOT . '/src/Routes', ?RouteCache $cache = null)
    {
        $this->cache = $cache ?? new RouteCache($directory);
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $map = $this->cache->get();
        $root = ['directories' => [''], 'middleware' => $map['middleware']];
        $path = RouteMap::requestPath($request->getUri()->getPath());
        if ($path === null) {
            return $this->respond($request, $root, static fn() => new Response(400, [], 'Invalid request path'));
        }
        $matched = RouteMap::match($map, $path);
        if ($matched === null) {
            return $this->respond($request, $root, static fn() => new Response(404, [], '404 Not Found'));
        }
        [$route, $attributes] = $matched;
        $method = strtoupper($request->getMethod());
        $target = $route['methods'][$method] ?? ($method === 'HEAD' ? ($route['methods']['GET'] ?? null) : null);
        foreach ($attributes as $name => $value) {
            $request = $request->withAttribute($name, $value);
        }
        if ($target === null) {
            $allowed = array_keys($route['methods']);
            if (isset($route['methods']['GET'])) {
                $allowed[] = 'HEAD';
            }
            $allowed = array_unique($allowed);
            sort($allowed);
            return $this->respond($request, reset($route['methods']), static fn() => new Response(405, ['Allow' => implode(', ', $allowed)], 'Method Not Allowed'));
        }
        return $this->respond($request, $target, function ($request) use ($target) {
            $handler = require SafePath::resolveFile($this->directory, $target['file']);
            if (!is_callable($handler)) {
                throw new \UnexpectedValueException('Route file must return a callable: ' . $target['file']);
            }
            return $handler($request);
        });
    }

    /**
     * @param array{directories:list<string>,middleware:list<string>,file?:string} $target
     * @param callable(ServerRequestInterface):ResponseInterface $callback
     */
    private function respond(ServerRequestInterface $request, array $target, callable $callback): ResponseInterface
    {
        [$response, $output] = OutputBuffer::capture(function () use ($request, $target, $callback) {
            $middleware = [];
            foreach ($target['directories'] as $ancestor) {
                $relative = ltrim($ancestor . '/_middleware.php', '/');
                clearstatcache(true, $this->directory . '/' . $relative);
                // A missing cached middleware file must fail closed, not bypass authentication.
                if (!file_exists($this->directory . '/' . $relative) && !in_array($relative, $target['middleware'], true)) {
                    continue;
                }
                $items = require SafePath::resolveFile($this->directory, $relative);
                if (!is_array($items)) {
                    throw new \UnexpectedValueException('Middleware file must return an array: ' . $relative);
                }
                foreach ($items as $item) {
                    if (!$item instanceof MiddlewareInterface) {
                        throw new \UnexpectedValueException('Expected PSR-15 middleware in: ' . $relative);
                    }
                    $middleware[] = $item;
                }
            }
            $handler = new class($callback) implements RequestHandlerInterface {
                public function __construct(private $callback) {}
                public function handle(ServerRequestInterface $request): ResponseInterface
                {
                    $response = ($this->callback)($request);
                    if (!$response instanceof ResponseInterface) {
                        throw new \UnexpectedValueException('Route handlers must return a PSR response');
                    }
                    return $response;
                }
            };
            return (new MiddlewareQueue($middleware, $handler))->handle($request);
        });
        if (!$response instanceof ResponseInterface || $output !== '') {
            throw new \UnexpectedValueException('Handlers and middleware must return a PSR response without printing output');
        }
        return $response;
    }
}
