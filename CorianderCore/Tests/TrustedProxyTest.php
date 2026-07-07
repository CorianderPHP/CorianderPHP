<?php
declare(strict_types=1);

namespace CorianderCore\Tests;

use CorianderCore\Core\Http\TrustedProxy;
use PHPUnit\Framework\TestCase;

class TrustedProxyTest extends TestCase
{
    public function testDetectsDirectHttpsRequest(): void
    {
        $this->assertTrue(TrustedProxy::isSecureRequest(['HTTPS' => 'on']));
    }

    public function testTrustsForwardedProtoOnlyFromTrustedProxy(): void
    {
        $server = [
            'REMOTE_ADDR' => '10.0.0.5',
            'HTTP_X_FORWARDED_PROTO' => 'https, http',
        ];

        $this->assertTrue(TrustedProxy::isSecureRequest($server, '10.0.0.0/8'));
        $this->assertFalse(TrustedProxy::isSecureRequest($server, '192.168.0.0/16'));
    }

    public function testDetectsHttpsPort(): void
    {
        $this->assertTrue(TrustedProxy::isSecureRequest(['SERVER_PORT' => '443']));
    }
}
