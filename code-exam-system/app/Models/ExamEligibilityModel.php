<?php

declare(strict_types=1);

final class ExamEligibilityModel
{
    private PDO $db;

    public const ALLOWED_GRANT_TYPES   = ['normal', 'make_up', 'late', 'legacy'];
    public const ALLOWED_GRANT_SOURCES = ['direct', 'request', 'system'];

    public function __construct()
    {
        $this->db = Database::getConnection();
    }

    public function findExamById(int $examId): ?array
    {
        $stmt = $this->db->prepare("
            SELECT id_exams, course_offerings_id, courses_id, max_attempts, status
            FROM exams
            WHERE id_exams = :id
              AND deleted_at IS NULL
            LIMIT 1
        ");
        $stmt->execute([':id' => $examId]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    public function getOfferingCandidates(int $offeringId): array
    {
        $stmt = $this->db->prepare("
            SELECT
                cm.user_id,
                cm.section_id,
                cm.course_offerings_id,
                cm.enrollment_status,
                s.section_no,
                u.student_code,
                u.display_name,
                u.email
            FROM course_members cm
            JOIN sections s ON s.id_sections = cm.section_id
            JOIN users u ON u.id_users = cm.user_id
            WHERE cm.course_offerings_id = :oid
              AND cm.enrollment_status = 'enrolled'
              AND u.deleted_at IS NULL
            ORDER BY s.section_no ASC, u.student_code ASC
        ");
        $stmt->execute([':oid' => $offeringId]);

        return $stmt->fetchAll();
    }

    public function examSectionExists(int $examId, int $sectionId, int $offeringId): bool
    {
        $stmt = $this->db->prepare("
            SELECT 1
            FROM exam_sections
            WHERE exams_id = :eid
              AND sections_id = :sid
              AND course_offerings_id = :oid
            LIMIT 1
        ");
        $stmt->execute([':eid' => $examId, ':sid' => $sectionId, ':oid' => $offeringId]);

        return (bool)$stmt->fetchColumn();
    }

    public function isEnrolledCourseMember(int $userId, int $sectionId, int $offeringId): bool
    {
        $stmt = $this->db->prepare("
            SELECT 1
            FROM course_members
            WHERE user_id = :uid
              AND section_id = :sid
              AND course_offerings_id = :oid
              AND enrollment_status = 'enrolled'
            LIMIT 1
        ");
        $stmt->execute([':uid' => $userId, ':sid' => $sectionId, ':oid' => $offeringId]);

        return (bool)$stmt->fetchColumn();
    }

    public function findEligibilityById(int $eligibilityId): ?array
    {
        $stmt = $this->db->prepare("
            SELECT *
            FROM exam_eligibilities
            WHERE id_exam_eligibilities = :id
            LIMIT 1
        ");
        $stmt->execute([':id' => $eligibilityId]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    public function findEligibilityByExamAndUser(int $examId, int $userId): ?array
    {
        $stmt = $this->db->prepare("
            SELECT *
            FROM exam_eligibilities
            WHERE exams_id = :eid
              AND users_id = :uid
            LIMIT 1
        ");
        $stmt->execute([':eid' => $examId, ':uid' => $userId]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    public function listEligibilitiesByExam(int $examId): array
    {
        $stmt = $this->db->prepare("
            SELECT
                ee.*,
                u.student_code,
                u.display_name,
                u.email,
                s.section_no
            FROM exam_eligibilities ee
            JOIN users u ON u.id_users = ee.users_id
            JOIN sections s ON s.id_sections = ee.sections_id
            WHERE ee.exams_id = :eid
            ORDER BY ee.id_exam_eligibilities DESC
        ");
        $stmt->execute([':eid' => $examId]);
        $rows = $stmt->fetchAll();

        foreach ($rows as &$row) {
            $row['grants'] = $this->getGrantsByEligibility((int)$row['id_exam_eligibilities']);
        }

        return $rows;
    }

    public function getGrantsByEligibility(int $eligibilityId): array
    {
        $stmt = $this->db->prepare("
            SELECT *
            FROM exam_eligibility_grants
            WHERE exam_eligibilities_id = :eid
            ORDER BY id_exam_eligibility_grants DESC
        ");
        $stmt->execute([':eid' => $eligibilityId]);

        return $stmt->fetchAll();
    }

    public function findGrantById(int $grantId): ?array
    {
        $stmt = $this->db->prepare("
            SELECT *
            FROM exam_eligibility_grants
            WHERE id_exam_eligibility_grants = :id
            LIMIT 1
        ");
        $stmt->execute([':id' => $grantId]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    /**
     * เพิ่มหรือคืนสิทธิ์เข้าสอบ พร้อมสร้าง Grant เริ่มต้น (normal) ใน Transaction เดียว
     */
    public function upsertEligibilityWithGrant(
        int $examId,
        int $userId,
        int $sectionId,
        int $offeringId,
        ?string $customOpenAt,
        ?string $customCloseAt,
        ?string $note,
        bool $createDefaultGrant,
        ?int $maxAttempts,
        int $approvedBy
    ): array {
        $this->db->beginTransaction();
        try {
            $existing = $this->findEligibilityByExamAndUser($examId, $userId);
            if ($existing) {
                $eligibilityId = (int)$existing['id_exam_eligibilities'];
                $this->db->prepare("
                    UPDATE exam_eligibilities
                    SET sections_id = :sid,
                        status = 'eligible',
                        custom_open_at = :open_at,
                        custom_close_at = :close_at,
                        note = :note
                    WHERE id_exam_eligibilities = :id
                ")->execute([
                    ':id'       => $eligibilityId,
                    ':sid'      => $sectionId,
                    ':open_at'  => $customOpenAt,
                    ':close_at' => $customCloseAt,
                    ':note'     => $note,
                ]);
            } else {
                $stmt = $this->db->prepare("
                    INSERT INTO exam_eligibilities (
                        exams_id, users_id, sections_id, course_offerings_id,
                        status, custom_open_at, custom_close_at, note, created_at
                    ) VALUES (
                        :eid, :uid, :sid, :oid,
                        'eligible', :open_at, :close_at, :note, NOW()
                    )
                ");
                $stmt->execute([
                    ':eid'      => $examId,
                    ':uid'      => $userId,
                    ':sid'      => $sectionId,
                    ':oid'      => $offeringId,
                    ':open_at'  => $customOpenAt,
                    ':close_at' => $customCloseAt,
                    ':note'     => $note,
                ]);
                $eligibilityId = (int)$this->db->lastInsertId();
            }

            if ($createDefaultGrant) {
                $this->db->prepare("
                    INSERT INTO exam_eligibility_grants (
                        exam_eligibilities_id, grant_type, status,
                        open_at, close_at, max_attempts, grant_source,
                        reason, approved_by, approved_at, created_at
                    ) VALUES (
                        :elig_id, 'normal', 'active',
                        :open_at, :close_at, :max_attempts, 'direct',
                        :reason, :approved_by, NOW(), NOW()
                    )
                ")->execute([
                    ':elig_id'      => $eligibilityId,
                    ':open_at'      => $customOpenAt,
                    ':close_at'     => $customCloseAt,
                    ':max_attempts' => $maxAttempts,
                    ':reason'       => $note ?? 'เปิดสิทธิ์เข้าสอบปกติ',
                    ':approved_by'  => $approvedBy,
                ]);
            }

            $this->db->commit();

            return [
                'eligibility' => $this->findEligibilityById($eligibilityId),
                'grants'      => $this->getGrantsByEligibility($eligibilityId),
            ];
        } catch (Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $e;
        }
    }

    public function createGrant(int $eligibilityId, array $data): array
    {
        $stmt = $this->db->prepare("
            INSERT INTO exam_eligibility_grants (
                exam_eligibilities_id, grant_type, status,
                open_at, close_at, max_attempts, grace_period_min,
                grant_source, reason, approved_by, approved_at, created_at
            ) VALUES (
                :elig_id, :grant_type, 'active',
                :open_at, :close_at, :max_attempts, :grace_period_min,
                :grant_source, :reason, :approved_by, NOW(), NOW()
            )
        ");
        $stmt->execute([
            ':elig_id'          => $eligibilityId,
            ':grant_type'       => $data['grant_type'],
            ':open_at'          => $data['open_at'],
            ':close_at'         => $data['close_at'],
            ':max_attempts'     => $data['max_attempts'],
            ':grace_period_min' => $data['grace_period_min'],
            ':grant_source'     => $data['grant_source'],
            ':reason'           => $data['reason'],
            ':approved_by'      => $data['approved_by'],
        ]);

        $grantId = (int)$this->db->lastInsertId();
        return $this->findGrantById($grantId) ?? [];
    }

    public function revokeEligibility(int $eligibilityId, int $revokedBy, ?string $reason): array
    {
        $this->db->beginTransaction();
        try {
            $this->db->prepare("
                UPDATE exam_eligibilities
                SET status = 'revoked',
                    note = COALESCE(:reason, note)
                WHERE id_exam_eligibilities = :id
            ")->execute([
                ':id'     => $eligibilityId,
                ':reason' => $reason,
            ]);

            $this->db->prepare("
                UPDATE exam_eligibility_grants
                SET status = 'revoked',
                    revoked_by = :revoked_by,
                    revoked_at = NOW(),
                    revoked_reason = COALESCE(:reason, 'ยกเลิกสิทธิ์ผู้เข้าสอบ')
                WHERE exam_eligibilities_id = :id
                  AND status = 'active'
            ")->execute([
                ':id'         => $eligibilityId,
                ':revoked_by' => $revokedBy,
                ':reason'     => $reason,
            ]);

            $this->db->commit();

            return [
                'eligibility' => $this->findEligibilityById($eligibilityId),
                'grants'      => $this->getGrantsByEligibility($eligibilityId),
            ];
        } catch (Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $e;
        }
    }

    public function revokeGrant(int $grantId, int $revokedBy, ?string $reason): ?array
    {
        $this->db->prepare("
            UPDATE exam_eligibility_grants
            SET status = 'revoked',
                revoked_by = :revoked_by,
                revoked_at = NOW(),
                revoked_reason = :reason
            WHERE id_exam_eligibility_grants = :id
        ")->execute([
            ':id'         => $grantId,
            ':revoked_by' => $revokedBy,
            ':reason'     => $reason ?? 'ยกเลิกโควตาสิทธิ์สอบ',
        ]);

        return $this->findGrantById($grantId);
    }
}