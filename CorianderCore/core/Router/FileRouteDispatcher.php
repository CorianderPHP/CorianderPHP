<?php
declare(strict_types=1);

namespace CorianderCore\Core\Router;

use CorianderCore\Core\Support\OutputBuffer;
use Nyholm\Psr7\Response;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

class FileRouteDispatcher implements RequestHandlerInterface
{
    public function __construct(private string $directory = PROJECT_ROOT . '/src/Routes') {}

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $path = RouteMap::requestPath($request->getUri()->getPath());
        if ($path === null) {
            return new Response(400, [], 'Invalid request path');
        }
        $matched = RouteMap::match((new RouteMap())->discover($this->directory), $path);
        if ($matched === null) {
            return new Response(404, [], '404 Not Found');
        }
        [$route, $attributes] = $matched;
        $method = strtoupper($request->getMethod());
        $target = $route['methods'][$method] ?? ($method === 'HEAD' ? ($route['methods']['GET'] ?? null) : null);
        if ($target === null) {
            $allowed = array_keys($route['methods']);
            if (isset($route['methods']['GET'])) {
                $allowed[] = 'HEAD';
            }
            $allowed = array_unique($allowed);
            sort($allowed);
            return new Response(405, ['Allow' => implode(', ', $allowed)], 'Method Not Allowed');
        }
        foreach ($attributes as $name => $value) {
            $request = $request->withAttribute($name, $value);
        }
        [$response, $output] = OutputBuffer::capture(function () use ($target, $request) {
            $handler = require SafePath::resolveFile($this->directory, $target['file']);
            if (!is_callable($handler)) {
                throw new \UnexpectedValueException('Route file must return a callable: ' . $target['file']);
            }
            return $handler($request);
        });
        if (!$response instanceof ResponseInterface || $output !== '') {
            throw new \UnexpectedValueException('Route handlers must return a PSR response without printing output: ' . $target['file']);
        }
        return $response;
    }
}
