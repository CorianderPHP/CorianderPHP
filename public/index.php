<?php

use CorianderCore\Core\Bootstrap\TimezoneBootstrap;
use CorianderCore\Core\Container\Container;
use CorianderCore\Core\Database\DatabaseHandler;
use CorianderCore\Core\Logging\Logger;
use CorianderCore\Core\Router\Router;
use CorianderCore\Core\Security\ApiRequestLimitsMiddleware;
use CorianderCore\Core\Security\CsrfMiddleware;
use CorianderCore\Core\Security\SecurityHeadersMiddleware;
use Nyholm\Psr7\Response;
use Nyholm\Psr7\ServerRequest;

/**
 * Detect whether the original client connection should be treated as HTTPS.
 *
 * Supports direct HTTPS and common reverse proxy headers.
 */
function corianderIpInCidr(string $ip, string $cidr): bool
{
    if (!str_contains($cidr, '/')) {
        return $ip === $cidr;
    }

    [$subnet, $prefixLength] = explode('/', $cidr, 2);
    if ($subnet === '' || !ctype_digit($prefixLength)) {
        return false;
    }

    $ipBinary = @inet_pton($ip);
    $subnetBinary = @inet_pton($subnet);
    if ($ipBinary === false || $subnetBinary === false || strlen($ipBinary) !== strlen($subnetBinary)) {
        return false;
    }

    $bits = (int) $prefixLength;
    $maxBits = strlen($ipBinary) * 8;
    if ($bits < 0 || $bits > $maxBits) {
        return false;
    }

    $bytes = intdiv($bits, 8);
    $remainder = $bits % 8;

    if ($bytes > 0 && substr($ipBinary, 0, $bytes) !== substr($subnetBinary, 0, $bytes)) {
        return false;
    }

    if ($remainder === 0) {
        return true;
    }

    $mask = (0xFF << (8 - $remainder)) & 0xFF;
    return (ord($ipBinary[$bytes]) & $mask) === (ord($subnetBinary[$bytes]) & $mask);
}

function corianderIsTrustedProxy(string $remoteAddr): bool
{
    if ($remoteAddr === '') {
        return false;
    }

    $trusted = defined('TRUSTED_PROXIES') ? (string) TRUSTED_PROXIES : '127.0.0.1,::1';
    $entries = array_map(static fn(string $item): string => trim($item), explode(',', $trusted));

    foreach ($entries as $entry) {
        if ($entry === '') {
            continue;
        }

        if ($entry === '*') {
            return true;
        }

        if (corianderIpInCidr($remoteAddr, $entry)) {
            return true;
        }
    }

    return false;
}

function corianderIsSecureRequest(array $serverParams): bool
{
    $https = strtolower((string) ($serverParams['HTTPS'] ?? ''));
    if ($https !== '' && $https !== 'off' && $https !== '0') {
        return true;
    }

    $remoteAddr = (string) ($serverParams['REMOTE_ADDR'] ?? '');
    if (corianderIsTrustedProxy($remoteAddr)) {
        $forwardedProto = strtolower((string) ($serverParams['HTTP_X_FORWARDED_PROTO'] ?? ''));
        if ($forwardedProto !== '') {
            $firstHop = trim(explode(',', $forwardedProto, 2)[0]);
            if ($firstHop === 'https') {
                return true;
            }
        }

        $forwarded = strtolower((string) ($serverParams['HTTP_FORWARDED'] ?? ''));
        if ($forwarded !== '' && str_contains($forwarded, 'proto=https')) {
            return true;
        }

        $forwardedSsl = strtolower((string) ($serverParams['HTTP_X_FORWARDED_SSL'] ?? ''));
        if ($forwardedSsl === 'on') {
            return true;
        }

        $frontEndHttps = strtolower((string) ($serverParams['HTTP_FRONT_END_HTTPS'] ?? ''));
        if ($frontEndHttps === 'on') {
            return true;
        }
    }

    return (string) ($serverParams['SERVER_PORT'] ?? '') === '443';
}

