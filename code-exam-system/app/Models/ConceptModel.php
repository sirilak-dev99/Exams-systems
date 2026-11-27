<?php

declare(strict_types=1);

final class ConceptModel
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getConnection();
    }

    public function activeCourseExists(int $courseId): bool
    {
        $stmt = $this->db->prepare("
            SELECT 1
            FROM courses
            WHERE id_courses = :id
              AND deleted_at IS NULL
            LIMIT 1
        ");
        $stmt->execute([':id' => $courseId]);

        return (bool)$stmt->fetchColumn();
    }

    // --- แนวคิดหลัก (main_concepts) ---

    public function getMainByCourse(int $courseId): array
    {
        $stmt = $this->db->prepare("
            SELECT
                mc.id_main_concepts,
                mc.courses_id,
                mc.code,
                mc.name,
                mc.description,
                COUNT(sc.id_sub_concepts) AS sub_concepts_count
            FROM main_concepts mc
            LEFT JOIN sub_concepts sc ON sc.main_concepts_id = mc.id_main_concepts
            WHERE mc.courses_id = :course_id
            GROUP BY
                mc.id_main_concepts,
                mc.courses_id,
                mc.code,
                mc.name,
                mc.description
            ORDER BY mc.code ASC, mc.id_main_concepts ASC
        ");
        $stmt->execute([':course_id' => $courseId]);

        return $stmt->fetchAll();
    }

    public function findMainById(int $id): ?array
    {
        $stmt = $this->db->prepare("
            SELECT
                id_main_concepts,
                courses_id,
                code,
                name,
                description
            FROM main_concepts
            WHERE id_main_concepts = :id
            LIMIT 1
        ");
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    public function mainCodeExists(int $courseId, string $code, ?int $excludeId = null): bool
    {
        $sql = "
            SELECT 1
            FROM main_concepts
            WHERE courses_id = :course_id
              AND code = :code
        ";
        $params = [
            ':course_id' => $courseId,
            ':code'      => $code,
        ];

        if ($excludeId !== null) {
            $sql .= " AND id_main_concepts <> :exclude_id";
            $params[':exclude_id'] = $excludeId;
        }

        $sql .= " LIMIT 1";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return (bool)$stmt->fetchColumn();
    }

    public function mainNameExists(int $courseId, string $name, ?int $excludeId = null): bool
    {
        $sql = "
            SELECT 1
            FROM main_concepts
            WHERE courses_id = :course_id
              AND name = :name
        ";
        $params = [
            ':course_id' => $courseId,
            ':name'      => $name,
        ];

        if ($excludeId !== null) {
            $sql .= " AND id_main_concepts <> :exclude_id";
            $params[':exclude_id'] = $excludeId;
        }

        $sql .= " LIMIT 1";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return (bool)$stmt->fetchColumn();
    }

    public function createMain(array $data): int
    {
        $stmt = $this->db->prepare("
            INSERT INTO main_concepts (courses_id, code, name, description)
            VALUES (:courses_id, :code, :name, :description)
        ");
        $stmt->execute([
            ':courses_id'  => $data['courses_id'],
            ':code'        => $data['code'],
            ':name'        => $data['name'],
            ':description' => $data['description'],
        ]);

        return (int)$this->db->lastInsertId();
    }

    public function updateMain(int $id, array $data): bool
    {
        $stmt = $this->db->prepare("
            UPDATE main_concepts
            SET code = :code,
                name = :name,
                description = :description
            WHERE id_main_concepts = :id
        ");

        return $stmt->execute([
            ':id'          => $id,
            ':code'        => $data['code'],
            ':name'        => $data['name'],
            ':description' => $data['description'],
        ]);
    }

    public function mainHasSubConcepts(int $mainId): bool
    {
        $stmt = $this->db->prepare("
            SELECT 1
            FROM sub_concepts
            WHERE main_concepts_id = :id
            LIMIT 1
        ");
        $stmt->execute([':id' => $mainId]);

        return (bool)$stmt->fetchColumn();
    }

    public function deleteMain(int $id): bool
    {
        $stmt = $this->db->prepare("DELETE FROM main_concepts WHERE id_main_concepts = :id");
        return $stmt->execute([':id' => $id]);
    }

    // --- แนวคิดย่อย (sub_concepts) ---

    public function getSubByMain(int $mainId): array
    {
        $stmt = $this->db->prepare("
            SELECT
                id_sub_concepts,
                main_concepts_id,
                courses_id,
                code,
                name,
                description
            FROM sub_concepts
            WHERE main_concepts_id = :main_id
            ORDER BY code ASC, id_sub_concepts ASC
        ");
        $stmt->execute([':main_id' => $mainId]);

        return $stmt->fetchAll();
    }

    public function findSubById(int $id): ?array
    {
        $stmt = $this->db->prepare("
            SELECT
                id_sub_concepts,
                main_concepts_id,
                courses_id,
                code,
                name,
                description
            FROM sub_concepts
            WHERE id_sub_concepts = :id
            LIMIT 1
        ");
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    public function subCodeExistsInCourse(int $courseId, string $code, ?int $excludeId = null): bool
    {
        $sql = "
            SELECT 1
            FROM sub_concepts
            WHERE courses_id = :course_id
              AND code = :code
        ";
        $params = [
            ':course_id' => $courseId,
            ':code'      => $code,
        ];

        if ($excludeId !== null) {
            $sql .= " AND id_sub_concepts <> :exclude_id";
            $params[':exclude_id'] = $excludeId;
        }

        $sql .= " LIMIT 1";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return (bool)$stmt->fetchColumn();
    }

    public function subNameExistsInMain(int $mainId, string $name, ?int $excludeId = null): bool
    {
        $sql = "
            SELECT 1
            FROM sub_concepts
            WHERE main_concepts_id = :main_id
              AND name = :name
        ";
        $params = [
            ':main_id' => $mainId,
            ':name'    => $name,
        ];

        if ($excludeId !== null) {
            $sql .= " AND id_sub_concepts <> :exclude_id";
            $params[':exclude_id'] = $excludeId;
        }

        $sql .= " LIMIT 1";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return (bool)$stmt->fetchColumn();
    }

    public function createSub(array $data): int
    {
        $stmt = $this->db->prepare("
            INSERT INTO sub_concepts (main_concepts_id, courses_id, code, name, description)
            VALUES (:main_concepts_id, :courses_id, :code, :name, :description)
        ");
        $stmt->execute([
            ':main_concepts_id' => $data['main_concepts_id'],
            ':courses_id'       => $data['courses_id'],
            ':code'             => $data['code'],
            ':name'             => $data['name'],
            ':description'      => $data['description'],
        ]);

        return (int)$this->db->lastInsertId();
    }

    public function updateSub(int $id, array $data): bool
    {
        $stmt = $this->db->prepare("
            UPDATE sub_concepts
            SET main_concepts_id = :main_concepts_id,
                code = :code,
                name = :name,
                description = :description
            WHERE id_sub_concepts = :id
        ");

        return $stmt->execute([
            ':id'               => $id,
            ':main_concepts_id' => $data['main_concepts_id'],
            ':code'             => $data['code'],
            ':name'             => $data['name'],
            ':description'      => $data['description'],
        ]);
    }

    public function deleteSub(int $id): bool
    {
        $stmt = $this->db->prepare("DELETE FROM sub_concepts WHERE id_sub_concepts = :id");
        return $stmt->execute([':id' => $id]);
    }
}