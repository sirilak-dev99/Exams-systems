<?php

declare(strict_types=1);

final class ProgramVersionCourseModel
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getConnection();
    }

    public function programVersionExists(int $programVersionId): bool
    {
        $stmt = $this->db->prepare("
            SELECT 1
            FROM program_versions
            WHERE id_program_versions = :id
            LIMIT 1
        ");
        $stmt->execute([':id' => $programVersionId]);

        return (bool)$stmt->fetchColumn();
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

    public function getByProgramVersion(int $programVersionId): array
    {
        $stmt = $this->db->prepare("
            SELECT
                pvc.id_program_version_courses,
                pvc.program_versions_id,
                pvc.courses_id,
                c.code AS course_code,
                c.name AS course_name,
                pvc.year_level,
                pvc.semester_no
            FROM program_version_courses pvc
            JOIN courses c ON c.id_courses = pvc.courses_id
            WHERE pvc.program_versions_id = :pv_id
              AND c.deleted_at IS NULL
            ORDER BY pvc.year_level ASC, pvc.semester_no ASC, c.code ASC
        ");
        $stmt->execute([':pv_id' => $programVersionId]);

        return $stmt->fetchAll();
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->db->prepare("
            SELECT
                pvc.id_program_version_courses,
                pvc.program_versions_id,
                pvc.courses_id,
                c.code AS course_code,
                c.name AS course_name,
                pvc.year_level,
                pvc.semester_no
            FROM program_version_courses pvc
            JOIN courses c ON c.id_courses = pvc.courses_id
            WHERE pvc.id_program_version_courses = :id
            LIMIT 1
        ");
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    public function bindingExists(int $programVersionId, int $courseId, ?int $excludeId = null): bool
    {
        $sql = "
            SELECT 1
            FROM program_version_courses
            WHERE program_versions_id = :pv_id
              AND courses_id = :course_id
        ";
        $params = [
            ':pv_id'     => $programVersionId,
            ':course_id' => $courseId,
        ];

        if ($excludeId !== null) {
            $sql .= " AND id_program_version_courses <> :exclude_id";
            $params[':exclude_id'] = $excludeId;
        }

        $sql .= " LIMIT 1";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return (bool)$stmt->fetchColumn();
    }

    public function create(array $data): int
    {
        $stmt = $this->db->prepare("
            INSERT INTO program_version_courses (
                program_versions_id,
                courses_id,
                semester_no,
                year_level
            ) VALUES (
                :program_versions_id,
                :courses_id,
                :semester_no,
                :year_level
            )
        ");
        $stmt->execute([
            ':program_versions_id' => $data['program_versions_id'],
            ':courses_id'          => $data['courses_id'],
            ':semester_no'         => $data['semester_no'],
            ':year_level'          => $data['year_level'],
        ]);

        return (int)$this->db->lastInsertId();
    }

    public function update(int $id, array $data): bool
    {
        $stmt = $this->db->prepare("
            UPDATE program_version_courses
            SET semester_no = :semester_no,
                year_level = :year_level
            WHERE id_program_version_courses = :id
        ");

        return $stmt->execute([
            ':id'          => $id,
            ':semester_no' => $data['semester_no'],
            ':year_level'  => $data['year_level'],
        ]);
    }

    public function hasOfferings(int $id): bool
    {
        $stmt = $this->db->prepare("
            SELECT 1
            FROM course_offerings
            WHERE program_version_courses_id = :id
            LIMIT 1
        ");
        $stmt->execute([':id' => $id]);

        return (bool)$stmt->fetchColumn();
    }

    public function delete(int $id): bool
    {
        $stmt = $this->db->prepare("
            DELETE FROM program_version_courses
            WHERE id_program_version_courses = :id
        ");

        return $stmt->execute([':id' => $id]);
    }
}