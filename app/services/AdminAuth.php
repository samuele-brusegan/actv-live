<?php
/**
 * Minimal session-based authentication gate for the /admin/* area.
 * The admin password is read from the ADMIN_PASSWORD entry in .env.
 */
class AdminAuth
{
    private static function ensureSession(): void
    {
        if (php_sapi_name() !== 'cli' && session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }

    public static function check(): bool
    {
        self::ensureSession();
        return !empty($_SESSION['is_admin']);
    }

    /**
     * Verify the submitted password against ADMIN_PASSWORD and open a session.
     */
    public static function attempt(string $password): bool
    {
        $expected = ENV['ADMIN_PASSWORD'] ?? '';
        if ($expected === '' || !hash_equals($expected, $password)) {
            return false;
        }

        self::ensureSession();
        session_regenerate_id(true);
        $_SESSION['is_admin'] = true;
        return true;
    }

    public static function requireAuth(): void
    {
        if (!self::check()) {
            header('Location: ' . URL_PATH . '/admin/login');
            exit;
        }
    }

    public static function logout(): void
    {
        self::ensureSession();
        unset($_SESSION['is_admin']);
    }

    /**
     * CSRF token for the login form (generated lazily, stored in session).
     */
    public static function csrfToken(): string
    {
        self::ensureSession();
        if (empty($_SESSION['admin_csrf'])) {
            $_SESSION['admin_csrf'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['admin_csrf'];
    }

    public static function verifyCsrf(?string $token): bool
    {
        self::ensureSession();
        return !empty($_SESSION['admin_csrf'])
            && is_string($token)
            && hash_equals($_SESSION['admin_csrf'], $token);
    }
}
