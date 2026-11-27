<?php

declare(strict_types=1);

final class AttemptModel
{
    private PDO $db;

    public const OBJECTIVE_TYPES = ['multiple_choice', 'true_false'];

    public function __construct()
    {
        $this->db = Database::getConnection();
    }

    public function findExamById(int $examId): ?array
    {
        $stmt = $this->db->prepare("
            SELECT *
            FROM exams
            WHERE id_exams = :id
              AND deleted_at IS NULL
            LIMIT 1
        ");
        $stmt->execute([':id' => $examId]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    public function findAttemptForUser(int $attemptId, int $userId): ?array
    {
        $stmt = $this->db->prepare("
            SELECT *
            FROM attempts
            WHERE id_attempts = :id
              AND users_id = :uid
            LIMIT 1
        ");
        $stmt->execute([':id' => $attemptId, ':uid' => $userId]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    public function getExamQuestionsForPlayer(int $examId): array
    {
        $stmt = $this->db->prepare("
            SELECT
                eq.id_exam_questions,
                eq.exams_id,
                eq.questions_id,
                eq.question_version_id,
                eq.sort_order,
                eq.score,
                q.type,
                q.text,
                q.image,
                q.shuffle_options
            FROM exam_questions eq
            JOIN questions q ON q.id_questions = eq.questions_id
            WHERE eq.exams_id = :eid
            ORDER BY eq.sort_order ASC, eq.id_exam_questions ASC
        ");
        $stmt->execute([':eid' => $examId]);
        $questions = $stmt->fetchAll();

        $optStmt = $this->db->prepare("
            SELECT id_question_options, label, text, sort_order, image
            FROM question_options
            WHERE question_versions_id = :vid
            ORDER BY sort_order ASC, id_question_options ASC
        ");

        foreach ($questions as &$q) {
            $optStmt->execute([':vid' => (int)$q['question_version_id']]);
            $q['options'] = $optStmt->fetchAll();
        }

        return $questions;
    }

    public function getSavedAnswers(int $attemptId): array
    {
        $stmt = $this->db->prepare("
            SELECT
                id_attempt_answers,
                exam_question_id,
                question_version_id,
                answer_text,
                answered_at,
                is_correct,
                score,
                displayed_order
            FROM attempt_answers
            WHERE attempts_id = :aid
            ORDER BY id_attempt_answers ASC
        ");
        $stmt->execute([':aid' => $attemptId]);
        $rows = $stmt->fetchAll();

        $optStmt = $this->db->prepare("
            SELECT question_options_id
            FROM attempt_answer_options
            WHERE attempt_answers_id = :aaid
            ORDER BY id_attempt_answer_options ASC
        ");

        foreach ($rows as &$r) {
            $optStmt->execute([':aaid' => (int)$r['id_attempt_answers']]);
            $r['selected_option_ids'] = array_map('intval', $optStmt->fetchAll(PDO::FETCH_COLUMN));
        }

        return $rows;
    }

    /**
     * [Task 37] เริ่มสอบใน Transaction พร้อม SELECT ... FOR UPDATE ที่ Grant
     */
    public function startAttempt(
        int $examId,
        int $userId,
        ?string $accessCode,
        ?string $ipAddress,
        ?string $userAgent
    ): array {
        $this->db->beginTransaction();
        try {
            $exam = $this->findExamById($examId);
            if (!$exam) {
                $this->db->rollBack();
                return ['error' => [404, 'EXAM_NOT_FOUND', 'ไม่พบข้อมูลชุดข้อสอบ']];
            }
            if ($exam['status'] !== 'published') {
                $this->db->rollBack();
                return ['error' => [422, 'EXAM_NOT_PUBLISHED', 'ชุดข้อสอบยังไม่เปิดเผยแพร่']];
            }

            // ตรวจว่ามี attempt ที่กำลังทำค้างอยู่หรือไม่
            $stmtActive = $this->db->prepare("
                SELECT id_attempts
                FROM attempts
                WHERE exams_id = :eid
                  AND users_id = :uid
                  AND status = 'in_progress'
                LIMIT 1
                FOR UPDATE
            ");
            $stmtActive->execute([':eid' => $examId, ':uid' => $userId]);
            $activeId = $stmtActive->fetchColumn();
            if ($activeId) {
                $this->db->rollBack();
                return [
                    'error' => [
                        409,
                        'ATTEMPT_ALREADY_IN_PROGRESS',
                        'คุณมีรอบการสอบที่กำลังดำเนินการอยู่ กรุณาใช้ /attempts/' . $activeId . '/resume เพื่อกลับเข้าสอบ',
                    ],
                ];
            }

            // ตรวจสิทธิ์เข้าสอบ
            $stmtElig = $this->db->prepare("
                SELECT *
                FROM exam_eligibilities
                WHERE exams_id = :eid
                  AND users_id = :uid
                  AND status = 'eligible'
                LIMIT 1
            ");
            $stmtElig->execute([':eid' => $examId, ':uid' => $userId]);
            $elig = $stmtElig->fetch();
            if (!$elig) {
                $this->db->rollBack();
                return ['error' => [403, 'NOT_ELIGIBLE', 'คุณไม่มีสิทธิ์เข้าสอบชุดข้อสอบนี้']];
            }

            // ล็อก Grant ทั้งหมดของสิทธิ์นี้ด้วย SELECT ... FOR UPDATE เพื่อกัน Race Condition
            $stmtGrants = $this->db->prepare("
                SELECT *
                FROM exam_eligibility_grants
                WHERE exam_eligibilities_id = :elig_id
                  AND status = 'active'
                ORDER BY id_exam_eligibility_grants ASC
                FOR UPDATE
            ");
            $stmtGrants->execute([':elig_id' => (int)$elig['id_exam_eligibilities']]);
            $grants = $stmtGrants->fetchAll();

            if ($grants === []) {
                $this->db->rollBack();
                return ['error' => [403, 'NO_ACTIVE_GRANT', 'ไม่พบโควตาสิทธิ์สอบ (Grant) ที่ใช้งานได้']];
            }

            $nowTs = time();
            $nowStr = date('Y-m-d H:i:s', $nowTs);
            $selectedGrant = null;
            $effectiveCloseTs = null;
            $windowMatched = false;

            $stmtUsed = $this->db->prepare("
                SELECT COUNT(*)
                FROM attempts
                WHERE exam_eligibility_grant_id = :gid
            ");

            foreach ($grants as $g) {
                $openStr  = $g['open_at'] ?? $elig['custom_open_at'] ?? $exam['open_at'];
                $closeStr = $g['close_at'] ?? $elig['custom_close_at'] ?? $exam['close_at'];
                $openTs   = strtotime((string)$openStr);
                $closeTs  = strtotime((string)$closeStr);

                if ($nowTs < $openTs || $nowTs > $closeTs) {
                    continue;
                }
                $windowMatched = true;

                $stmtUsed->execute([':gid' => (int)$g['id_exam_eligibility_grants']]);
                $usedCount = (int)$stmtUsed->fetchColumn();
                $allowedMax = $g['max_attempts'] !== null ? (int)$g['max_attempts'] : (int)$exam['max_attempts'];

                if ($usedCount < $allowedMax) {
                    $selectedGrant = $g;
                    $effectiveCloseTs = $closeTs;
                    break;
                }
            }

            if (!$windowMatched) {
                $this->db->rollBack();
                return ['error' => [422, 'OUTSIDE_EXAM_WINDOW', 'ไม่อยู่ในช่วงเวลาที่อนุญาตให้เข้าสอบ']];
            }

            if ($selectedGrant === null) {
                $this->db->rollBack();
                return ['error' => [409, 'QUOTA_EXCEEDED', 'ใช้สิทธิ์จำนวนครั้งที่สอบได้ครบตามโควตาแล้ว']];
            }

            // ตรวจรหัสเข้าสอบ (หากชุดสอบบังคับใช้รหัส)
            $verifiedCodeId = null;
            if ((int)$exam['require_exam_code'] === 1) {
                $codeRow = $this->findValidAccessCode($examId, (int)$elig['sections_id'], $accessCode, $nowStr);
                if (!$codeRow) {
                    $this->db->rollBack();
                    return ['error' => [422, 'INVALID_ACCESS_CODE', 'รหัสเข้าสอบไม่ถูกต้อง ถูกยกเลิก หรือหมดอายุแล้ว']];
                }
                $verifiedCodeId = (int)$codeRow['id_exam_access_codes'];
            }

            // คำนวณ attempt_no ถัดไป
            $stmtNo = $this->db->prepare("
                SELECT COALESCE(MAX(attempt_no), 0) + 1
                FROM attempts
                WHERE exams_id = :eid
                  AND users_id = :uid
            ");
            $stmtNo->execute([':eid' => $examId, ':uid' => $userId]);
            $nextAttemptNo = (int)$stmtNo->fetchColumn();

            $durationEndTs = $nowTs + ((int)$exam['duration_min'] * 60);
            $deadlineTs    = min($durationEndTs, (int)$effectiveCloseTs);
            if ($deadlineTs < $nowTs) {
                $deadlineTs = $nowTs;
            }
            $deadlineStr = date('Y-m-d H:i:s', $deadlineTs);

            $stmtMax = $this->db->prepare("SELECT COALESCE(SUM(score), 0) FROM exam_questions WHERE exams_id = :eid");
            $stmtMax->execute([':eid' => $examId]);
            $maxScore = round((float)$stmtMax->fetchColumn(), 2);

            $resumeToken     = bin2hex(random_bytes(24));
            $resumeTokenHash = hash('sha256', $resumeToken);

            $stmtIns = $this->db->prepare("
                INSERT INTO attempts (
                    exams_id, users_id, sections_id, course_offerings_id,
                    exam_eligibilities_id, exam_eligibility_grant_id,
                    attempt_no, started_at, delivered_at, deadline_at,
                    status, max_score, resume_token_hash, reentry_required,
                    ip_address, user_agent
                ) VALUES (
                    :eid, :uid, :sid, :oid,
                    :elig_id, :grant_id,
                    :attempt_no, :started_at, :delivered_at, :deadline_at,
                    'in_progress', :max_score, :token_hash, 0,
                    :ip, :ua
                )
            ");
            $stmtIns->execute([
                ':eid'          => $examId,
                ':uid'          => $userId,
                ':sid'          => (int)$elig['sections_id'],
                ':oid'          => (int)$elig['course_offerings_id'],
                ':elig_id'      => (int)$elig['id_exam_eligibilities'],
                ':grant_id'     => (int)$selectedGrant['id_exam_eligibility_grants'],
                ':attempt_no'   => $nextAttemptNo,
                ':started_at'   => $nowStr,
                ':delivered_at' => $nowStr,
                ':deadline_at'  => $deadlineStr,
                ':max_score'    => $maxScore,
                ':token_hash'   => $resumeTokenHash,
                ':ip'           => $ipAddress,
                ':ua'           => $userAgent,
            ]);

            $attemptId = (int)$this->db->lastInsertId();

            if ($verifiedCodeId !== null) {
                $this->logAccessCode($attemptId, $examId, $verifiedCodeId, 'start', $ipAddress, $userAgent);
            }

            $this->db->commit();

            return [
                'data' => [
                    'attempt'      => $this->findAttemptForUser($attemptId, $userId),
                    'resume_token' => $resumeToken,
                    'questions'    => $this->getExamQuestionsForPlayer($examId),
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
     * [Task 37] กลับเข้าสอบด้วยรหัสปัจจุบันและใช้ Attempt เดิม
     */
    public function resumeAttempt(
        int $attemptId,
        int $userId,
        ?string $accessCode,
        ?string $ipAddress,
        ?string $userAgent
    ): array {
        $this->db->beginTransaction();
        try {
            $stmt = $this->db->prepare("
                SELECT *
                FROM attempts
                WHERE id_attempts = :id
                  AND users_id = :uid
                LIMIT 1
                FOR UPDATE
            ");
            $stmt->execute([':id' => $attemptId, ':uid' => $userId]);
            $attempt = $stmt->fetch();

            if (!$attempt) {
                $this->db->rollBack();
                return ['error' => [404, 'ATTEMPT_NOT_FOUND', 'ไม่พบข้อมูลรอบการสอบ']];
            }

            if ($attempt['status'] !== 'in_progress') {
                $this->db->rollBack();
                return ['error' => [409, 'ATTEMPT_NOT_IN_PROGRESS', 'รอบการสอบนี้สิ้นสุดแล้ว ไม่สามารถกลับเข้าสอบได้']];
            }

            $exam = $this->findExamById((int)$attempt['exams_id']);
            if (!$exam) {
                $this->db->rollBack();
                return ['error' => [404, 'EXAM_NOT_FOUND', 'ไม่พบข้อมูลชุดข้อสอบ']];
            }

            $nowTs = time();
            $nowStr = date('Y-m-d H:i:s', $nowTs);
            $graceSec = ((int)$exam['grace_period_min']) * 60;
            if ($nowTs > (strtotime((string)$attempt['deadline_at']) + $graceSec)) {
                $this->db->rollBack();
                return ['error' => [422, 'ATTEMPT_EXPIRED', 'หมดเวลาทำข้อสอบแล้ว']];
            }

            $needCode = ((int)$exam['require_exam_code'] === 1)
                && ((int)$exam['require_code_on_resume'] === 1 || (int)$attempt['reentry_required'] === 1);

            if ($needCode) {
                $codeRow = $this->findValidAccessCode(
                    (int)$attempt['exams_id'],
                    (int)$attempt['sections_id'],
                    $accessCode,
                    $nowStr
                );
                if (!$codeRow) {
                    $this->db->rollBack();
                    return ['error' => [422, 'INVALID_ACCESS_CODE', 'กรุณากรอกรหัสเข้าสอบปัจจุบันที่ยังใช้งานได้']];
                }
                $this->logAccessCode(
                    $attemptId,
                    (int)$attempt['exams_id'],
                    (int)$codeRow['id_exam_access_codes'],
                    'resume',
                    $ipAddress,
                    $userAgent
                );
            }

            $this->db->prepare("
                UPDATE attempts
                SET reentry_required = 0,
                    ip_address = :ip,
                    user_agent = :ua
                WHERE id_attempts = :id
            ")->execute([
                ':id' => $attemptId,
                ':ip' => $ipAddress,
                ':ua' => $userAgent,
            ]);

            $this->db->commit();

            return [
                'data' => [
                    'attempt'       => $this->findAttemptForUser($attemptId, $userId),
                    'questions'     => $this->getExamQuestionsForPlayer((int)$attempt['exams_id']),
                    'saved_answers' => $this->getSavedAnswers($attemptId),
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
     * [Task 38] บันทึกคำตอบแบบ Upsert ต่อข้อ ผูก question_version + เก็บ draft และ event
     */
    public function upsertAnswer(
        int $attemptId,
        int $userId,
        int $examQuestionId,
        ?string $answerText,
        array $selectedOptionIds,
        string $saveMode,
        ?int $displayedOrder,
        ?string $ipAddress,
        ?string $userAgent
    ): array {
        $this->db->beginTransaction();
        try {
            $stmt = $this->db->prepare("
                SELECT *
                FROM attempts
                WHERE id_attempts = :id
                  AND users_id = :uid
                LIMIT 1
                FOR UPDATE
            ");
            $stmt->execute([':id' => $attemptId, ':uid' => $userId]);
            $attempt = $stmt->fetch();

            if (!$attempt) {
                $this->db->rollBack();
                return ['error' => [404, 'ATTEMPT_NOT_FOUND', 'ไม่พบข้อมูลรอบการสอบ']];
            }

            if ($attempt['status'] !== 'in_progress') {
                $this->db->rollBack();
                return ['error' => [409, 'ATTEMPT_LOCKED', 'ส่งข้อสอบแล้วหรือหมดเวลา ไม่สามารถแก้ไขคำตอบได้']];
            }

            $examId = (int)$attempt['exams_id'];

            // ดึง question_version_id ปัจจุบันจาก exam_questions ของชุดสอบ
            $stmtEq = $this->db->prepare("
                SELECT id_exam_questions, question_version_id
                FROM exam_questions
                WHERE id_exam_questions = :eq_id
                  AND exams_id = :eid
                LIMIT 1
            ");
            $stmtEq->execute([':eq_id' => $examQuestionId, ':eid' => $examId]);
            $eq = $stmtEq->fetch();
            if (!$eq) {
                $this->db->rollBack();
                return ['error' => [404, 'EXAM_QUESTION_NOT_FOUND', 'ไม่พบข้อสอบนี้ในชุดข้อสอบ']];
            }

            $questionVersionId = (int)$eq['question_version_id'];
            $cleanOptionIds = array_values(array_unique(array_map('intval', $selectedOptionIds)));

            // ตรวจว่าตัวเลือกที่เลือกอยู่ใต้ question_version_id นี้จริง
            if ($cleanOptionIds !== []) {
                $placeholders = implode(',', array_fill(0, count($cleanOptionIds), '?'));
                $stmtOpt = $this->db->prepare("
                    SELECT id_question_options
                    FROM question_options
                    WHERE question_versions_id = ?
                      AND id_question_options IN ($placeholders)
                ");
                $stmtOpt->execute(array_merge([$questionVersionId], $cleanOptionIds));
                $validOpts = array_map('intval', $stmtOpt->fetchAll(PDO::FETCH_COLUMN));
                if (count($validOpts) !== count($cleanOptionIds)) {
                    $this->db->rollBack();
                    return ['error' => [422, 'INVALID_OPTION_FOR_VERSION', 'ตัวเลือกที่เลือกไม่ตรงกับเวอร์ชันของข้อสอบ']];
                }
            }

            // ตรวจว่ามีคำตอบเดิมอยู่แล้วหรือไม่ (Upsert)
            $stmtExist = $this->db->prepare("
                SELECT id_attempt_answers
                FROM attempt_answers
                WHERE attempts_id = :aid
                  AND exam_question_id = :eq_id
                LIMIT 1
            ");
            $stmtExist->execute([':aid' => $attemptId, ':eq_id' => $examQuestionId]);
            $existingAnswerId = $stmtExist->fetchColumn();
            $isUpdate = (bool)$existingAnswerId;

            if ($isUpdate) {
                $answerId = (int)$existingAnswerId;
                // ลบตัวเลือกเก่าก่อนเพื่อไม่ให้ติด FK หาก question_version_id เปลี่ยน
                $this->db->prepare("DELETE FROM attempt_answer_options WHERE attempt_answers_id = :aaid")
                    ->execute([':aaid' => $answerId]);

                $this->db->prepare("
                    UPDATE attempt_answers
                    SET question_version_id = :vid,
                        answer_text = :answer_text,
                        answered_at = NOW(),
                        displayed_order = COALESCE(:disp_order, displayed_order)
                    WHERE id_attempt_answers = :aaid
                ")->execute([
                    ':vid'         => $questionVersionId,
                    ':answer_text' => $answerText,
                    ':disp_order'  => $displayedOrder,
                    ':aaid'        => $answerId,
                ]);
            } else {
                $stmtIns = $this->db->prepare("
                    INSERT INTO attempt_answers (
                        exam_question_id, attempts_id, exams_id, question_version_id,
                        answer_text, answered_at, displayed_order
                    ) VALUES (
                        :eq_id, :aid, :eid, :vid,
                        :answer_text, NOW(), :disp_order
                    )
                ");
                $stmtIns->execute([
                    ':eq_id'       => $examQuestionId,
                    ':aid'         => $attemptId,
                    ':eid'         => $examId,
                    ':vid'         => $questionVersionId,
                    ':answer_text' => $answerText,
                    ':disp_order'  => $displayedOrder,
                ]);
                $answerId = (int)$this->db->lastInsertId();
            }

            if ($cleanOptionIds !== []) {
                $stmtInsOpt = $this->db->prepare("
                    INSERT INTO attempt_answer_options (
                        attempt_answers_id, question_options_id, question_version_id
                    ) VALUES (:aaid, :oid, :vid)
                ");
                foreach ($cleanOptionIds as $oid) {
                    $stmtInsOpt->execute([':aaid' => $answerId, ':oid' => $oid, ':vid' => $questionVersionId]);
                }
            }

            // บันทึกฉบับร่างใน attempt_answer_drafts
            $stmtDraftNo = $this->db->prepare("
                SELECT COALESCE(MAX(draft_no), 0) + 1
                FROM attempt_answer_drafts
                WHERE attempt_answers_id = :aaid
            ");
            $stmtDraftNo->execute([':aaid' => $answerId]);
            $draftNo = (int)$stmtDraftNo->fetchColumn();

            $draftAction = $saveMode === 'autosave' ? 'autosave' : 'manual_save';
            $this->db->prepare("
                INSERT INTO attempt_answer_drafts (
                    attempt_answers_id, attempts_id, draft_no, answer_text, saved_by_action, saved_at
                ) VALUES (
                    :aaid, :aid, :draft_no, :answer_text, :action, NOW()
                )
            ")->execute([
                ':aaid'        => $answerId,
                ':aid'         => $attemptId,
                ':draft_no'    => $draftNo,
                ':answer_text' => $answerText,
                ':action'      => $draftAction,
            ]);

            // บันทึกเหตุการณ์ใน attempt_answer_events
            $eventType = $saveMode === 'autosave' ? 'autosave' : ($isUpdate ? 'update' : 'save');
            $stmtEv = $this->db->prepare("
                INSERT INTO attempt_answer_events (
                    attempt_answers_id, attempts_id, question_version_id,
                    event_type, answer_text_snapshot, selected_options_snapshot,
                    ip_address, user_agent, event_at
                ) VALUES (
                    :aaid, :aid, :vid,
                    :event_type, :text_snap, :opts_snap,
                    :ip, :ua, NOW()
                )
            ");
            $stmtEv->execute([
                ':aaid'       => $answerId,
                ':aid'        => $attemptId,
                ':vid'        => $questionVersionId,
                ':event_type' => $eventType,
                ':text_snap'  => $answerText,
                ':opts_snap'  => json_encode($cleanOptionIds),
                ':ip'         => $ipAddress,
                ':ua'         => $userAgent,
            ]);
            $eventId = (int)$this->db->lastInsertId();

            $this->db->commit();

            $stmtGet = $this->db->prepare("SELECT * FROM attempt_answers WHERE id_attempt_answers = :id LIMIT 1");
            $stmtGet->execute([':id' => $answerId]);

            return [
                'data' => [
                    'answer'              => $stmtGet->fetch(),
                    'selected_option_ids' => $cleanOptionIds,
                    'draft_no'            => $draftNo,
                    'event_id'            => $eventId,
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
     * [Task 39] ส่งข้อสอบ ล็อกสถานะ คำนวณคะแนนปรนัยทันที และบันทึกคะแนนรวม
     */
    public function submitAttempt(
        int $attemptId,
        int $userId,
        ?string $ipAddress,
        ?string $userAgent
    ): array {
        $this->db->beginTransaction();
        try {
            $stmt = $this->db->prepare("
                SELECT *
                FROM attempts
                WHERE id_attempts = :id
                  AND users_id = :uid
                LIMIT 1
                FOR UPDATE
            ");
            $stmt->execute([':id' => $attemptId, ':uid' => $userId]);
            $attempt = $stmt->fetch();

            if (!$attempt) {
                $this->db->rollBack();
                return ['error' => [404, 'ATTEMPT_NOT_FOUND', 'ไม่พบข้อมูลรอบการสอบ']];
            }

            if ($attempt['status'] !== 'in_progress') {
                $this->db->rollBack();
                return ['error' => [409, 'ATTEMPT_ALREADY_SUBMITTED', 'รอบการสอบนี้ถูกส่งและล็อกสถานะเรียบร้อยแล้ว']];
            }

            // ตั้งค่าตัวแปร Session MySQL เพื่อให้ Trigger trg_attempt_answers_score_history บันทึก score_versions ได้
            $this->db->exec("SET @current_user_id = " . (int)$userId . ", @score_change_reason = 'Auto-graded on submit'");

            $examId = (int)$attempt['exams_id'];

            // ดึงข้อสอบทั้งหมดในชุดสอบ
            $stmtEq = $this->db->prepare("
                SELECT eq.id_exam_questions, eq.question_version_id, eq.score, q.type
                FROM exam_questions eq
                JOIN questions q ON q.id_questions = eq.questions_id
                WHERE eq.exams_id = :eid
            ");
            $stmtEq->execute([':eid' => $examId]);
            $examQuestions = [];
            $maxScore = 0.0;
            foreach ($stmtEq->fetchAll() as $row) {
                $examQuestions[(int)$row['id_exam_questions']] = $row;
                $maxScore += (float)$row['score'];
            }
            $maxScore = round($maxScore, 2);

            // ดึงคำตอบทั้งหมดที่นิสิตบันทึกไว้
            $stmtAns = $this->db->prepare("
                SELECT *
                FROM attempt_answers
                WHERE attempts_id = :aid
            ");
            $stmtAns->execute([':aid' => $attemptId]);
            $answers = $stmtAns->fetchAll();

            $stmtCorrectOpts = $this->db->prepare("
                SELECT id_question_options
                FROM question_options
                WHERE question_versions_id = :vid
                  AND is_correct = 1
                ORDER BY id_question_options ASC
            ");
            $stmtSelectedOpts = $this->db->prepare("
                SELECT question_options_id
                FROM attempt_answer_options
                WHERE attempt_answers_id = :aaid
                ORDER BY question_options_id ASC
            ");
            $stmtGrade = $this->db->prepare("
                UPDATE attempt_answers
                SET is_correct = :is_correct,
                    score = :score
                WHERE id_attempt_answers = :aaid
            ");

            $totalScore = 0.0;
            $gradedItems = [];

            foreach ($answers as $ans) {
                $aaId = (int)$ans['id_attempt_answers'];
                $eqId = (int)$ans['exam_question_id'];
                $vid  = (int)$ans['question_version_id'];
                $eq   = $examQuestions[$eqId] ?? null;

                $stmtSelectedOpts->execute([':aaid' => $aaId]);
                $selected = array_map('intval', $stmtSelectedOpts->fetchAll(PDO::FETCH_COLUMN));

                $isCorrect = null;
                $earnedScore = null;

                if ($eq && in_array($eq['type'], self::OBJECTIVE_TYPES, true)) {
                    $stmtCorrectOpts->execute([':vid' => $vid]);
                    $correct = array_map('intval', $stmtCorrectOpts->fetchAll(PDO::FETCH_COLUMN));

                    $matched = ($correct !== [] && $selected === $correct);
                    $isCorrect = $matched ? 1 : 0;
                    $earnedScore = $matched ? round((float)$eq['score'], 2) : 0.00;

                    $stmtGrade->execute([
                        ':is_correct' => $isCorrect,
                        ':score'      => $earnedScore,
                        ':aaid'       => $aaId,
                    ]);
                    $totalScore += $earnedScore;
                }

                // บันทึก submit_snapshot และ submit event
                $stmtDraftNo = $this->db->prepare("
                    SELECT COALESCE(MAX(draft_no), 0) + 1
                    FROM attempt_answer_drafts
                    WHERE attempt_answers_id = :aaid
                ");
                $stmtDraftNo->execute([':aaid' => $aaId]);
                $draftNo = (int)$stmtDraftNo->fetchColumn();

                $this->db->prepare("
                    INSERT INTO attempt_answer_drafts (
                        attempt_answers_id, attempts_id, draft_no, answer_text, saved_by_action, saved_at
                    ) VALUES (
                        :aaid, :aid, :draft_no, :answer_text, 'submit_snapshot', NOW()
                    )
                ")->execute([
                    ':aaid'        => $aaId,
                    ':aid'         => $attemptId,
                    ':draft_no'    => $draftNo,
                    ':answer_text' => $ans['answer_text'],
                ]);

                $this->db->prepare("
                    INSERT INTO attempt_answer_events (
                        attempt_answers_id, attempts_id, question_version_id,
                        event_type, answer_text_snapshot, selected_options_snapshot,
                        ip_address, user_agent, event_at
                    ) VALUES (
                        :aaid, :aid, :vid,
                        'submit', :text_snap, :opts_snap,
                        :ip, :ua, NOW()
                    )
                ")->execute([
                    ':aaid'      => $aaId,
                    ':aid'       => $attemptId,
                    ':vid'       => $vid,
                    ':text_snap' => $ans['answer_text'],
                    ':opts_snap' => json_encode($selected),
                    ':ip'        => $ipAddress,
                    ':ua'        => $userAgent,
                ]);

                $gradedItems[] = [
                    'exam_question_id' => $eqId,
                    'is_correct'       => $isCorrect,
                    'score'            => $earnedScore,
                ];
            }

            $totalScore = round($totalScore, 2);

            // ล็อกสถานะ attempt เป็น submitted พร้อมบันทึกเวลาส่งและคะแนนรวม
            $this->db->prepare("
                UPDATE attempts
                SET status = 'submitted',
                    submitted_at = NOW(),
                    score = :score,
                    max_score = :max_score
                WHERE id_attempts = :id
            ")->execute([
                ':id'        => $attemptId,
                ':score'     => $totalScore,
                ':max_score' => $maxScore,
            ]);

            $this->db->commit();

            return [
                'data' => [
                    'attempt'      => $this->findAttemptForUser($attemptId, $userId),
                    'total_score'  => $totalScore,
                    'max_score'    => $maxScore,
                    'graded_items' => $gradedItems,
                ],
            ];
        } catch (Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $e;
        }
    }

    private function findValidAccessCode(int $examId, int $sectionId, ?string $rawCode, string $nowStr): ?array
    {
        $code = strtoupper(trim((string)$rawCode));
        if ($code === '') {
            return null;
        }

        $stmt = $this->db->prepare("
            SELECT *
            FROM exam_access_codes
            WHERE exams_id = :eid
              AND access_code = :code
              AND status = 'active'
              AND valid_from <= :now_from
              AND valid_until >= :now_until
              AND (sections_id IS NULL OR sections_id = :sid)
            ORDER BY id_exam_access_codes DESC
            LIMIT 1
        ");
        $stmt->execute([
            ':eid'       => $examId,
            ':code'      => $code,
            ':now_from'  => $nowStr,
            ':now_until' => $nowStr,
            ':sid'       => $sectionId,
        ]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    private function logAccessCode(
        int $attemptId,
        int $examId,
        int $codeId,
        string $actionType,
        ?string $ipAddress,
        ?string $userAgent
    ): void {
        $this->db->prepare("
            INSERT INTO attempt_access_logs (
                attempts_id, exams_id, exam_access_codes_id, action_type, verified_at, ip_address, user_agent
            ) VALUES (
                :aid, :eid, :cid, :action_type, NOW(), :ip, :ua
            )
        ")->execute([
            ':aid'         => $attemptId,
            ':eid'         => $examId,
            ':cid'         => $codeId,
            ':action_type' => $actionType,
            ':ip'          => $ipAddress,
            ':ua'          => $userAgent,
        ]);
    }
}