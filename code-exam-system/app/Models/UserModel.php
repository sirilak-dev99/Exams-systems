<?php

declare(strict_types=1);

final class UserModel
{
    public function __construct(private PDO $db) {}

    /** ค้นหาผู้ใช้ด้วยอีเมลหรือรหัสนิสิต โดยไม่คืนบัญชีที่ถูกปิดใช้งาน/ลบแล้ว */
    public function findActiveByIdentifier(string $identifier): ?array
    {
        // เลือกค้นหาตามรูปแบบตัวระบุ ป้องกันกรณีข้อความเดียวกันไปตรงกับ
        // email ของบัญชีหนึ่งและ student_code ของอีกบัญชีหนึ่ง
        if (filter_var($identifier, FILTER_VALIDATE_EMAIL)) {
            $sql = <<<'SQL'
                SELECT id_users, email, student_code, display_name, active, password_hash, deleted_at
                FROM users
                WHERE email = :identifier
                  AND active = 1
                  AND deleted_at IS NULL
                LIMIT 1
            SQL;
        } else {
            $sql = <<<'SQL'
                SELECT id_users, email, student_code, display_name, active, password_hash, deleted_at
                FROM users
                WHERE student_code = :identifier
                  AND active = 1
                  AND deleted_at IS NULL
                LIMIT 1
            SQL;
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute(['identifier' => $identifier]);

        $user = $stmt->fetch();
        return $user ?: null;
    }

    /** คืนชื่อ Role ทั้งหมดที่ผูกกับผู้ใช้ */
    public function getRoleNames(int $userId): array
    {
        $sql = <<<'SQL'
            SELECT r.name
            FROM user_roles ur
            INNER JOIN roles r ON r.id_roles = ur.roles_id
            WHERE ur.users_id = :user_id
            ORDER BY r.name
        SQL;

        $stmt = $this->db->prepare($sql);
        $stmt->execute(['user_id' => $userId]);
        return array_map('strval', $stmt->fetchAll(PDO::FETCH_COLUMN));
    }
}
