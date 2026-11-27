<?php

declare(strict_types=1);

final class GradingModel
{
    private PDO $db;

    public const OBJECTIVE_TYPES = ['multiple_choice', 'true_false'];
    public const ALLOWED_ASSIGNMENT_STATUSES = ['assigned', 'in_progress', 'completed', 'reassigned', 'cancelled'];

    public function __construct()
    {
        $this->db = Database::getConnection();
    }

    public function findExamById(int $examId): ?array
    {
        $stmt = $this->db->prepare("
            SELECT id_exams, title, status
            FROM exams
            WHERE id_exams = :id
              AND deleted_at IS NULL
            LIMIT 1
        ");
        $stmt->execute([':id' => $examId]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    public function activeUserExists(int $userId): bool
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

    /**
     * [Task 42] ตรวจคำตอบปรนัยและหลายตัวเลือกตาม question_options.is_correct และ scoring_logic
     * พร้อมคำนวณคะแนนรวม attempts.score ให้เท่ากับผลรวมรายข้อ
     */
    public function gradeObjectiveAttempt(int $attemptId, int $graderUserId): array
    {
        $this->db->beginTransaction();
        try {
            $stmtAtt = $this->db->prepare("
                SELECT *
                FROM attempts
                WHERE id_attempts = :id
                LIMIT 1
                FOR UPDATE
            ");
            $stmtAtt->execute([':id' => $attemptId]);
            $attempt = $stmtAtt->fetch();

            if (!$attempt) {
                $this->db->rollBack();
                return ['error' => [404, 'ATTEMPT_NOT_FOUND', 'ไม่พบข้อมูลรอบการสอบ']];
            }

            $examId = (int)$attempt['exams_id'];
            $this->setAuditSessionVars($graderUserId, 'Auto-graded objective questions by scoring_logic');

            // ดึงข้อสอบในชุดสอบพร้อม scoring_logic
            $stmtEq = $this->db->prepare("
                SELECT
                    eq.id_exam_questions,
                    eq.question_version_id,
                    eq.score AS max_score,
                    q.type,
                    q.scoring_logic
                FROM exam_questions eq
                JOIN questions q ON q.id_questions = eq.questions_id
                WHERE eq.exams_id = :eid
            ");
            $stmtEq->execute([':eid' => $examId]);
            $eqMap = [];
            $examMaxScore = 0.0;
            foreach ($stmtEq->fetchAll() as $row) {
                $eqMap[(int)$row['id_exam_questions']] = $row;
                $examMaxScore += (float)$row['max_score'];
            }
            $examMaxScore = round($examMaxScore, 2);

            $stmtAns = $this->db->prepare("
                SELECT *
                FROM attempt_answers
                WHERE attempts_id = :aid
                ORDER BY id_attempt_answers ASC
            ");
            $stmtAns->execute([':aid' => $attemptId]);
            $answers = $stmtAns->fetchAll();

            $stmtCorrect = $this->db->prepare("
                SELECT id_question_options
                FROM question_options
                WHERE question_versions_id = :vid
                  AND is_correct = 1
                ORDER BY id_question_options ASC
            ");
            $stmtSelected = $this->db->prepare("
                SELECT question_options_id
                FROM attempt_answer_options
                WHERE attempt_answers_id = :aaid
                ORDER BY question_options_id ASC
            ");
            $stmtUpdateAns = $this->db->prepare("
                UPDATE attempt_answers
                SET is_correct = :is_correct,
                    score = :score
                WHERE id_attempt_answers = :aaid
            ");

            $items = [];
            foreach ($answers as $ans) {
                $aaId = (int)$ans['id_attempt_answers'];
                $eqId = (int)$ans['exam_question_id'];
                $vid  = (int)$ans['question_version_id'];
                $eq   = $eqMap[$eqId] ?? null;

                if (!$eq || !in_array($eq['type'], self::OBJECTIVE_TYPES, true)) {
                    $items[] = [
                        'id_attempt_answers' => $aaId,
                        'exam_question_id'   => $eqId,
                        'type'               => $eq['type'] ?? 'unknown',
                        'scoring_logic'      => $eq['scoring_logic'] ?? null,
                        'score'              => $ans['score'] !== null ? (float)$ans['score'] : null,
                        'auto_graded'        => false,
                    ];
                    continue;
                }

                $stmtCorrect->execute([':vid' => $vid]);
                $correctIds = array_map('intval', $stmtCorrect->fetchAll(PDO::FETCH_COLUMN));

                $stmtSelected->execute([':aaid' => $aaId]);
                $selectedIds = array_map('intval', $stmtSelected->fetchAll(PDO::FETCH_COLUMN));

                $itemMax = round((float)$eq['max_score'], 2);
                $logic   = strtolower(trim((string)($eq['scoring_logic'] ?? 'all_or_nothing')));

                [$isCorrect, $earned] = $this->calculateObjectiveScore($correctIds, $selectedIds, $itemMax, $logic);

                $stmtUpdateAns->execute([
                    ':is_correct' => $isCorrect,
                    ':score'      => $earned,
                    ':aaid'       => $aaId,
                ]);

                $items[] = [
                    'id_attempt_answers' => $aaId,
                    'exam_question_id'   => $eqId,
                    'type'               => $eq['type'],
                    'scoring_logic'      => $logic,
                    'is_correct'         => $isCorrect,
                    'score'              => $earned,
                    'max_score'          => $itemMax,
                    'auto_graded'        => true,
                ];
            }

            // คำนวณคะแนนรวมให้เท่ากับผลรวมรายข้อเสมอ
            $totalScore = $this->recalculateAttemptTotalScore($attemptId, $examMaxScore);

            $this->db->commit();

            $stmtReload = $this->db->prepare("SELECT * FROM attempts WHERE id_attempts = :id LIMIT 1");
            $stmtReload->execute([':id' => $attemptId]);

            return [
                'data' => [
                    'attempt'     => $stmtReload->fetch(),
                    'total_score' => $totalScore,
                    'max_score'   => $examMaxScore,
                    'items'       => $items,
                ],
            ];
        } catch (Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $e;
        }
    }

    /**
     * [Task 43] มอบหมายผู้ตรวจอัตนัยรายข้อ พร้อมกันมอบหมายซ้ำในงานที่ยังไม่เสร็จ
     */
    public function createGradingAssignment(
        int $examId,
        int $attemptAnswerId,
        int $graderId,
        int $assignedBy,
        ?string $note
    ): array {
        $stmtAns = $this->db->prepare("
            SELECT id_attempt_answers, exams_id
            FROM attempt_answers
            WHERE id_attempt_answers = :aaid
              AND exams_id = :eid
            LIMIT 1
        ");
        $stmtAns->execute([':aaid' => $attemptAnswerId, ':eid' => $examId]);
        if (!$stmtAns->fetch()) {
            return ['error' => [404, 'ATTEMPT_ANSWER_NOT_FOUND', 'ไม่พบคำตอบของนิสิตในชุดข้อสอบนี้']];
        }

        if (!$this->activeUserExists($graderId)) {
            return ['error' => [404, 'GRADER_NOT_FOUND', 'ไม่พบข้อมูลผู้ตรวจที่ระบุ']];
        }

        // ตรวจว่าผู้ตรวจคนเดิมยังมีงานค้าง (assigned / in_progress) ในคำตอบข้อนี้หรือไม่
        $stmtDup = $this->db->prepare("
            SELECT id_grading_assignments
            FROM grading_assignments
            WHERE attempt_answer_id = :aaid
              AND grader_id = :gid
              AND status IN ('assigned', 'in_progress')
            LIMIT 1
        ");
        $stmtDup->execute([':aaid' => $attemptAnswerId, ':gid' => $graderId]);
        if ($stmtDup->fetchColumn()) {
            return [
                'error' => [
                    409,
                    'DUPLICATE_ACTIVE_ASSIGNMENT',
                    'ผู้ตรวจคนนี้ได้รับมอบหมายให้ตรวจคำตอบข้อนี้และยังดำเนินการไม่เสร็จ',
                ],
            ];
        }

        try {
            $stmtIns = $this->db->prepare("
                INSERT INTO grading_assignments (
                    attempt_answer_id, exams_id, grader_id, assigned_by,
                    status, assigned_at, note
                ) VALUES (
                    :aaid, :eid, :gid, :assigned_by,
                    'assigned', NOW(), :note
                )
            ");
            $stmtIns->execute([
                ':aaid'        => $attemptAnswerId,
                ':eid'         => $examId,
                ':gid'         => $graderId,
                ':assigned_by' => $assignedBy,
                ':note'        => $note,
            ]);
            $id = (int)$this->db->lastInsertId();

            return ['data' => ['assignment' => $this->findAssignmentById($id)]];
        } catch (PDOException $e) {
            if ($e->getCode() === '23000') {
                return ['error' => [409, 'DUPLICATE_ACTIVE_ASSIGNMENT', 'ไม่สามารถมอบหมายซ้ำให้ผู้ตรวจคนเดิมในงานที่ยังไม่เสร็จได้']];
            }
            throw $e;
        }
    }

    public function listAssignmentsByExam(int $examId, ?int $graderId = null, ?string $status = null): array
    {
        $sql = "
            SELECT
                ga.*,
                u.display_name AS grader_name,
                aa.answer_text,
                aa.score AS current_score,
                eq.score AS max_score,
                q.text AS question_text,
                q.type AS question_type
            FROM grading_assignments ga
            JOIN users u ON u.id_users = ga.grader_id
            JOIN attempt_answers aa ON aa.id_attempt_answers = ga.attempt_answer_id
            JOIN exam_questions eq ON eq.id_exam_questions = aa.exam_question_id
            JOIN questions q ON q.id_questions = eq.questions_id
            WHERE ga.exams_id = :eid
        ";
        $params = [':eid' => $examId];

        if ($graderId !== null) {
            $sql .= " AND ga.grader_id = :gid";
            $params[':gid'] = $graderId;
        }
        if ($status !== null && in_array($status, self::ALLOWED_ASSIGNMENT_STATUSES, true)) {
            $sql .= " AND ga.status = :status";
            $params[':status'] = $status;
        }

        $sql .= " ORDER BY ga.id_grading_assignments DESC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll();
    }

    public function findAssignmentById(int $assignmentId): ?array
    {
        $stmt = $this->db->prepare("
            SELECT *
            FROM grading_assignments
            WHERE id_grading_assignments = :id
            LIMIT 1
        ");
        $stmt->execute([':id' => $assignmentId]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    public function updateAssignmentStatus(int $assignmentId, string $status, ?string $note): array
    {
        $existing = $this->findAssignmentById($assignmentId);
        if (!$existing) {
            return ['error' => [404, 'ASSIGNMENT_NOT_FOUND', 'ไม่พบรายการมอบหมายงานตรวจ']];
        }

        $startedSql   = "started_at";
        $completedSql = "completed_at";

        if ($status === 'in_progress') {
            $startedSql = "COALESCE(started_at, NOW())";
        } elseif ($status === 'completed') {
            $startedSql   = "COALESCE(started_at, NOW())";
            $completedSql = "NOW()";
        }

        $stmt = $this->db->prepare("
            UPDATE grading_assignments
            SET status = :status,
                started_at = {$startedSql},
                completed_at = {$completedSql},
                note = COALESCE(:note, note)
            WHERE id_grading_assignments = :id
        ");
        $stmt->execute([
            ':status' => $status,
            ':note'   => $note,
            ':id'     => $assignmentId,
        ]);

        return ['data' => ['assignment' => $this->findAssignmentById($assignmentId)]];
    }

    /**
     * [Task 45] บันทึกคะแนนตามเกณฑ์ Rubric (grading_details), บังคับเหตุผลเมื่อแก้คะแนน
     * และบันทึกประวัติลง score_versions อัตโนมัติผ่าน Trigger
     */
    public function saveRubricGrade(
        int $attemptAnswerId,
        int $graderId,
        ?int $gradingAssignmentId,
        array $criteria,
        ?string $reason,
        bool $markCompleted
    ): array {
        $this->db->beginTransaction();
        try {
            $stmtAns = $this->db->prepare("
                SELECT
                    aa.*,
                    eq.score AS question_max_score
                FROM attempt_answers aa
                JOIN exam_questions eq ON eq.id_exam_questions = aa.exam_question_id
                WHERE aa.id_attempt_answers = :aaid
                LIMIT 1
                FOR UPDATE
            ");
            $stmtAns->execute([':aaid' => $attemptAnswerId]);
            $ans = $stmtAns->fetch();

            if (!$ans) {
                $this->db->rollBack();
                return ['error' => [404, 'ATTEMPT_ANSWER_NOT_FOUND', 'ไม่พบคำตอบของนิสิตที่ระบุ']];
            }

            $oldScore        = $ans['score'] !== null ? round((float)$ans['score'], 2) : null;
            $questionMax     = round((float)$ans['question_max_score'], 2);
            $trimmedReason   = trim((string)$reason);

            // หากเคยมีคะแนนอยู่แล้ว (การแก้ไขคะแนน) บังคับต้องระบุเหตุผล
            if ($oldScore !== null && $trimmedReason === '') {
                $this->db->rollBack();
                return [
                    'error' => [
                        422,
                        'REASON_REQUIRED',
                        'กรุณาระบุเหตุผล (reason) ทุกครั้งที่มีการแก้ไขคะแนน',
                    ],
                ];
            }

            // ตรวจความถูกต้องของ grading_assignment_id (ถ้าส่งมา) ให้ตรงตาม Composite FK
            if ($gradingAssignmentId !== null) {
                $stmtGa = $this->db->prepare("
                    SELECT 1
                    FROM grading_assignments
                    WHERE id_grading_assignments = :gaid
                      AND attempt_answer_id = :aaid
                      AND grader_id = :gid
                    LIMIT 1
                ");
                $stmtGa->execute([
                    ':gaid' => $gradingAssignmentId,
                    ':aaid' => $attemptAnswerId,
                    ':gid'  => $graderId,
                ]);
                if (!$stmtGa->fetchColumn()) {
                    $this->db->rollBack();
                    return [
                        'error' => [
                            422,
                            'INVALID_GRADING_ASSIGNMENT',
                            'grading_assignment_id ไม่ตรงกับคำตอบข้อนี้หรือผู้ตรวจปัจจุบัน',
                        ],
                    ];
                }
            }

            // ตรวจยอดรวมคะแนน Rubric
            $rubricTotalScore = 0.0;
            $rubricMaxScore   = 0.0;
            foreach ($criteria as $c) {
                $rubricTotalScore += (float)$c['score_given'];
                $rubricMaxScore   += (float)$c['max_score'];
            }
            $rubricTotalScore = round($rubricTotalScore, 2);
            $rubricMaxScore   = round($rubricMaxScore, 2);

            if ($rubricTotalScore > $questionMax) {
                $this->db->rollBack();
                return [
                    'error' => [
                        422,
                        'SCORE_EXCEEDS_MAX',
                        "คะแนนรวมตาม Rubric ({$rubricTotalScore}) ต้องไม่เกินคะแนนเต็มของข้อ ({$questionMax})",
                    ],
                ];
            }

            // บันทึกเกณฑ์ Rubric ลง grading_details
            $this->db->prepare("
                DELETE FROM grading_details
                WHERE attempt_answer_id = :aaid
                  AND grader_id = :gid
            ")->execute([':aaid' => $attemptAnswerId, ':gid' => $graderId]);

            $stmtInsDet = $this->db->prepare("
                INSERT INTO grading_details (
                    attempt_answer_id, grader_id, grading_assignment_id,
                    criterion_name, max_score, score_given, comments, created_at
                ) VALUES (
                    :aaid, :gid, :gaid,
                    :name, :max_score, :score_given, :comments, NOW()
                )
            ");

            foreach ($criteria as $c) {
                $stmtInsDet->execute([
                    ':aaid'        => $attemptAnswerId,
                    ':gid'         => $graderId,
                    ':gaid'        => $gradingAssignmentId,
                    ':name'        => $c['criterion_name'],
                    ':max_score'   => $c['max_score'],
                    ':score_given' => $c['score_given'],
                    ':comments'    => $c['comments'],
                ]);
            }

            // ตั้งค่าตัวแปร Session เพื่อบันทึกประวัติลง score_versions อัตโนมัติผ่าน Trigger
            $auditReason = $trimmedReason !== '' ? $trimmedReason : 'ให้คะแนนตามเกณฑ์ Rubric';
            $this->setAuditSessionVars($graderId, $auditReason);

            $isCorrect = ($questionMax > 0 && $rubricTotalScore >= $questionMax) ? 1 : 0;
            $this->db->prepare("
                UPDATE attempt_answers
                SET score = :score,
                    is_correct = :is_correct
                WHERE id_attempt_answers = :aaid
            ")->execute([
                ':score'      => $rubricTotalScore,
                ':is_correct' => $isCorrect,
                ':aaid'       => $attemptAnswerId,
            ]);

            // คำนวณคะแนนรวมของ Attempt ใหม่ให้ตรงกับผลรวมรายข้อ
            $attemptId = (int)$ans['attempts_id'];
            $stmtExamMax = $this->db->prepare("SELECT COALESCE(SUM(score), 0) FROM exam_questions WHERE exams_id = :eid");
            $stmtExamMax->execute([':eid' => (int)$ans['exams_id']]);
            $examMaxScore = round((float)$stmtExamMax->fetchColumn(), 2);

            $attemptTotalScore = $this->recalculateAttemptTotalScore($attemptId, $examMaxScore);

            if ($gradingAssignmentId !== null && $markCompleted) {
                $this->db->prepare("
                    UPDATE grading_assignments
                    SET status = 'completed',
                        started_at = COALESCE(started_at, NOW()),
                        completed_at = NOW()
                    WHERE id_grading_assignments = :gaid
                ")->execute([':gaid' => $gradingAssignmentId]);
            }

            $this->db->commit();

            $detail = $this->getAnswerGradingDetail($attemptAnswerId);
            $detail['attempt_total_score'] = $attemptTotalScore;
            $detail['attempt_max_score']   = $examMaxScore;

            return ['data' => $detail];
        } catch (Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $e;
        }
    }

    /**
     * [Task 45] ดึงรายละเอียดเกณฑ์ Rubric (grading_details) และประวัติการแก้คะแนน (score_versions)
     */
    public function getAnswerGradingDetail(int $attemptAnswerId): ?array
    {
        $stmtAns = $this->db->prepare("
            SELECT
                aa.*,
                eq.score AS question_max_score,
                q.text AS question_text,
                q.type AS question_type
            FROM attempt_answers aa
            JOIN exam_questions eq ON eq.id_exam_questions = aa.exam_question_id
            JOIN questions q ON q.id_questions = eq.questions_id
            WHERE aa.id_attempt_answers = :aaid
            LIMIT 1
        ");
        $stmtAns->execute([':aaid' => $attemptAnswerId]);
        $ans = $stmtAns->fetch();
        if (!$ans) {
            return null;
        }

        $stmtRubric = $this->db->prepare("
            SELECT
                gd.*,
                u.display_name AS grader_name
            FROM grading_details gd
            JOIN users u ON u.id_users = gd.grader_id
            WHERE gd.attempt_answer_id = :aaid
            ORDER BY gd.id_grading_details ASC
        ");
        $stmtRubric->execute([':aaid' => $attemptAnswerId]);
        $rubricDetails = $stmtRubric->fetchAll();

        $rubricTotal = 0.0;
        $rubricMax   = 0.0;
        foreach ($rubricDetails as $r) {
            $rubricTotal += (float)$r['score_given'];
            $rubricMax   += (float)$r['max_score'];
        }

        $stmtVersions = $this->db->prepare("
            SELECT
                sv.*,
                u.display_name AS graded_by_name
            FROM score_versions sv
            JOIN users u ON u.id_users = sv.graded_by
            WHERE sv.attempt_answer_id = :aaid
            ORDER BY sv.id_score_versions DESC
        ");
        $stmtVersions->execute([':aaid' => $attemptAnswerId]);

        return [
            'answer'             => $ans,
            'question_max_score' => round((float)$ans['question_max_score'], 2),
            'rubric_details'     => $rubricDetails,
            'rubric_total_score' => round($rubricTotal, 2),
            'rubric_max_score'   => round($rubricMax, 2),
            'score_versions'     => $stmtVersions->fetchAll(),
        ];
    }

    private function calculateObjectiveScore(array $correctIds, array $selectedIds, float $maxScore, string $logic): array
    {
        if ($correctIds === []) {
            return [0, 0.00];
        }

        if ($logic === 'partial_credit' || $logic === 'partial') {
            $matchedCorrect = count(array_intersect($selectedIds, $correctIds));
            $wrongSelected  = count(array_diff($selectedIds, $correctIds));
            $netRatio       = ($matchedCorrect - $wrongSelected) / count($correctIds);
            $netRatio       = max(0.0, min(1.0, $netRatio));
            $earned         = round($maxScore * $netRatio, 2);
            $isCorrect      = ($selectedIds === $correctIds) ? 1 : 0;

            return [$isCorrect, $earned];
        }

        // ค่าเริ่มต้น: all_or_nothing
        $matched = ($selectedIds === $correctIds);
        return [$matched ? 1 : 0, $matched ? round($maxScore, 2) : 0.00];
    }

    private function recalculateAttemptTotalScore(int $attemptId, float $examMaxScore): float
    {
        $stmtSum = $this->db->prepare("
            SELECT COALESCE(SUM(score), 0)
            FROM attempt_answers
            WHERE attempts_id = :aid
        ");
        $stmtSum->execute([':aid' => $attemptId]);
        $totalScore = round((float)$stmtSum->fetchColumn(), 2);

        if ($totalScore > $examMaxScore) {
            $totalScore = $examMaxScore;
        }

        $this->db->prepare("
            UPDATE attempts
            SET score = :score,
                max_score = :max_score
            WHERE id_attempts = :aid
        ")->execute([
            ':score'     => $totalScore,
            ':max_score' => $examMaxScore,
            ':aid'       => $attemptId,
        ]);

        return $totalScore;
    }

    private function setAuditSessionVars(int $userId, string $reason): void
    {
        $quotedReason = $this->db->quote(mb_substr($reason, 0, 255));
        $this->db->exec("SET @current_user_id = {$userId}, @score_change_reason = {$quotedReason}");
    }
}