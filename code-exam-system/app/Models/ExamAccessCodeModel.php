<?php

declare(strict_types=1);

final class ExamAccessCodeModel
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getConnection();
    }

    public function findExamById(int $examId): ?array
    {
        $stmt = $this->db->prepare("
            SELECT id_exams, access_code_ttl_min, require_exam_code, status
            FROM exams
            WHERE id_exams = :id
              AND deleted_at IS NULL
            LIMIT 1
        ");
        $stmt->execute([':id' => $examId]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    public function examSectionExists(int $examId, int $sectionId): bool
    {
        $stmt = $this->db->prepare("
            SELECT 1
            FROM exam_sections
            WHERE exams_id = :eid
              AND sections_id = :sid
            LIMIT 1
        ");
        $stmt->execute([':eid' => $examId, ':sid' => $sectionId]);

        return (bool)$stmt->fetchColumn();
    }

    public function listByExam(int $examId): array
    {
        $stmt = $this->db->prepare("
            SELECT *
            FROM exam_access_codes
            WHERE exams_id = :eid
            ORDER BY id_exam_access_codes DESC
        ");
        $stmt->execute([':eid' => $examId]);

        return $stmt->fetchAll();
    }

    public function findById(int $codeId): ?array
    {
        $stmt = $this->db->prepare("
            SELECT *
            FROM exam_access_codes
            WHERE id_exam_access_codes = :id
            LIMIT 1
        ");
        $stmt->execute([':id' => $codeId]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    /**
     * ยกเลิกรหัสเดิมที่ active ในขอบเขต (exams_id, section_scope_key) แล้วสร้างรหัสใหม่ 5 หลักใน Transaction เดียว
     */
    public function rotateAccessCode(int $examId, ?int $sectionId, int $ttlMin, int $userId): array
    {
        $scopeKey = $sectionId ?? 0;
        $code     = $this->generateCode5();
        $nowTs    = time();
        $validFrom  = date('Y-m-d H:i:s', $nowTs);
        $validUntil = date('Y-m-d H:i:s', $nowTs + ($ttlMin * 60));

        $this->db->beginTransaction();
        try {
            // 1. ยกเลิกรหัสเดิมที่ active อยู่ (เพื่อให้ผ่าน Unique Index uq_eac_single_active)
            $stmtRevoke = $this->db->prepare("
                UPDATE exam_access_codes
                SET status = 'revoked',
                    revoked_by = :revoked_by,
                    revoked_at = NOW()
                WHERE exams_id = :eid
                  AND section_scope_key = :scope_key
                  AND status = 'active'
            ");
            $stmtRevoke->execute([
                ':revoked_by' => $userId,
                ':eid'        => $examId,
                ':scope_key'  => $scopeKey,
            ]);
            $revokedCount = $stmtRevoke->rowCount();

            // 2. สร้างรหัสใหม่
            $stmtIns = $this->db->prepare("
                INSERT INTO exam_access_codes (
                    exams_id, sections_id, section_scope_key, access_code,
                    status, valid_from, valid_until, created_by, created_at
                ) VALUES (
                    :eid, :sid, :scope_key, :code,
                    'active', :valid_from, :valid_until, :created_by, NOW()
                )
            ");
            $stmtIns->execute([
                ':eid'         => $examId,
                ':sid'         => $sectionId,
                ':scope_key'   => $scopeKey,
                ':code'        => $code,
                ':valid_from'  => $validFrom,
                ':valid_until' => $validUntil,
                ':created_by'  => $userId,
            ]);

            $newId = (int)$this->db->lastInsertId();
            $this->db->commit();

            return [
                'access_code'            => $this->findById($newId),
                'revoked_previous_count' => $revokedCount,
            ];
        } catch (Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $e;
        }
    }

    public function revokeCode(int $codeId, int $userId): ?array
    {
        $this->db->prepare("
            UPDATE exam_access_codes
            SET status = 'revoked',
                revoked_by = :revoked_by,
                revoked_at = NOW()
            WHERE id_exam_access_codes = :id
              AND status = 'active'
        ")->execute([
            ':id'         => $codeId,
            ':revoked_by' => $userId,
        ]);

        return $this->findById($codeId);
    }

    private function generateCode5(): string
    {
        $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
        $maxIndex = strlen($alphabet) - 1;
        $result = '';
        for ($i = 0; $i < 5; $i++) {
            $result .= $alphabet[random_int(0, $maxIndex)];
        }
        return $result;
    }
}