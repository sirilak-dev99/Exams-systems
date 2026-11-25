<?php

declare(strict_types=1);

class UserModel
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getConnection();
    }

    public function findActiveByIdentifier(string $identifier): ?array
    {
        $sql = "
            SELECT
                id_users,
                email,
                student_code,
                display_name,
                active,
                password_hash,
                google_id
            FROM users
            WHERE active = 1
              AND deleted_at IS NULL
              AND (email = :email_id OR student_code = :code_id)
            LIMIT 1
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':email_id' => $identifier,
            ':code_id'  => $identifier,
        ]);

        $user = $stmt->fetch();
        return $user ?: null;
    }

    public function getUserRoles(int $userId): array
    {
        $sql = "
            SELECT r.name
            FROM user_roles ur
            JOIN roles r ON r.id_roles = ur.roles_id
            WHERE ur.users_id = :user_id
        ";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':user_id' => $userId]);
        return array_column($stmt->fetchAll(), 'name');
    }

    public function getRoleNames(int $userId): array
    {
        return $this->getUserRoles($userId);
    }

    
    public function getUserPermissions(int $userId): array
    {
        $sql = "
            SELECT DISTINCT p.code
            FROM user_roles ur
            JOIN role_permissions rp ON rp.roles_id = ur.roles_id
            JOIN permissions p ON p.id_permissions = rp.permissions_id
            WHERE ur.users_id = :user_id
        ";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':user_id' => $userId]);
        return array_column($stmt->fetchAll(), 'code');
    }

    public function getPermissionCodes(int $userId): array
    {
        return $this->getUserPermissions($userId);
    }

    /**
     * ค้นหาผู้ใช้ที่ยังไม่ถูกลบ (ใช้แยกตรวจ active = 0 เพื่อบันทึก failure_reason)
     */
    public function findByIdentifier(string $identifier): ?array
    {
        $sql = "
            SELECT
                id_users,
                email,
                student_code,
                display_name,
                active,
                password_hash,
                google_id
            FROM users
            WHERE deleted_at IS NULL
              AND (email = :email_id OR student_code = :code_id)
            LIMIT 1
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':email_id' => $identifier,
            ':code_id'  => $identifier,
        ]);

        $user = $stmt->fetch();
        return $user ?: null;
    }

    /**
     * บันทึกประวัติการเข้าสู่ระบบลง login_attempt_logs
     */
    public function logLoginAttempt(
        ?int $userId,
        string $identifier,
        bool $isSuccess,
        ?string $failureReason = null,
        string $method = 'password'
    ): void {
        $sql = "
            INSERT INTO login_attempt_logs (
                users_id,
                identifier,
                login_method,
                is_success,
                failure_reason,
                ip_address,
                user_agent,
                attempted_at
            ) VALUES (
                :users_id,
                :identifier,
                :login_method,
                :is_success,
                :failure_reason,
                :ip_address,
                :user_agent,
                NOW()
            )
        ";

        $ip = substr((string)($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45) ?: null;
        $ua = substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255) ?: null;

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':users_id'       => $userId,
            ':identifier'     => substr($identifier, 0, 254),
            ':login_method'   => $method,
            ':is_success'     => $isSuccess ? 1 : 0,
            ':failure_reason' => $failureReason,
            ':ip_address'     => $ip,
            ':user_agent'     => $ua,
        ]);
    }
}