require_once '../config/config.php';

$secure = corianderIsSecureRequest($_SERVER);
session_set_cookie_params([
    'path' => '/',
    'secure' => $secure,
    'httponly' => true,
    'samesite' => 'Lax',
]);
session_start();

if (file_exists(PROJECT_ROOT . '/CorianderCore/autoload.php')) {
    require_once PROJECT_ROOT . '/CorianderCore/autoload.php';
}

if (file_exists(PROJECT_ROOT . '/vendor/autoload.php')) {
    require_once PROJECT_ROOT . '/vendor/autoload.php';
}

$appTimezone = getenv('APP_TIMEZONE');
TimezoneBootstrap::applyFromEnvironment(is_string($appTimezone) ? $appTimezone : null);

try {
    $container = new Container();
    $container->set(Logger::class, fn() => new Logger());
    $container->set(DatabaseHandler::class, fn(Container $c) => new DatabaseHandler($c->get(Logger::class)));
    $container->set(Router::class, fn() => new Router());

    $router = $container->get(Router::class);
    $router->addMiddleware(new SecurityHeadersMiddleware());
    $router->addMiddleware(new ApiRequestLimitsMiddleware());
    $router->addMiddleware(new CsrfMiddleware());

    $notFound = function () {
        $notFoundView = 'notfound';

        $metaDataFile = PROJECT_ROOT . '/public/public_views/' . $notFoundView . '/metadata.php';

        if (file_exists($metaDataFile)) {
            include $metaDataFile;
        }

        ob_start();
        require_once PROJECT_ROOT . '/public/public_views/header.php';
        require_once PROJECT_ROOT . '/public/public_views/' . $notFoundView . '/index.php';
        require_once PROJECT_ROOT . '/public/public_views/footer.php';

        return new Response(404, [], (string) ob_get_clean());
    };
    $router->setNotFound($notFound);

    $routesFile = __DIR__ . '/routes.php';
    if (file_exists($routesFile)) {
        require $routesFile;
    }

    $serverParams = $_SERVER;
    $protocol = $serverParams['SERVER_PROTOCOL'] ?? 'HTTP/1.1';
    $version = str_contains($protocol, '/') ? substr($protocol, strpos($protocol, '/') + 1) : '1.1';
    $headers = function_exists('getallheaders') ? getallheaders() : [];
    $rawBody = file_get_contents('php://input');

    $request = new ServerRequest(
        $serverParams['REQUEST_METHOD'] ?? 'GET',
        $serverParams['REQUEST_URI'] ?? '/',
        $headers,
        $rawBody,
        $version,
        $serverParams
    );

    $contentType = strtolower((string) ($headers['Content-Type'] ?? $headers['content-type'] ?? ''));
    $method = strtoupper($serverParams['REQUEST_METHOD'] ?? 'GET');
    if (in_array($method, ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
        if (str_contains($contentType, 'application/json')) {
            $decoded = json_decode($rawBody ?: '', true);
            if (is_array($decoded)) {
                $request = $request->withParsedBody($decoded);
            }
        } elseif (!empty($_POST)) {
            $request = $request->withParsedBody($_POST);
        }
    }

    $response = $router->dispatch($request);

    http_response_code($response->getStatusCode());
    foreach ($response->getHeaders() as $name => $values) {
        foreach ($values as $value) {
            header($name . ': ' . $value, false);
        }
    }
    echo $response->getBody();
} catch (Throwable $exception) {
    try {
        (new Logger())->error('Unhandled application exception.', ['exception' => $exception]);
    } catch (Throwable) {
        // Avoid cascading failures while handling an already-failed request.
    }

    if (!headers_sent()) {
        http_response_code(500);
        header('Content-Type: text/plain; charset=utf-8');
    }

    echo 'Internal Server Error';
}


