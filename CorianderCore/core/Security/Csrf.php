<?php
declare(strict_types=1);

namespace CorianderCore\Core\Security;

/**
 * Handles generation, storage, and validation of CSRF tokens.
 *
 * Workflow:
 * - Tokens are generated lazily and stored in the active session.
 * - Forms include the token via {@see Csrf::input()} to embed a hidden field.
 * - Incoming POST requests are validated against the stored token.
 */
class Csrf
{
    private const SESSION_KEY = '_csrf_token';

    /**
     * Generate or retrieve the current CSRF token.
     *
     * @return string The CSRF token stored in session.
     */
    public static function token(): string
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }

        if (empty($_SESSION[self::SESSION_KEY])) {
            $_SESSION[self::SESSION_KEY] = bin2hex(random_bytes(32));
        }

        return $_SESSION[self::SESSION_KEY];
    }

    /**
     * Render a hidden input element containing the CSRF token.
     *
     * @return string HTML markup for the hidden token field.
     */
    public static function input(): string
    {
        $token = htmlspecialchars(self::token(), ENT_QUOTES, 'UTF-8');
        return '<input type="hidden" name="csrf_token" value="' . $token . '">';
    }

    /**
     * Validate the supplied token against the session value.
     *
     * @param string|null $token Token supplied by the client.
     * @return bool True when the token matches the session value.
     */
    public static function validate(?string $token): bool
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }

        $sessionToken = $_SESSION[self::SESSION_KEY] ?? '';
        return $sessionToken !== '' && $token !== null && hash_equals($sessionToken, $token);
    }

    /**
     * Validate the CSRF token from the current POST request.
     *
     * @return bool True when the request token is valid.
     */
    public static function validateRequest(): bool
    {
        $token = $_POST['csrf_token'] ?? null;
        return self::validate($token);
    }

}
