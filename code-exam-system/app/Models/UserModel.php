<?php

declare(strict_types=1);

class UserModel
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getConnection();
    }

    /**
     * ค้นหาผู้ใช้ที่ยังเปิดใช้งาน (active = 1) ด้วยอีเมลหรือรหัสนิสิต
     */
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
     * ดึงรายการ Role ของผู้ใช้
     */
    public function getUserRoles(int $userId): array
    {
        $sql = "
            SELECT r.id_roles, r.name
            FROM user_roles ur
            JOIN roles r ON r.id_roles = ur.roles_id
            WHERE ur.users_id = :user_id
        ";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':user_id' => $userId]);
        return array_column($stmt->fetchAll(), 'name');
    }

    /**
     * ดึงรายการชื่อ Role ของผู้ใช้
     */
    public function getRoleNames(int $userId): array
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

    /**
     * ดึงรายการ Permission code ของผู้ใช้
     */
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

    /**
     * ดึงรายการ Permission code ของผู้ใช้ (อีกวิธีหนึ่ง)
     */
    public function getPermissionCodes(int $userId): array
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

}