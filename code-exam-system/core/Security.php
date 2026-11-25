<?php

declare(strict_types=1);

final class Security
{
    public const IDLE_TIMEOUT_SECONDS = 1800; // 30 นาที

    public static function startSession(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            self::enforceIdleTimeout();
            return;
        }

        ini_set('session.use_strict_mode', '1');

        $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && strtolower((string)$_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https');

        session_set_cookie_params([
            'httponly' => true,
            'secure'   => $isHttps,
            'samesite' => 'Lax',
            'path'     => '/',
        ]);

        session_start();
        self::enforceIdleTimeout();
    }

    private static function enforceIdleTimeout(): void
    {
        if (!isset($_SESSION['user']['id_users'])) {
            return;
        }

        $now = time();
        $lastActivity = (int)($_SESSION['_last_activity'] ?? $now);

        if (($now - $lastActivity) > self::IDLE_TIMEOUT_SECONDS) {
            self::destroySessionData();
            return;
        }

        $_SESSION['_last_activity'] = $now;
    }

    public static function destroySessionData(): void
    {
        $_SESSION = [];

        if (session_status() === PHP_SESSION_ACTIVE) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                [
                    'expires'  => time() - 3600,
                    'path'     => $params['path'] ?: '/',
                    'domain'   => $params['domain'] ?: '',
                    'secure'   => (bool)$params['secure'],
                    'httponly' => true,
                    'samesite' => $params['samesite'] ?: 'Lax',
                ]
            );
            session_destroy();
        }
    }

    public static function csrfToken(): string
    {
        self::startSession();
        return $_SESSION['_csrf'] ??= bin2hex(random_bytes(32));
    }

    public static function verifyCsrf(?string $token): bool
    {
        self::startSession();
        return is_string($token)
            && isset($_SESSION['_csrf'])
            && hash_equals($_SESSION['_csrf'], $token);
    }

    public static function refreshCsrfToken(): void
    {
        self::startSession();
        $_SESSION['_csrf'] = bin2hex(random_bytes(32));
    }

    public static function isAuthenticated(): bool
    {
        self::startSession();
        return isset($_SESSION['user']['id_users']);
    }

    public static function currentUser(): ?array
    {
        self::startSession();
        return $_SESSION['user'] ?? null;
    }

    public static function requireLogin(string $redirectTo = 'index.php?url=auth/login'): void
    {
        if (!self::isAuthenticated()) {
            header('Location: ' . $redirectTo);
            exit;
        }
    }

    public static function hasRole(string $role): bool
    {
        $user = self::currentUser();
        return $user !== null && in_array($role, $user['roles'] ?? [], true);
    }

    public static function escape(?string $value): string
    {
        return htmlspecialchars($value ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    public static function hasAnyRole(array $roles): bool
{
    $user = self::currentUser();
    if ($user === null) {
        return false;
    }

    $userRoles = $user['roles'] ?? [];
    foreach ($roles as $role) {
        if (in_array($role, $userRoles, true)) {
            return true;
        }
    }
    return false;
}

public static function requireRole(array $roles): void
{
    self::requireLogin();
    if (!self::hasAnyRole($roles)) {
        http_response_code(403);
        echo '403 Forbidden - คุณไม่มีสิทธิ์เข้าถึงหน้านี้';
        exit;
    }
}
}