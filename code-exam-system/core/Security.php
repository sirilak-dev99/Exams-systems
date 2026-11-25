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

    public static function wantsJson(): bool
    {
        $accept = strtolower((string)($_SERVER['HTTP_ACCEPT'] ?? ''));
        $xhr    = strtolower((string)($_SERVER['HTTP_X_REQUESTED_WITH'] ?? ''));
        $uri    = (string)($_SERVER['REQUEST_URI'] ?? '');

        return str_contains($accept, 'application/json')
            || $xhr === 'xmlhttprequest'
            || str_contains($uri, '/api/');
    }

    private static function sendJsonError(int $statusCode, string $code, string $message): never
    {
        http_response_code($statusCode);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'data'  => null,
            'error' => [
                'code'    => $code,
                'message' => $message,
            ],
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    public static function requireLogin(string $redirectTo = '/code-exam-system-starter/code-exam-system/public/login'): void
    {
        if (!self::isAuthenticated()) {
            if (self::wantsJson()) {
                self::sendJsonError(401, 'UNAUTHORIZED', 'กรุณาเข้าสู่ระบบก่อนใช้งาน');
            }
            header('Location: ' . $redirectTo);
            exit;
        }
    }

    public static function hasRole(string $role): bool
    {
        $user = self::currentUser();
        return $user !== null && in_array($role, $user['roles'] ?? [], true);
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
            if (self::wantsJson()) {
                self::sendJsonError(403, 'FORBIDDEN', 'คุณไม่มีสิทธิ์เข้าถึงส่วนนี้');
            }
            http_response_code(403);
            echo '403 Forbidden - คุณไม่มีสิทธิ์เข้าถึงหน้านี้';
            exit;
        }
    }

    public static function hasPermission(string $permissionCode): bool
    {
        $user = self::currentUser();
        if ($user === null) {
            return false;
        }

        // admin มีสิทธิ์เข้าถึงทุกคำสั่งโดยอัตโนมัติ
        if (in_array('admin', $user['roles'] ?? [], true)) {
            return true;
        }

        return in_array($permissionCode, $user['permissions'] ?? [], true);
    }

    public static function hasAnyPermission(array $permissionCodes): bool
    {
        foreach ($permissionCodes as $code) {
            if (self::hasPermission($code)) {
                return true;
            }
        }
        return false;
    }

    public static function requirePermission(string|array $permissions): void
    {
        self::requireLogin();
        $list = is_array($permissions) ? $permissions : [$permissions];

        if (!self::hasAnyPermission($list)) {
            if (self::wantsJson()) {
                self::sendJsonError(403, 'FORBIDDEN', 'บัญชีของคุณไม่ได้รับอนุญาตให้ดำเนินการนี้');
            }
            http_response_code(403);
            echo '403 Forbidden - บัญชีของคุณไม่ได้รับอนุญาตให้ดำเนินการนี้';
            exit;
        }
    }

    public static function escape(?string $value): string
    {
        return htmlspecialchars($value ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}