<?php

declare(strict_types=1);

final class ReportStatisticsModel
{
    private PDO $db;

    public const ALLOWED_SELECTION_MODES = ['first', 'latest', 'highest_score', 'all'];
    public const ALLOWED_R_METHODS = ['upper_lower', 'point_biserial', 'both'];

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

    public function findCourseById(int $courseId): ?array
    {
        $stmt = $this->db->prepare("
            SELECT id_courses, code, name
            FROM courses
            WHERE id_courses = :id
              AND deleted_at IS NULL
            LIMIT 1
        ");
        $stmt->execute([':id' => $courseId]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    /**
     * [Task 46 & 49] รายงานคะแนนรายบุคคลต่อชุดสอบ พร้อมสรุปจำนวนผู้เข้าสอบ/ยังไม่เข้าสอบ และเฉลี่ย/สูงสุด/ต่ำสุด
     */
    public function getExamScoreReport(int $examId, ?int $sectionId = null, ?string $statusFilter = null): array
    {
        $exam = $this->findExamById($examId);
        if (!$exam) {
            return ['error' => [404, 'EXAM_NOT_FOUND', 'ไม่พบข้อมูลชุดข้อสอบ']];
        }

        $sql = "
            SELECT
                ee.id_exam_eligibilities,
                ee.users_id,
                ee.sections_id,
                s.section_no,
                u.student_code,
                u.display_name,
                u.email,
                ee.status AS eligibility_status,
                att.id_attempts,
                att.attempt_no,
                att.status AS attempt_status,
                att.score,
                COALESCE(att.max_score, eq_max.max_score, 0) AS max_score,
                att.submitted_at
            FROM exam_eligibilities ee
            JOIN users u ON u.id_users = ee.users_id
            JOIN sections s ON s.id_sections = ee.sections_id
            LEFT JOIN (
                SELECT eq.exams_id, COALESCE(SUM(eq.score), 0) AS max_score
                FROM exam_questions eq
                WHERE eq.exams_id = :eq_eid
                GROUP BY eq.exams_id
            ) eq_max ON eq_max.exams_id = ee.exams_id
            LEFT JOIN attempts att ON att.id_attempts = (
                SELECT a2.id_attempts
                FROM attempts a2
                WHERE a2.exams_id = ee.exams_id
                  AND a2.users_id = ee.users_id
                ORDER BY (a2.status IN ('submitted', 'auto_submitted')) DESC,
                         COALESCE(a2.score, -1) DESC,
                         a2.attempt_no DESC
                LIMIT 1
            )
            WHERE ee.exams_id = :eid
        ";
        $params = [
            ':eid'    => $examId,
            ':eq_eid' => $examId,
        ];

        if ($sectionId !== null) {
            $sql .= " AND ee.sections_id = :sid";
            $params[':sid'] = $sectionId;
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $allRows = $stmt->fetchAll();

        $submittedScores = [];
        $notTakenCount   = 0;
        $inProgressCount = 0;

        foreach ($allRows as &$r) {
            $attStatus = $r['attempt_status'] ?? null;
            if ($attStatus === 'submitted' || $attStatus === 'auto_submitted') {
                $r['participation_status'] = 'submitted';
                $scoreVal = $r['score'] !== null ? round((float)$r['score'], 2) : 0.0;
                $maxVal   = round((float)$r['max_score'], 2);
                $r['score']     = $scoreVal;
                $r['max_score'] = $maxVal;
                $r['percent']   = $maxVal > 0 ? round(($scoreVal / $maxVal) * 100, 2) : 0.0;
                $submittedScores[] = $scoreVal;
            } elseif ($attStatus === 'in_progress') {
                $r['participation_status'] = 'in_progress';
                $r['percent'] = null;
                $inProgressCount++;
            } else {
                $r['participation_status'] = 'not_taken';
                $r['percent'] = null;
                $notTakenCount++;
            }
        }
        unset($r);

        $rows = $allRows;
        if ($statusFilter !== null && $statusFilter !== '') {
            $rows = array_values(array_filter(
                $allRows,
                fn($row) => $row['participation_status'] === $statusFilter
            ));
        }

        $submittedCount = count($submittedScores);
        $meanScore = $submittedCount > 0 ? round(array_sum($submittedScores) / $submittedCount, 2) : null;
        $maxScore  = $submittedCount > 0 ? round(max($submittedScores), 2) : null;
        $minScore  = $submittedCount > 0 ? round(min($submittedScores), 2) : null;

        return [
            'data' => [
                'exam' => [
                    'id_exams'            => (int)$exam['id_exams'],
                    'courses_id'          => (int)$exam['courses_id'],
                    'course_offerings_id' => (int)$exam['course_offerings_id'],
                    'title'               => $exam['title'],
                    'kind'                => $exam['kind'],
                    'status'              => $exam['status'],
                ],
                'summary' => [
                    'eligible_count'    => count($allRows),
                    'submitted_count'   => $submittedCount,
                    'in_progress_count' => $inProgressCount,
                    'not_taken_count'   => $notTakenCount,
                    'mean_score'        => $meanScore,
                    'max_score_val'     => $maxScore,
                    'min_score_val'     => $minScore,
                ],
                'students' => $rows,
            ],
        ];
    }

    /**
     * [Task 46] รายงานคะแนนรายวิชา รวมชุดข้อสอบและสรุปคะแนนของนิสิตในวิชา
     */
    public function getCourseScoreReport(int $courseId): array
    {
        $course = $this->findCourseById($courseId);
        if (!$course) {
            return ['error' => [404, 'COURSE_NOT_FOUND', 'ไม่พบข้อมูลรายวิชา']];
        }

        $stmtExams = $this->db->prepare("
            SELECT
                e.id_exams,
                e.course_offerings_id,
                e.kind,
                e.title,
                e.status,
                COUNT(DISTINCT CASE WHEN a.status IN ('submitted', 'auto_submitted') THEN a.id_attempts END) AS submitted_attempts,
                AVG(CASE WHEN a.status IN ('submitted', 'auto_submitted') THEN a.score END) AS mean_score,
                MAX(CASE WHEN a.status IN ('submitted', 'auto_submitted') THEN a.score END) AS max_score_val,
                MIN(CASE WHEN a.status IN ('submitted', 'auto_submitted') THEN a.score END) AS min_score_val
            FROM exams e
            LEFT JOIN attempts a ON a.exams_id = e.id_exams
            WHERE e.courses_id = :cid
              AND e.deleted_at IS NULL
            GROUP BY e.id_exams
            ORDER BY e.id_exams ASC
        ");
        $stmtExams->execute([':cid' => $courseId]);
        $exams = $stmtExams->fetchAll();

        foreach ($exams as &$ex) {
            $ex['submitted_attempts'] = (int)$ex['submitted_attempts'];
            $ex['mean_score']         = $ex['mean_score'] !== null ? round((float)$ex['mean_score'], 2) : null;
            $ex['max_score_val']      = $ex['max_score_val'] !== null ? round((float)$ex['max_score_val'], 2) : null;
            $ex['min_score_val']      = $ex['min_score_val'] !== null ? round((float)$ex['min_score_val'], 2) : null;
        }
        unset($ex);

        $stmtStudents = $this->db->prepare("
            SELECT
                u.id_users AS users_id,
                u.student_code,
                u.display_name,
                u.email,
                COUNT(CASE WHEN a.status IN ('submitted', 'auto_submitted') THEN a.id_attempts END) AS completed_exams,
                COALESCE(SUM(CASE WHEN a.status IN ('submitted', 'auto_submitted') THEN a.score ELSE 0 END), 0) AS total_earned_score,
                COALESCE(SUM(CASE WHEN a.status IN ('submitted', 'auto_submitted') THEN COALESCE(a.max_score, 0) ELSE 0 END), 0) AS total_max_score
            FROM users u
            JOIN course_members cm ON cm.user_id = u.id_users
            JOIN course_offerings co ON co.id_course_offerings = cm.course_offerings_id
            LEFT JOIN attempts a
              ON a.users_id = u.id_users
             AND a.course_offerings_id = co.id_course_offerings
            WHERE co.courses_id = :cid
              AND cm.enrollment_status = 'enrolled'
              AND u.deleted_at IS NULL
            GROUP BY u.id_users
            ORDER BY u.student_code ASC, u.id_users ASC
        ");
        $stmtStudents->execute([':cid' => $courseId]);
        $students = $stmtStudents->fetchAll();

        foreach ($students as &$st) {
            $st['completed_exams']    = (int)$st['completed_exams'];
            $st['total_earned_score'] = round((float)$st['total_earned_score'], 2);
            $st['total_max_score']    = round((float)$st['total_max_score'], 2);
        }
        unset($st);

        return [
            'data' => [
                'course'   => $course,
                'exams'    => $exams,
                'students' => $students,
            ],
        ];
    }

    /**
     * [Task 47] คำนวณสถิติภาพรวมและรายข้อ เก็บเป็น run และตั้ง is_current = 1
     */
    public function calculateAndStoreStatistics(
        int $examId,
        ?int $sectionId,
        string $selectionMode,
        string $rMethod,
        ?float $passPercent,
        int $calculatedBy
    ): array {
        $exam = $this->findExamById($examId);
        if (!$exam) {
            return ['error' => [404, 'EXAM_NOT_FOUND', 'ไม่พบข้อมูลชุดข้อสอบ']];
        }

        $courseId = (int)$exam['courses_id'];
        $scopeKey = $sectionId ?? 0;

        $selectedAttempts = $this->selectAttemptsForRun($examId, $sectionId, $selectionMode);
        $sampleSize = count($selectedAttempts);

        $stmtEq = $this->db->prepare("
            SELECT
                eq.id_exam_questions,
                eq.question_version_id,
                eq.score AS item_max_score,
                qv.bloom_levels_code
            FROM exam_questions eq
            JOIN question_versions qv ON qv.id_question_versions = eq.question_version_id
            WHERE eq.exams_id = :eid
            ORDER BY eq.sort_order ASC, eq.id_exam_questions ASC
        ");
        $stmtEq->execute([':eid' => $examId]);
        $examQuestions = $stmtEq->fetchAll();

        $scores = array_map(fn($a) => (float)($a['score'] ?? 0), $selectedAttempts);
        $maxScoreVal = $sampleSize > 0 ? round(max($scores), 2) : null;
        $minScoreVal = $sampleSize > 0 ? round(min($scores), 2) : null;
        $meanScore   = $sampleSize > 0 ? round(array_sum($scores) / $sampleSize, 4) : null;
        $sdScore     = $sampleSize > 1 ? $this->calculateSampleSd($scores, (float)$meanScore) : ($sampleSize === 1 ? 0.0 : null);

        // คำนวณสถิติรายข้อ (p_value, discrimination, delta)
        $itemStatsPayload = [];
        $itemBinaryMatrix = []; // สำหรับ KR-20
        $itemScoreMatrix  = []; // สำหรับ Cronbach Alpha

        // จัดกลุ่มผู้สอบสูง/ต่ำ (27% บน-ล่าง)
        $sortedAttempts = $selectedAttempts;
        usort($sortedAttempts, fn($a, $b) => ((float)$b['score']) <=> ((float)$a['score']));
        $groupN = $sampleSize >= 2 ? max(1, (int)floor($sampleSize * 0.27)) : 0;
        $highAttemptIds = $groupN > 0 ? array_map(fn($a) => (int)$a['id_attempts'], array_slice($sortedAttempts, 0, $groupN)) : [];
        $lowAttemptIds  = $groupN > 0 ? array_map(fn($a) => (int)$a['id_attempts'], array_slice($sortedAttempts, -$groupN)) : [];

        $stmtAnsByEq = $this->db->prepare("
            SELECT attempts_id, is_correct, score
            FROM attempt_answers
            WHERE exam_question_id = :eq_id
              AND question_version_id = :vid
        ");

        foreach ($examQuestions as $eq) {
            $eqId   = (int)$eq['id_exam_questions'];
            $vid    = (int)$eq['question_version_id'];
            $eqMax  = (float)$eq['item_max_score'];
            $bloom  = (string)$eq['bloom_levels_code'];

            $stmtAnsByEq->execute([':eq_id' => $eqId, ':vid' => $vid]);
            $ansMap = [];
            foreach ($stmtAnsByEq->fetchAll() as $aRow) {
                $ansMap[(int)$aRow['attempts_id']] = $aRow;
            }

            $correctCount  = 0;
            $wrongCount    = 0;
            $answeredCount = 0;
            $omittedCount  = 0;
            $highCorrect   = 0;
            $lowCorrect    = 0;
            $binaryVec     = [];
            $scoreVec      = [];
            $pbCorrectScores = [];
            $pbWrongScores   = [];

            foreach ($selectedAttempts as $att) {
                $attId = (int)$att['id_attempts'];
                $attTotal = (float)($att['score'] ?? 0);
                $ans = $ansMap[$attId] ?? null;

                $isRight = false;
                $itemEarned = 0.0;

                if ($ans !== null) {
                    $answeredCount++;
                    $itemEarned = (float)($ans['score'] ?? 0);
                    if ($ans['is_correct'] !== null) {
                        $isRight = ((int)$ans['is_correct'] === 1);
                    } elseif ($eqMax > 0 && $itemEarned >= $eqMax) {
                        $isRight = true;
                    }
                } else {
                    $omittedCount++;
                }

                if ($isRight) {
                    $correctCount++;
                    $pbCorrectScores[] = $attTotal;
                } else {
                    $wrongCount++;
                    $pbWrongScores[] = $attTotal;
                }

                if (in_array($attId, $highAttemptIds, true) && $isRight) {
                    $highCorrect++;
                }
                if (in_array($attId, $lowAttemptIds, true) && $isRight) {
                    $lowCorrect++;
                }

                $binaryVec[] = $isRight ? 1 : 0;
                $scoreVec[]  = $itemEarned;
            }

            $pValue = $sampleSize > 0 ? round($correctCount / $sampleSize, 4) : null;
            $qValue = $pValue !== null ? round(1.0 - $pValue, 4) : null;

            $rUpperLower = ($groupN > 0)
                ? round(($highCorrect - $lowCorrect) / $groupN, 4)
                : null;

            $rPointBiserial = $this->calculatePointBiserial($pbCorrectScores, $pbWrongScores, $scores, $sdScore);

            $rPrimary = match ($rMethod) {
                'upper_lower'    => $rUpperLower,
                'point_biserial' => $rPointBiserial,
                default          => $rUpperLower ?? $rPointBiserial,
            };

            $deltaValue = ($pValue !== null && $pValue > 0 && $pValue < 1)
                ? round(13.0 + 4.0 * $this->probitApprox(1.0 - $pValue), 2)
                : null;

            $itemStatsPayload[] = [
                'exam_question_id'   => $eqId,
                'question_version_id'=> $vid,
                'bloom_levels_code'  => $bloom,
                'correct_count'      => $correctCount,
                'wrong_count'        => $wrongCount,
                'answered_count'     => $answeredCount,
                'omitted_count'      => $omittedCount,
                'high_group_n'       => $groupN,
                'low_group_n'        => $groupN,
                'p_value'            => $pValue,
                'q_value'            => $qValue,
                'r_value'            => $rPrimary,
                'r_upper_lower'      => $rUpperLower,
                'r_point_biserial'   => $rPointBiserial,
                'delta_value'        => $deltaValue,
            ];

            $itemBinaryMatrix[] = $binaryVec;
            $itemScoreMatrix[]  = $scoreVec;
        }

        $kr20 = $this->calculateKr20($itemBinaryMatrix, $sampleSize);
        $cronbachAlpha = $this->calculateCronbachAlpha($itemScoreMatrix, $sampleSize);

        $this->db->beginTransaction();
        try {
            $stmtNextRun = $this->db->prepare("
                SELECT COALESCE(MAX(run_no), 0) + 1
                FROM exam_statistics
                WHERE exams_id = :eid
                  AND section_scope_key = :scope_key
            ");
            $stmtNextRun->execute([':eid' => $examId, ':scope_key' => $scopeKey]);
            $nextRunNo = (int)$stmtNextRun->fetchColumn();

            // ปลด is_current เดิมในขอบเขตเดียวกันก่อน (เพื่อให้ผ่าน Unique Index uq_stats_current_run)
            $this->db->prepare("
                UPDATE exam_statistics
                SET is_current = 0
                WHERE exams_id = :eid
                  AND section_scope_key = :scope_key
                  AND is_current = 1
            ")->execute([':eid' => $examId, ':scope_key' => $scopeKey]);

            $nowStr = date('Y-m-d H:i:s');
            $stmtIns = $this->db->prepare("
                INSERT INTO exam_statistics (
                    exams_id, courses_id, sections_id, section_scope_key,
                    run_no, calculation_status, calculation_type,
                    attempt_selection_mode, r_method, formula_version,
                    sample_size, max_score_val, min_score_val, mean_score, sd_score,
                    reliability_kr20, reliability_cronbach_alpha, pass_percent,
                    is_current, calculated_by, calculation_started_at,
                    completed_at, calculated_at, created_at
                ) VALUES (
                    :eid, :cid, :sid, :scope_key,
                    :run_no, 'completed', :calc_type,
                    :sel_mode, :r_method, 'v1',
                    :sample_size, :max_val, :min_val, :mean_val, :sd_val,
                    :kr20, :alpha, :pass_pct,
                    1, :calc_by, :started_at,
                    :completed_at, :calculated_at, :created_at
                )
            ");
            $stmtIns->execute([
                ':eid'           => $examId,
                ':cid'           => $courseId,
                ':sid'           => $sectionId,
                ':scope_key'     => $scopeKey,
                ':run_no'        => $nextRunNo,
                ':calc_type'     => $nextRunNo === 1 ? 'initial' : 'recalculation',
                ':sel_mode'      => $selectionMode,
                ':r_method'      => $rMethod,
                ':sample_size'   => $sampleSize,
                ':max_val'       => $maxScoreVal,
                ':min_val'       => $minScoreVal,
                ':mean_val'      => $meanScore,
                ':sd_val'        => $sdScore,
                ':kr20'          => $kr20,
                ':alpha'         => $cronbachAlpha,
                ':pass_pct'      => $passPercent,
                ':calc_by'       => $calculatedBy,
                ':started_at'    => $nowStr,
                ':completed_at'  => $nowStr,
                ':calculated_at' => $nowStr,
                ':created_at'    => $nowStr,
            ]);

            $statId = (int)$this->db->lastInsertId();

            $stmtInsAtt = $this->db->prepare("
                INSERT IGNORE INTO exam_statistics_attempts (
                    exam_statistics_id, exams_id, courses_id, attempts_id, users_id, selected_at
                ) VALUES (:stat_id, :eid, :cid, :att_id, :uid, NOW())
            ");
            foreach ($selectedAttempts as $att) {
                $stmtInsAtt->execute([
                    ':stat_id' => $statId,
                    ':eid'     => $examId,
                    ':cid'     => $courseId,
                    ':att_id'  => (int)$att['id_attempts'],
                    ':uid'     => (int)$att['users_id'],
                ]);
            }

            $stmtInsItem = $this->db->prepare("
                INSERT INTO exam_item_statistics (
                    exam_statistics_id, exams_id, exam_question_id, question_version_id,
                    bloom_levels_code, correct_count, wrong_count, answered_count, omitted_count,
                    high_group_n, low_group_n, p_value, q_value, r_value,
                    r_upper_lower, r_point_biserial, delta_value
                ) VALUES (
                    :stat_id, :eid, :eq_id, :vid,
                    :bloom, :correct_c, :wrong_c, :ans_c, :omit_c,
                    :high_n, :low_n, :p_val, :q_val, :r_val,
                    :r_ul, :r_pb, :delta_val
                )
            ");

            foreach ($itemStatsPayload as $item) {
                $stmtInsItem->execute([
                    ':stat_id'   => $statId,
                    ':eid'       => $examId,
                    ':eq_id'     => $item['exam_question_id'],
                    ':vid'       => $item['question_version_id'],
                    ':bloom'     => $item['bloom_levels_code'],
                    ':correct_c' => $item['correct_count'],
                    ':wrong_c'   => $item['wrong_count'],
                    ':ans_c'     => $item['answered_count'],
                    ':omit_c'    => $item['omitted_count'],
                    ':high_n'    => $item['high_group_n'],
                    ':low_n'     => $item['low_group_n'],
                    ':p_val'     => $item['p_value'],
                    ':q_val'     => $item['q_value'],
                    ':r_val'     => $item['r_value'],
                    ':r_ul'      => $item['r_upper_lower'],
                    ':r_pb'      => $item['r_point_biserial'],
                    ':delta_val' => $item['delta_value'],
                ]);
            }

            $this->db->commit();

            return [
                'data' => $this->getStatisticsBundle($examId, $statId),
            ];
        } catch (Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $e;
        }
    }

    /**
     * [Task 47] ดึงรายการรอบ run และรายละเอียดสถิติ (default คือ run ที่มี is_current = 1)
     */
    public function getStatisticsBundle(int $examId, ?int $specificStatId = null): array
    {
        $stmtRuns = $this->db->prepare("
            SELECT
                id_exam_statistics,
                exams_id,
                courses_id,
                sections_id,
                run_no,
                attempt_selection_mode,
                r_method,
                sample_size,
                max_score_val,
                min_score_val,
                mean_score,
                sd_score,
                reliability_kr20,
                reliability_cronbach_alpha,
                is_current,
                calculated_at
            FROM exam_statistics
            WHERE exams_id = :eid
            ORDER BY run_no DESC, id_exam_statistics DESC
        ");
        $stmtRuns->execute([':eid' => $examId]);
        $runs = $stmtRuns->fetchAll();

        $targetStat = null;
        if ($specificStatId !== null) {
            foreach ($runs as $r) {
                if ((int)$r['id_exam_statistics'] === $specificStatId) {
                    $targetStat = $r;
                    break;
                }
            }
        }
        if ($targetStat === null && $runs !== []) {
            foreach ($runs as $r) {
                if ((int)$r['is_current'] === 1) {
                    $targetStat = $r;
                    break;
                }
            }
            if ($targetStat === null) {
                $targetStat = $runs[0];
            }
        }

        $itemStats = [];
        if ($targetStat !== null) {
            $stmtItems = $this->db->prepare("
                SELECT
                    eis.*,
                    eq.sort_order,
                    eq.score AS item_max_score,
                    q.text AS question_text,
                    q.type AS question_type
                FROM exam_item_statistics eis
                JOIN exam_questions eq ON eq.id_exam_questions = eis.exam_question_id
                JOIN questions q ON q.id_questions = eq.questions_id
                WHERE eis.exam_statistics_id = :stat_id
                ORDER BY eq.sort_order ASC, eis.id_exam_item_statistics ASC
            ");
            $stmtItems->execute([':stat_id' => (int)$targetStat['id_exam_statistics']]);
            $itemStats = $stmtItems->fetchAll();
        }

        return [
            'exams_id'        => $examId,
            'runs'            => $runs,
            'current'         => $targetStat,
            'statistics'      => $targetStat,
            'item_statistics' => $itemStats,
        ];
    }

    /**
     * [Task 47] ตั้งรอบการคำนวณที่เลือกให้เป็น is_current = 1 ในขอบเขตเดียวกัน
     */
    public function setCurrentRun(int $statId): array
    {
        $stmt = $this->db->prepare("SELECT * FROM exam_statistics WHERE id_exam_statistics = :id LIMIT 1");
        $stmt->execute([':id' => $statId]);
        $stat = $stmt->fetch();
        if (!$stat) {
            return ['error' => [404, 'STATISTICS_NOT_FOUND', 'ไม่พบรอบการคำนวณสถิติที่ระบุ']];
        }

        $this->db->beginTransaction();
        try {
            $this->db->prepare("
                UPDATE exam_statistics
                SET is_current = 0
                WHERE exams_id = :eid
                  AND section_scope_key = :scope_key
                  AND is_current = 1
            ")->execute([
                ':eid'       => (int)$stat['exams_id'],
                ':scope_key' => (int)$stat['section_scope_key'],
            ]);

            $this->db->prepare("
                UPDATE exam_statistics
                SET is_current = 1
                WHERE id_exam_statistics = :id
            ")->execute([':id' => $statId]);

            $this->db->commit();

            return ['data' => $this->getStatisticsBundle((int)$stat['exams_id'], $statId)];
        } catch (Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $e;
        }
    }

    private function selectAttemptsForRun(int $examId, ?int $sectionId, string $mode): array
    {
        $sql = "
            SELECT *
            FROM attempts
            WHERE exams_id = :eid
              AND status IN ('submitted', 'auto_submitted')
        ";
        $params = [':eid' => $examId];
        if ($sectionId !== null) {
            $sql .= " AND sections_id = :sid";
            $params[':sid'] = $sectionId;
        }
        $sql .= " ORDER BY users_id ASC, COALESCE(score, 0) DESC, attempt_no DESC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll();

        if ($mode === 'all') {
            return $rows;
        }

        $byUser = [];
        foreach ($rows as $row) {
            $uid = (int)$row['users_id'];
            $byUser[$uid][] = $row;
        }

        $picked = [];
        foreach ($byUser as $userAttempts) {
            usort($userAttempts, fn($a, $b) => ((int)$a['attempt_no']) <=> ((int)$b['attempt_no']));
            if ($mode === 'first') {
                $picked[] = $userAttempts[0];
            } elseif ($mode === 'latest') {
                $picked[] = $userAttempts[count($userAttempts) - 1];
            } else {
                // highest_score
                usort($userAttempts, fn($a, $b) => ((float)($b['score'] ?? 0)) <=> ((float)($a['score'] ?? 0)));
                $picked[] = $userAttempts[0];
            }
        }

        return $picked;
    }

    private function calculateSampleSd(array $values, float $mean): float
    {
        $n = count($values);
        if ($n <= 1) {
            return 0.0;
        }
        $sumSq = 0.0;
        foreach ($values as $v) {
            $diff = (float)$v - $mean;
            $sumSq += ($diff * $diff);
        }
        return round(sqrt($sumSq / ($n - 1)), 4);
    }

    private function calculatePointBiserial(array $correctScores, array $wrongScores, array $allScores, ?float $sdTotal): ?float
    {
        $n1 = count($correctScores);
        $n0 = count($wrongScores);
        $n  = count($allScores);
        if ($n1 === 0 || $n0 === 0 || $n <= 1 || $sdTotal === null || $sdTotal <= 0.0) {
            return null;
        }
        $mean1 = array_sum($correctScores) / $n1;
        $mean0 = array_sum($wrongScores) / $n0;
        $rpb = (($mean1 - $mean0) / $sdTotal) * sqrt(($n1 * $n0) / ($n * $n));
        return round(max(-1.0, min(1.0, $rpb)), 4);
    }

    private function calculateKr20(array $binaryMatrix, int $n): ?float
    {
        $k = count($binaryMatrix);
        if ($k < 2 || $n < 2) {
            return null;
        }
        $sumPq = 0.0;
        $studentTotals = array_fill(0, $n, 0.0);
        foreach ($binaryMatrix as $vec) {
            $p = array_sum($vec) / $n;
            $q = 1.0 - $p;
            $sumPq += ($p * $q);
            for ($i = 0; $i < $n; $i++) {
                $studentTotals[$i] += (float)$vec[$i];
            }
        }
        $meanTot = array_sum($studentTotals) / $n;
        $varTot = 0.0;
        foreach ($studentTotals as $t) {
            $varTot += (($t - $meanTot) ** 2);
        }
        $varTot /= ($n - 1);
        if ($varTot <= 0.0) {
            return 0.0;
        }
        $kr20 = ($k / ($k - 1)) * (1.0 - ($sumPq / $varTot));
        return round(max(-1.0, min(1.0, $kr20)), 4);
    }

    private function calculateCronbachAlpha(array $scoreMatrix, int $n): ?float
    {
        $k = count($scoreMatrix);
        if ($k < 2 || $n < 2) {
            return null;
        }
        $sumItemVars = 0.0;
        $studentTotals = array_fill(0, $n, 0.0);
        foreach ($scoreMatrix as $vec) {
            $meanI = array_sum($vec) / $n;
            $varI = 0.0;
            for ($i = 0; $i < $n; $i++) {
                $v = (float)$vec[$i];
                $varI += (($v - $meanI) ** 2);
                $studentTotals[$i] += $v;
            }
            $sumItemVars += ($varI / ($n - 1));
        }
        $meanTot = array_sum($studentTotals) / $n;
        $varTot = 0.0;
        foreach ($studentTotals as $t) {
            $varTot += (($t - $meanTot) ** 2);
        }
        $varTot /= ($n - 1);
        if ($varTot <= 0.0) {
            return 0.0;
        }
        $alpha = ($k / ($k - 1)) * (1.0 - ($sumItemVars / $varTot));
        return round(max(-1.0, min(1.0, $alpha)), 4);
    }

    private function probitApprox(float $p): float
    {
        if ($p <= 0.0) {
            return -3.0;
        }
        if ($p >= 1.0) {
            return 3.0;
        }
        $t = sqrt(-2.0 * log($p < 0.5 ? $p : (1.0 - $p)));
        $c0 = 2.515517;
        $c1 = 0.802853;
        $c2 = 0.010328;
        $d1 = 1.432788;
        $d2 = 0.189269;
        $d3 = 0.001308;
        $z = $t - (($c0 + $c1 * $t + $c2 * $t * $t) / (1.0 + $d1 * $t + $d2 * $t * $t + $d3 * $t * $t * $t));
        return $p >= 0.5 ? $z : -$z;
    }
}