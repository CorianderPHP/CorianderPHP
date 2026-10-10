<?php
declare(strict_types=1);
namespace CorianderCore\Tests;

use CorianderCore\Core\Http\{BadRequestException, ErrorResponse, PayloadTooLargeException};
use CorianderCore\Tests\Support\HttpServer;
use PHPUnit\Framework\TestCase;

class ErrorResponseTest extends TestCase
{
    public function testDebugDetailsRequireExplicitLocalModeAndAreEscaped(): void
    {
        $previous = ['APP_ENV' => getenv('APP_ENV'), 'APP_DEBUG' => getenv('APP_DEBUG'), 'LOG_CHANNEL' => getenv('LOG_CHANNEL')];
        putenv('LOG_CHANNEL=' . (PHP_OS_FAMILY === 'Windows' ? 'NUL' : '/dev/null'));
        try {
            foreach ([['local', '1', true], ['development', 'true', true], ['local', '0', false],
                ['production', '1', false], ['staging', '1', false], ['', '1', false], ['local', 'invalid', false]] as [$env, $debug, $visible]) {
                putenv('APP_ENV=' . $env);
                putenv('APP_DEBUG=' . $debug);
                try { (static function ($secret) { throw new \RuntimeException('<script>failure</script>'); })('secret-argument'); }
                catch (\Throwable $error) { $response = ErrorResponse::fromException($error); }
                self::assertSame(500, $response->getStatusCode());
                $body = (string) $response->getBody();
                self::assertStringNotContainsString('secret-argument', $body);
                self::assertStringNotContainsString('<script>', $body);
                if ($visible) {
                    self::assertStringContainsString('&lt;script&gt;failure&lt;/script&gt;', $body);
                    self::assertStringContainsString('ErrorResponseTest.php', $body);
                    self::assertStringContainsString('#0', $body);
                } else {
                    self::assertSame('Internal Server Error', $body);
                }
            }
            self::assertSame(400, ErrorResponse::fromException(new BadRequestException('hidden'))->getStatusCode());
            self::assertSame('Bad Request', (string) ErrorResponse::fromException(new BadRequestException('hidden'))->getBody());
            self::assertSame(413, ErrorResponse::fromException(new PayloadTooLargeException('hidden'))->getStatusCode());
        } finally {
            foreach ($previous as $name => $value) { putenv($value === false ? $name : $name . '=' . $value); }
        }
    }

    public function testDebugPolicyOverActualHttpAndHead(): void
    {
        $root = __DIR__ . '/fixtures/http';
        foreach (['local' => true, 'production' => false] as $environment => $visible) {
            $server = new HttpServer($root, $root . '/router.php', ['APP_ENV' => $environment, 'APP_DEBUG' => '1']);
            try {
                $response = $server->request('GET', '/failure');
                self::assertSame(500, $response['status']);
                if ($visible) { self::assertStringContainsString('&lt;script&gt;fixture failure&lt;/script&gt;', $response['body']); }
                else { self::assertSame('Internal Server Error', $response['body']); }
                self::assertSame('', $server->request('HEAD', '/failure')['body']);
            } finally { $server->stop(); }
        }
    }
}
