<?php
declare(strict_types=1);

namespace CorianderCore\Core\Http;

use CorianderCore\Core\Logging\Logger;
use Psr\Http\Message\ResponseInterface;

/** Converts bootstrap failures into responses; debug details are local-only. */
final class ErrorResponse
{
    public static function fromException(\Throwable $exception): ResponseInterface
    {
        if ($exception instanceof PayloadTooLargeException) {
            return Responses::html('Payload Too Large', 413);
        }
        if ($exception instanceof BadRequestException) {
            return Responses::html('Bad Request', 400);
        }
        // Logging failure must not prevent the HTTP error response.
        try {
            (new Logger())->error('Unhandled application exception: {exception}', ['exception' => $exception]);
        } catch (\Throwable) {}

        $environment = strtolower(trim((string) getenv('APP_ENV')));
        if (!in_array($environment, ['local', 'development'], true)
            || filter_var(getenv('APP_DEBUG'), FILTER_VALIDATE_BOOLEAN) !== true) {
            return Responses::html('Internal Server Error', 500);
        }
        $details = $exception::class . ': ' . $exception->getMessage() . "\n"
            . $exception->getFile() . ':' . $exception->getLine();
        // Trace argument values may contain application secrets.
        foreach ($exception->getTrace() as $index => $frame) {
            $details .= "\n#" . $index . ' ' . ($frame['file'] ?? '[internal]') . ':' . ($frame['line'] ?? 0)
                . ' ' . ($frame['class'] ?? '') . ($frame['type'] ?? '') . ($frame['function'] ?? '') . '()';
        }
        $html = '<!doctype html><html lang="en"><meta charset="utf-8"><title>Application error</title>'
            . '<h1>Application error</h1><pre>' . htmlspecialchars($details, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</pre></html>';
        return Responses::html($html, 500)->withHeader('X-Content-Type-Options', 'nosniff');
    }
}
