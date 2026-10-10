<?php
declare(strict_types=1);

namespace CorianderCore\Core\Http;

use CorianderCore\Core\Router\ViewRenderer;
use Nyholm\Psr7\Response;
use Psr\Http\Message\ResponseInterface;

final class Responses
{
    public static function html(string $html, int $status = 200): ResponseInterface
    {
        return new Response($status, ['Content-Type' => 'text/html; charset=utf-8'], $html);
    }

    /** @throws \JsonException When the payload cannot be encoded. */
    public static function json(mixed $data, int $status = 200): ResponseInterface
    {
        return new Response($status, ['Content-Type' => 'application/json; charset=utf-8'],
            json_encode($data, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    }

    public static function redirect(string $location, int $status = 303): ResponseInterface
    {
        if (!in_array($status, [301, 302, 303, 307, 308], true)) {
            throw new \InvalidArgumentException('Invalid redirect status');
        }
        return new Response($status, ['Location' => $location]);
    }

    /** @param array<string,mixed> $data View variables; string values are HTML-escaped recursively. */
    public static function view(string $view, array $data = [], int $status = 200, bool $layout = true): ResponseInterface
    {
        return (new ViewRenderer())->response($view, $data, $status, $layout);
    }
}
