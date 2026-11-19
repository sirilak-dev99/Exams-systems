<?php

declare(strict_types=1);

final class AuthController extends Controller
{
    public function showLogin(): void
    {
        if (Security::isAuthenticated()) {
            $this->redirect('/');
        }

        $this->view('auth/login', ['pageTitle' => 'เข้าสู่ระบบ']);
    }

    public function login(): void
    {
        if (!Security::verifyCsrf($_POST['_csrf'] ?? null)) {
            http_response_code(419);
            $this->view('auth/login', [
                'pageTitle' => 'เข้าสู่ระบบ',
                'error' => 'คำขอหมดอายุหรือไม่ถูกต้อง กรุณาลองใหม่อีกครั้ง',
            ]);
            return;
        }

        $identifier = trim((string) ($_POST['identifier'] ?? ''));
        $password = (string) ($_POST['password'] ?? '');

        if ($identifier === '' || $password === '') {
            http_response_code(422);
            $this->view('auth/login', [
                'pageTitle' => 'เข้าสู่ระบบ',
                'error' => 'กรุณากรอกอีเมลหรือรหัสนิสิต และรหัสผ่าน',
                'identifier' => $identifier,
            ]);
            return;
        }

        $users = new UserModel(Database::connection());
        $user = $users->findActiveByIdentifier($identifier);

        // ใช้ข้อความเดียวกันเพื่อไม่เปิดเผยว่าบัญชีมีอยู่หรือไม่
        if (!$user || empty($user['password_hash']) || !password_verify($password, $user['password_hash'])) {
            http_response_code(401);
            $this->view('auth/login', [
                'pageTitle' => 'เข้าสู่ระบบ',
                'error' => 'อีเมล/รหัสนิสิต หรือรหัสผ่านไม่ถูกต้อง',
                'identifier' => $identifier,
            ]);
            return;
        }

        session_regenerate_id(true);
        $_SESSION['user'] = [
            'id_users' => (int) $user['id_users'],
            'email' => (string) $user['email'],
            'student_code' => $user['student_code'] !== null ? (string) $user['student_code'] : null,
            'display_name' => (string) $user['display_name'],
            'roles' => $users->getRoleNames((int) $user['id_users']),
        ];
        Security::refreshCsrfToken();

        $this->redirect('/');
    }

    public function logout(): void
    {
        if (!Security::verifyCsrf($_POST['_csrf'] ?? null)) {
            http_response_code(419);
            echo 'CSRF token ไม่ถูกต้อง';
            return;
        }

        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', [
                'expires' => time() - 42000,
                'path' => $params['path'] ?: '/',
                'domain' => $params['domain'],
                'secure' => $params['secure'],
                'httponly' => $params['httponly'],
                'samesite' => $params['samesite'] ?? 'Lax',
            ]);
        }
        session_destroy();
        $this->redirect('/login');
    }
}
