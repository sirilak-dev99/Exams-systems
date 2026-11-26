<?php

declare(strict_types=1);

final class CourseModel
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getConnection();
    }

    // --- กลุ่มวิชา (course_groups) ---

    public function getAllGroups(?string $q = null): array
    {
        $sql = "
            SELECT
                cg.id_course_groups,
                cg.name,
                cg.created_at,
                COUNT(c.id_courses) AS courses_count
            FROM course_groups cg
            LEFT JOIN courses c
                ON c.course_groups_id = cg.id_course_groups
               AND c.deleted_at IS NULL
            WHERE 1=1
        ";
        $params = [];

        if ($q !== null && trim($q) !== '') {
            $sql .= " AND cg.name LIKE :q";
            $params[':q'] = '%' . trim($q) . '%';
        }

        $sql .= " GROUP BY cg.id_course_groups, cg.name, cg.created_at ORDER BY cg.id_course_groups DESC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll();
    }

    public function findGroupById(int $id): ?array
    {
        $stmt = $this->db->prepare("
            SELECT id_course_groups, name, created_at
            FROM course_groups
            WHERE id_course_groups = :id
            LIMIT 1
        ");
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    public function groupNameExists(string $name, ?int $excludeId = null): bool
    {
        $sql = "SELECT 1 FROM course_groups WHERE name = :name";
        $params = [':name' => $name];

        if ($excludeId !== null) {
            $sql .= " AND id_course_groups <> :exclude_id";
            $params[':exclude_id'] = $excludeId;
        }

        $sql .= " LIMIT 1";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return (bool)$stmt->fetchColumn();
    }

    public function createGroup(string $name): int
    {
        $stmt = $this->db->prepare("
            INSERT INTO course_groups (name, created_at)
            VALUES (:name, NOW())
        ");
        $stmt->execute([':name' => $name]);

        return (int)$this->db->lastInsertId();
    }

    public function updateGroup(int $id, string $name): bool
    {
        $stmt = $this->db->prepare("
            UPDATE course_groups
            SET name = :name
            WHERE id_course_groups = :id
        ");

        return $stmt->execute([
            ':id'   => $id,
            ':name' => $name,
        ]);
    }

    public function groupHasCourses(int $groupId): bool
    {
        $stmt = $this->db->prepare("
            SELECT 1
            FROM courses
            WHERE course_groups_id = :id
              AND deleted_at IS NULL
            LIMIT 1
        ");
        $stmt->execute([':id' => $groupId]);

        return (bool)$stmt->fetchColumn();
    }

    public function deleteGroup(int $id): bool
    {
        $stmt = $this->db->prepare("DELETE FROM course_groups WHERE id_course_groups = :id");
        return $stmt->execute([':id' => $id]);
    }

    // --- รายวิชา (courses) ---

    public function getAllCourses(array $filters = []): array
    {
        $sql = "
            SELECT
                c.id_courses,
                c.course_groups_id,
                cg.name AS course_group_name,
                c.created_by,
                u.display_name AS created_by_name,
                c.code,
                c.name,
                c.created_at,
                c.updated_at
            FROM courses c
            JOIN course_groups cg ON cg.id_course_groups = c.course_groups_id
            LEFT JOIN users u ON u.id_users = c.created_by
            WHERE c.deleted_at IS NULL
        ";
        $params = [];

        if (!empty($filters['course_groups_id']) && ctype_digit((string)$filters['course_groups_id'])) {
            $sql .= " AND c.course_groups_id = :group_id";
            $params[':group_id'] = (int)$filters['course_groups_id'];
        }

        if (!empty($filters['q'])) {
            $sql .= " AND (c.code LIKE :q OR c.name LIKE :q)";
            $params[':q'] = '%' . trim((string)$filters['q']) . '%';
        }

        $sql .= " ORDER BY c.id_courses DESC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll();
    }

    public function findCourseById(int $id): ?array
    {
        $stmt = $this->db->prepare("
            SELECT
                c.id_courses,
                c.course_groups_id,
                cg.name AS course_group_name,
                c.created_by,
                c.code,
                c.name,
                c.created_at,
                c.updated_at,
                c.deleted_at
            FROM courses c
            JOIN course_groups cg ON cg.id_course_groups = c.course_groups_id
            WHERE c.id_courses = :id
              AND c.deleted_at IS NULL
            LIMIT 1
        ");
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    public function courseCodeExists(string $code, ?int $excludeId = null): bool
    {
        $sql = "SELECT 1 FROM courses WHERE code = :code AND deleted_at IS NULL";
        $params = [':code' => $code];

        if ($excludeId !== null) {
            $sql .= " AND id_courses <> :exclude_id";
            $params[':exclude_id'] = $excludeId;
        }

        $sql .= " LIMIT 1";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return (bool)$stmt->fetchColumn();
    }

    public function userExists(int $userId): bool
    {
        $stmt = $this->db->prepare("
            SELECT 1
            FROM users
            WHERE id_users = :id
              AND deleted_at IS NULL
            LIMIT 1
        ");
        $stmt->execute([':id' => $userId]);

        return (bool)$stmt->fetchColumn();
    }

    public function createCourse(array $data): int
    {
        $stmt = $this->db->prepare("
            INSERT INTO courses (
                course_groups_id,
                created_by,
                code,
                name,
                created_at,
                updated_at,
                deleted_at
            ) VALUES (
                :course_groups_id,
                :created_by,
                :code,
                :name,
                NOW(),
                NOW(),
                NULL
            )
        ");
        $stmt->execute([
            ':course_groups_id' => $data['course_groups_id'],
            ':created_by'       => $data['created_by'],
            ':code'             => $data['code'],
            ':name'             => $data['name'],
        ]);

        return (int)$this->db->lastInsertId();
    }

    public function updateCourse(int $id, array $data): bool
    {
        $stmt = $this->db->prepare("
            UPDATE courses
            SET course_groups_id = :course_groups_id,
                code = :code,
                name = :name,
                updated_at = NOW()
            WHERE id_courses = :id
              AND deleted_at IS NULL
        ");

        return $stmt->execute([
            ':id'               => $id,
            ':course_groups_id' => $data['course_groups_id'],
            ':code'             => $data['code'],
            ':name'             => $data['name'],
        ]);
    }

    public function softDeleteCourse(int $id): bool
    {
        $stmt = $this->db->prepare("
            UPDATE courses
            SET deleted_at = NOW(),
                updated_at = NOW()
            WHERE id_courses = :id
              AND deleted_at IS NULL
        ");

        return $stmt->execute([':id' => $id]);
    }
}