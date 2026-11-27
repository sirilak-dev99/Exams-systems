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
        Security::startSession();

        $isJson = Security::wantsJson();
        $input  = $isJson ? (json_decode((string)file_get_contents('php://input'), true) ?: $_POST) : $_POST;

        $identifier = trim((string)($input['identifier'] ?? $input['email'] ?? ''));
        $password   = (string)($input['password'] ?? '');

        // ตรวจ CSRF เฉพาะการส่งผ่านฟอร์มหน้าเว็บ
        if (!$isJson) {
            $token = $input['csrf_token'] ?? $input['_csrf'] ?? null;
            if (!Security::verifyCsrf(is_string($token) ? $token : null)) {
                http_response_code(419);
                $this->view('auth/login', [
                    'pageTitle'  => 'เข้าสู่ระบบ',
                    'error'      => 'เซสชันฟอร์มหมดอายุหรือไม่ถูกต้อง กรุณาลองใหม่อีกครั้ง',
                    'identifier' => $identifier,
                ]);
                return;
            }
        }

        if ($identifier === '' || $password === '') {
            $this->respondLoginError($isJson, 422, 'VALIDATION_ERROR', 'กรุณากรอกอีเมลหรือรหัสนิสิต และรหัสผ่าน', $identifier);
            return;
        }

        $users = new UserModel();
        $user  = $users->findByIdentifier($identifier);

        // กรณีไม่พบบัญชี
        if (!$user) {
            $users->logLoginAttempt(null, $identifier, false, 'user_not_found');
            $this->respondLoginError($isJson, 401, 'INVALID_CREDENTIALS', 'อีเมล/รหัสนิสิต หรือรหัสผ่านไม่ถูกต้อง', $identifier);
            return;
        }

        $userId = (int)$user['id_users'];

        // กรณีบัญชีถูกปิดใช้งาน (active = 0)
        if ((int)$user['active'] !== 1) {
            $users->logLoginAttempt($userId, $identifier, false, 'account_inactive');
            $this->respondLoginError($isJson, 403, 'ACCOUNT_INACTIVE', 'บัญชีผู้ใช้นี้ถูกระงับการใช้งาน กรุณาติดต่อผู้ดูแลระบบ', $identifier);
            return;
        }

        // กรณีไม่มีรหัสผ่านหรือรหัสผ่านไม่ตรง
        if (empty($user['password_hash']) || !password_verify($password, (string)$user['password_hash'])) {
            $users->logLoginAttempt($userId, $identifier, false, 'invalid_password');
            $this->respondLoginError($isJson, 401, 'INVALID_CREDENTIALS', 'อีเมล/รหัสนิสิต หรือรหัสผ่านไม่ถูกต้อง', $identifier);
            return;
        }

        // เข้าสู่ระบบสำเร็จ
        $users->logLoginAttempt($userId, $identifier, true, null);

        session_regenerate_id(true);
        $_SESSION['user'] = [
            'id_users'     => $userId,
            'email'        => (string)$user['email'],
            'student_code' => $user['student_code'] !== null ? (string)$user['student_code'] : null,
            'display_name' => (string)$user['display_name'],
            'roles'        => $users->getUserRoles($userId),
            'permissions'  => $users->getUserPermissions($userId),
        ];
        $_SESSION['_last_activity'] = time();
        Security::refreshCsrfToken();

        if ($isJson) {
            $this->sendJson(200, ['user' => $_SESSION['user']], null);
            return;
        }

        $this->redirect('/');
    }

    public function logout(): void
    {
        Security::startSession();
        $isJson = Security::wantsJson();

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            if ($isJson) {
                $this->sendJson(405, null, ['code' => 'METHOD_NOT_ALLOWED', 'message' => 'อนุญาตเฉพาะเมธอด POST']);
                return;
            }
            $this->redirect('/');
            return;
        }

        if (!$isJson) {
            $token = $_POST['csrf_token'] ?? $_POST['_csrf'] ?? null;
            if (!Security::verifyCsrf(is_string($token) ? $token : null)) {
                http_response_code(419);
                $this->view('errors/419', [
                    'pageTitle' => 'คำขอไม่ถูกต้อง',
                    'message'   => 'เซสชันหมดอายุหรือไม่ถูกต้อง กรุณารีเฟรชหน้าแล้วลองใหม่อีกครั้ง',
                ]);
                return;
            }
        }

        Security::destroySessionData();
        Security::startSession();
        Security::refreshCsrfToken();

        if ($isJson) {
            $this->sendJson(200, ['message' => 'ออกจากระบบสำเร็จ'], null);
            return;
        }

        $this->redirect('auth/login');
    }

    private function respondLoginError(bool $isJson, int $status, string $code, string $message, string $identifier): void
    {
        if ($isJson) {
            $this->sendJson($status, null, [
                'code'    => $code,
                'message' => $message,
            ]);
            return;
        }

        http_response_code($status);
        $this->view('auth/login', [
            'pageTitle'  => 'เข้าสู่ระบบ',
            'error'      => $message,
            'identifier' => $identifier,
        ]);
    }

    private function sendJson(int $status, mixed $data, ?array $error): void
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'data'  => $data,
            'error' => $error,
        ], JSON_UNESCAPED_UNICODE);
    }
}