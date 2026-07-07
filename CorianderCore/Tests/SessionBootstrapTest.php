<?php
declare(strict_types=1);

namespace CorianderCore\Tests;

use CorianderCore\Core\Bootstrap\SessionBootstrap;
use CorianderCore\Core\Security\Csrf;
use PHPUnit\Framework\TestCase;

class SessionBootstrapTest extends TestCase
{
    #[\PHPUnit\Framework\Attributes\RunInSeparateProcess]
    public function testSkipsSessionStartForApiRequests(): void
    {
        SessionBootstrap::configure(false);
        SessionBootstrap::startForRequest(['REQUEST_URI' => '/api/items']);

        $this->assertSame(PHP_SESSION_NONE, session_status());
    }

    #[\PHPUnit\Framework\Attributes\RunInSeparateProcess]
    public function testStartsSessionForWebRequests(): void
    {
        SessionBootstrap::configure(false);
        SessionBootstrap::startForRequest(['REQUEST_URI' => '/home']);

        $this->assertSame(PHP_SESSION_ACTIVE, session_status());
    }

    #[\PHPUnit\Framework\Attributes\RunInSeparateProcess]
    public function testCsrfStartsSessionWhenTokenIsRequested(): void
    {
        SessionBootstrap::configure(false);

        $token = Csrf::token();

        $this->assertNotSame('', $token);
        $this->assertSame(PHP_SESSION_ACTIVE, session_status());
    }
}
