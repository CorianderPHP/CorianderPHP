<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';
require_once PROJECT_ROOT . '/CorianderCore/autoload.php';

use CorianderCore\Core\Bootstrap\{SessionBootstrap, TimezoneBootstrap};
use CorianderCore\Core\Http\{BadRequestException, PayloadTooLargeException, RequestFactory, ResponseEmitter, Responses, TrustedProxy};
use CorianderCore\Core\Logging\Logger;
use CorianderCore\Core\Router\Router;

// PHP's development server passes existing asset requests to its router script.
if (PHP_SAPI === 'cli-server') {
    $asset = \CorianderCore\Core\Router\RouteMap::requestPath(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?? '/');
    if ($asset !== null && str_starts_with($asset, 'assets/') && !str_contains('/' . $asset, '/.')
        && preg_match('/\.(css|js|png|jpg|jpeg|gif|svg|webp|ico|woff2?|ttf)$/i', $asset)) {
        try {
            \CorianderCore\Core\Router\SafePath::resolveFile(__DIR__, $asset);
            return false;
        } catch (RuntimeException) {}
    }
}

try {
    SessionBootstrap::configure(TrustedProxy::isSecureRequest($_SERVER));
    SessionBootstrap::startForRequest($_SERVER, statelessPrefixes: []);
    $timezone = getenv('APP_TIMEZONE');
    TimezoneBootstrap::applyFromEnvironment(is_string($timezone) ? $timezone : null);
    $request = RequestFactory::fromGlobals();
    $response = (new Router())->handle($request);
} catch (PayloadTooLargeException $exception) {
    $response = Responses::html('Payload Too Large', 413);
} catch (BadRequestException $exception) {
    $response = Responses::html('Bad Request', 400);
} catch (Throwable $exception) {
    try {
        (new Logger())->error('Unhandled application exception.', ['exception' => $exception]);
    } catch (Throwable) {}
    $response = Responses::html('Internal Server Error', 500);
}
ResponseEmitter::emit($response, $_SERVER['REQUEST_METHOD'] ?? 'GET');
