<?php
declare(strict_types=1);

$root = $argv[1] ?? throw new InvalidArgumentException('Provide an empty fixture directory');
$project = dirname(__DIR__, 3);
foreach (['config', 'public', 'src/Routes/users', 'src/Routes/session', 'src/Views', 'cache'] as $directory) {
    mkdir($root . '/' . $directory, 0775, true);
}
symlink($project . '/CorianderCore', $root . '/CorianderCore');
symlink($project . '/vendor', $root . '/vendor');
copy($project . '/public/index.php', $root . '/public/index.php');
file_put_contents($root . '/config/config.php', '<?php define("PROJECT_ROOT", dirname(__DIR__)); require ' . var_export($project . '/config/config.php', true) . ';');
$routes = [
    'index.get.php' => 'return static fn($r) => \\CorianderCore\\Core\\Http\\Responses::html("static");',
    'users/[id].get.php' => 'return static fn($r) => \\CorianderCore\\Core\\Http\\Responses::html("user:" . $r->getAttribute("id"));',
    'layout.get.php' => 'return static fn($r) => \\CorianderCore\\Core\\Http\\Responses::view("page");',
    'failure.get.php' => 'return static function($r) { throw new \\RuntimeException("benchmark failure"); };',
    'public-delay.get.php' => 'return static function($r) { usleep(20_000); return \\CorianderCore\\Core\\Http\\Responses::html("public"); };',
    'session/start.get.php' => 'return static function($r) { \\CorianderCore\\Core\\Bootstrap\\SessionBootstrap::start(); $_SESSION["requests"] = 0; return \\CorianderCore\\Core\\Http\\Responses::html("session"); };',
    'session/hold.get.php' => 'return static function($r) { \\CorianderCore\\Core\\Bootstrap\\SessionBootstrap::start(); $count = ++$_SESSION["requests"]; usleep(20_000); return \\CorianderCore\\Core\\Http\\Responses::json(["count" => $count]); };',
];
foreach ($routes as $name => $code) { file_put_contents($root . '/src/Routes/' . $name, '<?php ' . $code); }
file_put_contents($root . '/src/Views/_header.php', '<header>header</header>');
file_put_contents($root . '/src/Views/page.php', '<main>body</main>');
file_put_contents($root . '/src/Views/_footer.php', '<footer>footer</footer>');
file_put_contents($root . '/src/Routes/_middleware.php', <<<'PHP'
<?php
return [
    new class implements \Psr\Http\Server\MiddlewareInterface {
        public function process(\Psr\Http\Message\ServerRequestInterface $request, \Psr\Http\Server\RequestHandlerInterface $handler): \Psr\Http\Message\ResponseInterface {
            $response = $handler->handle($request);
            return $response->withHeader('X-PHP-Version', PHP_VERSION)->withHeader('X-PHP-SAPI', PHP_SAPI)
                ->withHeader('X-OPcache', (string) (int) (opcache_get_status(false)['opcache_enabled'] ?? false))
                ->withHeader('X-PHP-Peak-Bytes', (string) memory_get_peak_usage(true))
                ->withHeader('X-Session-Active', (string) (int) (session_status() === PHP_SESSION_ACTIVE));
        }
    },
    new \CorianderCore\Core\Security\SecurityHeadersMiddleware(),
    new \CorianderCore\Core\Security\ApiRequestLimitsMiddleware(API_MAX_BODY_BYTES, API_TIMEOUT_SECONDS, apiPrefixes: []),
    new \CorianderCore\Core\Security\CsrfMiddleware(),
];
PHP);
