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
        $csrfToken = $_POST['csrf_token'] ?? null;
        $identifier = trim((string)($_POST['identifier'] ?? ''));
        $password   = (string)($_POST['password'] ?? '');

        $token = $_POST['csrf_token'] ?? $_POST['_csrf'] ?? null;
        if (!Security::verifyCsrf(is_string($token) ? $token : null)) {
            http_response_code(419);
            $this->view('auth/login', [
                'pageTitle'  => 'เข้าสู่ระบบ',
                'error'      => 'เซสชันฟอร์มหมดอายุหรือไม่ถูกต้อง กรุณาลองใหม่อีกครั้ง',
                'identifier' => $identifier,
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
        Security::startSession();

        // รองรับเฉพาะ POST เพื่อความปลอดภัยของ Session
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('/');
            return;
        }

        $token = $_POST['csrf_token'] ?? $_POST['_csrf'] ?? null;
        if (!Security::verifyCsrf(is_string($token) ? $token : null)) {
            http_response_code(419);
            $this->view('errors/419', [
                'pageTitle' => 'คำขอไม่ถูกต้อง',
                'message'   => 'เซสชันหมดอายุหรือไม่ถูกต้อง กรุณารีเฟรชหน้าแล้วลองใหม่อีกครั้ง',
            ]);
            return;
        }

        // ล้างข้อมูลใน Session และลบ Cookie ของ Session
        $_SESSION = [];

        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                [
                    'expires'  => time() - 42000,
                    'path'     => $params['path'] ?: '/',
                    'domain'   => $params['domain'] ?: '',
                    'secure'   => (bool)$params['secure'],
                    'httponly' => true,
                    'samesite' => $params['samesite'] ?: 'Lax',
                ]
            );
        }

        session_destroy();

        // เริ่มเซสชันใหม่สะอาดๆ เพื่อสร้าง CSRF Token ใหม่ให้หน้า Login
        Security::startSession();
        Security::refreshCsrfToken();

        $this->redirect('auth/login');
    }
}
