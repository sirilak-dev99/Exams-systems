<?php

declare(strict_types=1);
final class AuthController extends Controller
{
    public function showLogin(): void
    {
        $this->view('auth/login', ['pageTitle' => 'เข้าสู่ระบบ']);
    }
    public function login(): void
    {
        if (!Security::verifyCsrf($_POST['_csrf'] ?? null)) {
            http_response_code(419);
            echo 'CSRF token ไม่ถูกต้อง';
            return;
        }
        // TODO: เชื่อมตาราง users และตรวจสอบ password_hash หลังยืนยัน schema จริง
        $this->view('auth/login', ['pageTitle' => 'เข้าสู่ระบบ', 'error' => 'ยังไม่ได้เชื่อมระบบตรวจสอบบัญชี']);
    }
    public function logout(): void
    {
        if (!Security::verifyCsrf($_POST['_csrf'] ?? null)) {
            http_response_code(419);
            echo 'CSRF token ไม่ถูกต้อง';
            return;
        }
        $_SESSION = [];
        session_regenerate_id(true);
        $this->redirect('/login');
    }
}
