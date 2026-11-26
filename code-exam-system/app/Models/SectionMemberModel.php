<?php

declare(strict_types=1);

final class SectionMemberModel
{
    private PDO $db;

    public const ALLOWED_MEMBER_STATUSES = ['enrolled', 'dropped', 'completed'];
    public const ALLOWED_STAFF_ROLES     = ['teacher', 'assistant', 'coordinator'];

    public function __construct()
    {
        $this->db = Database::getConnection();
    }

    // --- ข้อมูลอ้างอิงพื้นฐาน ---

    public function findSectionContext(int $sectionId): ?array
    {
        $stmt =$this->db->prepare("
            SELECT
                s.id_sections,
                s.section_no,
                s.course_offerings_id,
                co.program_versions_id,
                co.courses_id
            FROM sections s
            JOIN course_offerings co ON co.id_course_offerings = s.course_offerings_id
            WHERE s.id_sections = :id
            LIMIT 1
        ");
        $stmt->execute([':id' =>$sectionId]);
        $row =$stmt->fetch();

        return $row ?: null;
    }

    public function findActiveUser(int $userId): ?array
    {
        $stmt =$this->db->prepare("
            SELECT id_users, email, student_code, display_name, active
            FROM users
            WHERE id_users = :id
              AND deleted_at IS NULL
            LIMIT 1
        ");
        $stmt->execute([':id' =>$userId]);
        $row =$stmt->fetch();

        return $row ?: null;
    }

    public function findStudentEnrollment(int $userId, int $programVersionId, ?int $enrollmentId = null): ?array
    {
        $sql = "
            SELECT
                id_enrollments,
                users_id,
                program_versions_id,
                admission_year,
                current_year_level,
                status
            FROM student_program_enrollments
            WHERE users_id = :user_id
              AND program_versions_id = :pv_id
        ";
        $params = [
            ':user_id' => $userId,
            ':pv_id'   => $programVersionId,
        ];

        if ($enrollmentId !== null) {$sql .= " AND id_enrollments = :enrollment_id";
            $params[':enrollment_id'] =$enrollmentId;
        }

        $sql .= " ORDER BY (status = 'studying') DESC, id_enrollments DESC LIMIT 1";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $row =$stmt->fetch();

        return $row ?: null;
    }

    // --- สมาชิกนิสิตในกลุ่มเรียน (course_members) ---

    public function getMembersBySection(int $sectionId, ?string $status = null): array
    {
        $sql = "
            SELECT
                cm.id_course_members,
                cm.user_id,
                u.student_code,
                u.display_name,
                u.email,
                cm.enrollments_id,
                cm.program_versions_id,
                cm.section_id,
                cm.course_offerings_id,
                cm.enrollment_status,
                cm.enrolled_at,
                cm.dropped_at
            FROM course_members cm
            JOIN users u ON u.id_users = cm.user_id
            WHERE cm.section_id = :section_id
        ";
        $params = [':section_id' =>$sectionId];

        if ($status !== null && in_array($status, self::ALLOWED_MEMBER_STATUSES, true)) {$sql .= " AND cm.enrollment_status = :status";
            $params[':status'] =$status;
        }

        $sql .= " ORDER BY cm.id_course_members ASC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll();
    }

    public function findMemberById(int $id): ?array
    {
        $stmt =$this->db->prepare("
            SELECT
                cm.id_course_members,
                cm.user_id,
                u.student_code,
                u.display_name,
                u.email,
                cm.enrollments_id,
                cm.program_versions_id,
                cm.section_id,
                cm.course_offerings_id,
                cm.enrollment_status,
                cm.enrolled_at,
                cm.dropped_at
            FROM course_members cm
            JOIN users u ON u.id_users = cm.user_id
            WHERE cm.id_course_members = :id
            LIMIT 1
        ");
        $stmt->execute([':id' =>$id]);
        $row =$stmt->fetch();

        return $row ?: null;
    }

    public function findMemberByUserOffering(int $userId, int$offeringId): ?array
    {
        $stmt =$this->db->prepare("
            SELECT *
            FROM course_members
            WHERE user_id = :user_id
              AND course_offerings_id = :offering_id
            LIMIT 1
        ");
        $stmt->execute([
            ':user_id'     => $userId,
            ':offering_id' => $offeringId,
        ]);
        $row =$stmt->fetch();

        return $row ?: null;
    }

    public function addMember(array $data): int
    {
        $stmt =$this->db->prepare("
            INSERT INTO course_members (
                user_id,
                enrollments_id,
                program_versions_id,
                section_id,
                course_offerings_id,
                enrollment_status,
                enrolled_at,
                dropped_at
            ) VALUES (
                :user_id,
                :enrollments_id,
                :program_versions_id,
                :section_id,
                :course_offerings_id,
                'enrolled',
                NOW(),
                NULL
            )
        ");
        $stmt->execute([
            ':user_id'             => $data['user_id'],
            ':enrollments_id'      => $data['enrollments_id'],
            ':program_versions_id' => $data['program_versions_id'],
            ':section_id'          => $data['section_id'],
            ':course_offerings_id' => $data['course_offerings_id'],
        ]);

        return (int)$this->db->lastInsertId();
    }

    public function updateMember(int $id, int $sectionId, string$status): bool
    {
        $droppedAtSql =$status === 'dropped' ? 'COALESCE(dropped_at, NOW())' : 'NULL';

        $stmt =$this->db->prepare("
            UPDATE course_members
            SET section_id = :section_id,
                enrollment_status = :status,
                dropped_at = {$droppedAtSql}
            WHERE id_course_members = :id
        ");

        return $stmt->execute([
            ':id'         => $id,
            ':section_id' => $sectionId,
            ':status'     => $status,
        ]);
    }

    public function dropMember(int $id): bool
    {
        $stmt =$this->db->prepare("
            UPDATE course_members
            SET enrollment_status = 'dropped',
                dropped_at = NOW()
            WHERE id_course_members = :id
        ");

        return $stmt->execute([':id' =>$id]);
    }

    // --- ผู้รับผิดชอบ / ผู้สอนประจำกลุ่มเรียน (section_staff) ---

    public function getStaffBySection(int $sectionId): array
    {
        $stmt =$this->db->prepare("
            SELECT
                ss.id_section_staff,
                ss.user_id,
                u.display_name,
                u.email,
                ss.section_id,
                ss.staff_role
            FROM section_staff ss
            JOIN users u ON u.id_users = ss.user_id
            WHERE ss.section_id = :section_id
            ORDER BY ss.id_section_staff ASC
        ");
        $stmt->execute([':section_id' =>$sectionId]);

        return $stmt->fetchAll();
    }

    public function findStaffById(int $id): ?array
    {
        $stmt =$this->db->prepare("
            SELECT
                ss.id_section_staff,
                ss.user_id,
                u.display_name,
                u.email,
                ss.section_id,
                ss.staff_role
            FROM section_staff ss
            JOIN users u ON u.id_users = ss.user_id
            WHERE ss.id_section_staff = :id
            LIMIT 1
        ");
        $stmt->execute([':id' =>$id]);
        $row =$stmt->fetch();

        return $row ?: null;
    }

    public function staffExists(int $userId, int$sectionId): bool
    {
        $stmt =$this->db->prepare("
            SELECT 1
            FROM section_staff
            WHERE user_id = :user_id
              AND section_id = :section_id
            LIMIT 1
        ");
        $stmt->execute([
            ':user_id'    => $userId,
            ':section_id' => $sectionId,
        ]);

        return (bool)$stmt->fetchColumn();
    }

    public function addStaff(int $userId, int $sectionId, string$role): int
    {
        $stmt =$this->db->prepare("
            INSERT INTO section_staff (user_id, section_id, staff_role)
            VALUES (:user_id, :section_id, :staff_role)
        ");
        $stmt->execute([
            ':user_id'    => $userId,
            ':section_id' => $sectionId,
            ':staff_role' => $role,
        ]);

        return (int)$this->db->lastInsertId();
    }

    public function updateStaffRole(int $id, string$role): bool
    {
        $stmt =$this->db->prepare("
            UPDATE section_staff
            SET staff_role = :staff_role
            WHERE id_section_staff = :id
        ");

        return $stmt->execute([
            ':id'         => $id,
            ':staff_role' => $role,
        ]);
    }

    public function deleteStaff(int $id): bool
    {
        $stmt =$this->db->prepare("DELETE FROM section_staff WHERE id_section_staff = :id");
        return $stmt->execute([':id' =>$id]);
    }
}