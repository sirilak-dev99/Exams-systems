<?php

declare(strict_types=1);

final class CourseOfferingModel
{
    private PDO $db;

    public const ALLOWED_STATUSES = ['open', 'closed', 'archived'];

    public function __construct()
    {
        $this->db = Database::getConnection();
    }

    // --- การเปิดสอนรายวิชา (course_offerings) ---

    public function findProgramVersionCourse(int $pvcId): ?array
    {
        $stmt = $this->db->prepare("
            SELECT
                pvc.id_program_version_courses,
                pvc.program_versions_id,
                pvc.courses_id
            FROM program_version_courses pvc
            JOIN courses c ON c.id_courses = pvc.courses_id
            WHERE pvc.id_program_version_courses = :id
              AND c.deleted_at IS NULL
            LIMIT 1
        ");
        $stmt->execute([':id' => $pvcId]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    public function academicTermExists(int $termId): bool
    {
        $stmt = $this->db->prepare("
            SELECT 1
            FROM academic_terms
            WHERE id_academic_terms = :id
            LIMIT 1
        ");
        $stmt->execute([':id' => $termId]);

        return (bool)$stmt->fetchColumn();
    }

    public function getAllOfferings(array $filters = []): array
    {
        $sql = "
            SELECT
                co.id_course_offerings,
                co.program_version_courses_id,
                co.program_versions_id,
                co.courses_id,
                c.code AS course_code,
                c.name AS course_name,
                co.academic_terms_id,
                at.academic_year,
                at.term_no,
                co.pass_percent,
                co.status,
                COUNT(s.id_sections) AS sections_count
            FROM course_offerings co
            JOIN courses c ON c.id_courses = co.courses_id
            JOIN academic_terms at ON at.id_academic_terms = co.academic_terms_id
            LEFT JOIN sections s ON s.course_offerings_id = co.id_course_offerings
            WHERE 1=1
        ";
        $params = [];

        if (!empty($filters['academic_terms_id']) && ctype_digit((string)$filters['academic_terms_id'])) {
            $sql .= " AND co.academic_terms_id = :term_id";
            $params[':term_id'] = (int)$filters['academic_terms_id'];
        }

        if (!empty($filters['courses_id']) && ctype_digit((string)$filters['courses_id'])) {
            $sql .= " AND co.courses_id = :course_id";
            $params[':course_id'] = (int)$filters['courses_id'];
        }

        if (!empty($filters['status']) && in_array($filters['status'], self::ALLOWED_STATUSES, true)) {
            $sql .= " AND co.status = :status";
            $params[':status'] = $filters['status'];
        }

        $sql .= "
            GROUP BY
                co.id_course_offerings,
                co.program_version_courses_id,
                co.program_versions_id,
                co.courses_id,
                c.code,
                c.name,
                co.academic_terms_id,
                at.academic_year,
                at.term_no,
                co.pass_percent,
                co.status
            ORDER BY co.id_course_offerings DESC
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll();
    }

    public function findOfferingById(int $id): ?array
    {
        $stmt = $this->db->prepare("
            SELECT
                co.id_course_offerings,
                co.program_version_courses_id,
                co.program_versions_id,
                co.courses_id,
                c.code AS course_code,
                c.name AS course_name,
                co.academic_terms_id,
                at.academic_year,
                at.term_no,
                co.pass_percent,
                co.status
            FROM course_offerings co
            JOIN courses c ON c.id_courses = co.courses_id
            JOIN academic_terms at ON at.id_academic_terms = co.academic_terms_id
            WHERE co.id_course_offerings = :id
            LIMIT 1
        ");
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    public function offeringExists(int $pvcId, int $termId, ?int $excludeId = null): bool
    {
        $sql = "
            SELECT 1
            FROM course_offerings
            WHERE program_version_courses_id = :pvc_id
              AND academic_terms_id = :term_id
        ";
        $params = [
            ':pvc_id'  => $pvcId,
            ':term_id' => $termId,
        ];

        if ($excludeId !== null) {
            $sql .= " AND id_course_offerings <> :exclude_id";
            $params[':exclude_id'] = $excludeId;
        }

        $sql .= " LIMIT 1";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return (bool)$stmt->fetchColumn();
    }

    public function createOffering(array $data): int
    {
        $stmt = $this->db->prepare("
            INSERT INTO course_offerings (
                program_version_courses_id,
                program_versions_id,
                courses_id,
                academic_terms_id,
                pass_percent,
                status
            ) VALUES (
                :program_version_courses_id,
                :program_versions_id,
                :courses_id,
                :academic_terms_id,
                :pass_percent,
                :status
            )
        ");
        $stmt->execute([
            ':program_version_courses_id' => $data['program_version_courses_id'],
            ':program_versions_id'        => $data['program_versions_id'],
            ':courses_id'                 => $data['courses_id'],
            ':academic_terms_id'          => $data['academic_terms_id'],
            ':pass_percent'               => $data['pass_percent'],
            ':status'                     => $data['status'],
        ]);

        return (int)$this->db->lastInsertId();
    }

    public function updateOffering(int $id, array $data): bool
    {
        $stmt = $this->db->prepare("
            UPDATE course_offerings
            SET pass_percent = :pass_percent,
                status = :status
            WHERE id_course_offerings = :id
        ");

        return $stmt->execute([
            ':id'           => $id,
            ':pass_percent' => $data['pass_percent'],
            ':status'       => $data['status'],
        ]);
    }

    public function offeringHasSections(int $offeringId): bool
    {
        $stmt = $this->db->prepare("
            SELECT 1
            FROM sections
            WHERE course_offerings_id = :id
            LIMIT 1
        ");
        $stmt->execute([':id' => $offeringId]);

        return (bool)$stmt->fetchColumn();
    }

    public function deleteOffering(int $id): bool
    {
        $stmt = $this->db->prepare("DELETE FROM course_offerings WHERE id_course_offerings = :id");
        return $stmt->execute([':id' => $id]);
    }

    // --- กลุ่มเรียน (sections) ---

    public function getSectionsByOffering(int $offeringId): array
    {
        $stmt = $this->db->prepare("
            SELECT
                id_sections,
                course_offerings_id,
                section_no,
                created_at
            FROM sections
            WHERE course_offerings_id = :offering_id
            ORDER BY section_no ASC
        ");
        $stmt->execute([':offering_id' => $offeringId]);

        return $stmt->fetchAll();
    }

    public function findSectionById(int $id): ?array
    {
        $stmt = $this->db->prepare("
            SELECT
                id_sections,
                course_offerings_id,
                section_no,
                created_at
            FROM sections
            WHERE id_sections = :id
            LIMIT 1
        ");
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    public function sectionNoExists(int $offeringId, string $sectionNo, ?int $excludeId = null): bool
    {
        $sql = "
            SELECT 1
            FROM sections
            WHERE course_offerings_id = :offering_id
              AND section_no = :section_no
        ";
        $params = [
            ':offering_id' => $offeringId,
            ':section_no'  => $sectionNo,
        ];

        if ($excludeId !== null) {
            $sql .= " AND id_sections <> :exclude_id";
            $params[':exclude_id'] = $excludeId;
        }

        $sql .= " LIMIT 1";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return (bool)$stmt->fetchColumn();
    }

    public function createSection(int $offeringId, string $sectionNo): int
    {
        $stmt = $this->db->prepare("
            INSERT INTO sections (course_offerings_id, section_no, created_at)
            VALUES (:course_offerings_id, :section_no, NOW())
        ");
        $stmt->execute([
            ':course_offerings_id' => $offeringId,
            ':section_no'          => $sectionNo,
        ]);

        return (int)$this->db->lastInsertId();
    }

    public function updateSection(int $id, string $sectionNo): bool
    {
        $stmt = $this->db->prepare("
            UPDATE sections
            SET section_no = :section_no
            WHERE id_sections = :id
        ");

        return $stmt->execute([
            ':id'         => $id,
            ':section_no' => $sectionNo,
        ]);
    }

    public function deleteSection(int $id): bool
    {
        $stmt = $this->db->prepare("DELETE FROM sections WHERE id_sections = :id");
        return $stmt->execute([':id' => $id]);
    }
}