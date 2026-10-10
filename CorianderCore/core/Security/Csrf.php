<?php
declare(strict_types=1);

namespace CorianderCore\Core\Security;

use CorianderCore\Core\Bootstrap\SessionBootstrap;

/**
 * Session-backed CSRF tokens; generation and validation open a session on demand.
 */
class Csrf
{
    private const SESSION_KEY = '_csrf_token';

    /** Generate or retrieve this session's token. */
    public static function token(): string
    {
        SessionBootstrap::start();

        if (empty($_SESSION[self::SESSION_KEY])) {
            $_SESSION[self::SESSION_KEY] = bin2hex(random_bytes(32));
        }

        return $_SESSION[self::SESSION_KEY];
    }

    /** Render an escaped hidden input for a mutating form. */
    public static function input(): string
    {
        $token = htmlspecialchars(self::token(), ENT_QUOTES, 'UTF-8');
        return '<input type="hidden" name="csrf_token" value="' . $token . '">';
    }

    /** Reject missing tokens; compare the submitted token with this session's value. */
    public static function validate(?string $token): bool
    {
        SessionBootstrap::start();

        $sessionToken = $_SESSION[self::SESSION_KEY] ?? '';
        return $sessionToken !== '' && $token !== null && hash_equals($sessionToken, $token);
    }

    /** Validate the native POST form token; PSR middleware uses parsed request data. */
    public static function validateRequest(): bool
    {
        $token = $_POST['csrf_token'] ?? null;
        return self::validate(is_string($token) ? $token : null);
    }

}
