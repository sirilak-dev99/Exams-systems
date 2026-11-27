<?php

declare(strict_types=1);

final class ExamVersionReleaseModel
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getConnection();
    }

    public function findExamById(int $examId): ?array
    {
        $stmt = $this->db->prepare("
            SELECT id_exams, courses_id, title, status
            FROM exams
            WHERE id_exams = :id
              AND deleted_at IS NULL
            LIMIT 1
        ");
        $stmt->execute([':id' => $examId]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    public function findExamQuestionRow(int $examId, int $questionId): ?array
    {
        $stmt = $this->db->prepare("
            SELECT id_exam_questions, exams_id, questions_id, question_version_id, courses_id
            FROM exam_questions
            WHERE exams_id = :eid
              AND questions_id = :qid
            LIMIT 1
        ");
        $stmt->execute([':eid' => $examId, ':qid' => $questionId]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    public function versionBelongsToQuestion(int $versionId, int $questionId, int $courseId): bool
    {
        $stmt = $this->db->prepare("
            SELECT 1
            FROM question_versions qv
            JOIN questions q ON q.id_questions = qv.questions_id
            WHERE qv.id_question_versions = :vid
              AND qv.questions_id = :qid
              AND COALESCE(qv.courses_id, q.courses_id) = :cid
              AND q.deleted_at IS NULL
            LIMIT 1
        ");
        $stmt->execute([':vid' => $versionId, ':qid' => $questionId, ':cid' => $courseId]);

        return (bool)$stmt->fetchColumn();
    }

    public function listReleasesByExam(int $examId): array
    {
        $stmt = $this->db->prepare("
            SELECT *
            FROM exam_question_version_releases
            WHERE exams_id = :eid
            ORDER BY id_exam_question_version_releases DESC
        ");
        $stmt->execute([':eid' => $examId]);

        return $stmt->fetchAll();
    }

    /**
     * 1. สร้าง release ใน exam_question_version_releases ก่อน (เพื่อให้ผ่าน Trigger 4)
     * 2. อัปเดต exam_questions เป็นเวอร์ชันใหม่
     * 3. อัปเดต attempt_answers ของผู้สอบที่กำลังทำข้อสอบ (attempts.status = 'in_progress')
     */
    public function releaseNewVersion(
        int $examQuestionId,
        int $examId,
        int $questionId,
        int $oldVersionId,
        int $newVersionId,
        string $releaseReason,
        int $releasedBy
    ): array {
        $this->db->beginTransaction();
        try {
            // 1. สร้าง release ก่อนอัปเดต exam_questions
            $stmtRel = $this->db->prepare("
                INSERT INTO exam_question_version_releases (
                    exam_question_id, exams_id, questions_id,
                    old_question_version_id, new_question_version_id,
                    release_reason, released_by, released_at
                ) VALUES (
                    :eq_id, :eid, :qid,
                    :old_vid, :new_vid,
                    :release_reason, :released_by, NOW()
                )
            ");
            $stmtRel->execute([
                ':eq_id'          => $examQuestionId,
                ':eid'            => $examId,
                ':qid'            => $questionId,
                ':old_vid'        => $oldVersionId,
                ':new_vid'        => $newVersionId,
                ':release_reason' => $releaseReason,
                ':released_by'    => $releasedBy,
            ]);
            $releaseId = (int)$this->db->lastInsertId();

            // 2. อัปเดต exam_questions
            $stmtEq = $this->db->prepare("
                UPDATE exam_questions
                SET question_version_id = :new_vid
                WHERE id_exam_questions = :eq_id
                  AND exams_id = :eid
                  AND questions_id = :qid
                  AND question_version_id = :old_vid
            ");
            $stmtEq->execute([
                ':new_vid' => $newVersionId,
                ':eq_id'   => $examQuestionId,
                ':eid'     => $examId,
                ':qid'     => $questionId,
                ':old_vid' => $oldVersionId,
            ]);

            // 3. อัปเดตผู้สอบที่กำลังทำข้อสอบอยู่ (attempts.status = 'in_progress')
            $updatedActiveAttempts = $this->syncInProgressAttempts(
                $examQuestionId,
                $examId,
                $oldVersionId,
                $newVersionId,
                $releaseId
            );

            $this->db->commit();

            $stmtGet = $this->db->prepare("
                SELECT * FROM exam_question_version_releases
                WHERE id_exam_question_version_releases = :id
                LIMIT 1
            ");
            $stmtGet->execute([':id' => $releaseId]);

            return [
                'release'                 => $stmtGet->fetch(),
                'updated_active_attempts' => $updatedActiveAttempts,
            ];
        } catch (Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $e;
        }
    }

    private function syncInProgressAttempts(
        int $examQuestionId,
        int $examId,
        int $oldVersionId,
        int $newVersionId,
        int $releaseId
    ): int {
        $stmt = $this->db->prepare("
            UPDATE attempt_answers aa
            JOIN attempts a ON a.id_attempts = aa.attempts_id
            SET aa.question_version_id = :new_vid,
                aa.version_release_id = :release_id
            WHERE a.exams_id = :eid
              AND a.status = 'in_progress'
              AND aa.exam_question_id = :eq_id
              AND aa.question_version_id = :old_vid
        ");
        $stmt->execute([
            ':new_vid'    => $newVersionId,
            ':release_id' => $releaseId,
            ':eid'        => $examId,
            ':eq_id'      => $examQuestionId,
            ':old_vid'    => $oldVersionId,
        ]);

        return $stmt->rowCount();
    }
}
