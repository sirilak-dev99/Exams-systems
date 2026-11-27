<?php

declare(strict_types=1);

final class ExamBlueprintModel
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getConnection();
    }

    public function findExamById(int $examId): ?array
    {
        $stmt = $this->db->prepare("
            SELECT id_exams, courses_id, course_offerings_id, title, mode, status
            FROM exams
            WHERE id_exams = :id
              AND deleted_at IS NULL
            LIMIT 1
        ");
        $stmt->execute([':id' => $examId]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    public function findBlueprintByExam(int $examId): ?array
    {
        $stmt = $this->db->prepare("
            SELECT *
            FROM exam_blueprints
            WHERE exams_id = :eid
            ORDER BY id_exam_blueprints DESC
            LIMIT 1
        ");
        $stmt->execute([':eid' => $examId]);
        $bp = $stmt->fetch();
        if (!$bp) {
            return null;
        }

        $rules = $this->getRulesByBlueprint((int)$bp['id_exam_blueprints']);
        $expectedQuestions = 0;
        $expectedScore = 0.0;
        foreach ($rules as $r) {
            $expectedQuestions += (int)$r['question_count'];
            $expectedScore += ((int)$r['question_count'] * (float)$r['score_per_question']);
        }

        return [
            'blueprint'                => $bp,
            'rules'                    => $rules,
            'expected_total_questions' => $expectedQuestions,
            'expected_total_score'     => round($expectedScore, 2),
        ];
    }

    public function getRulesByBlueprint(int $blueprintId): array
    {
        $stmt = $this->db->prepare("
            SELECT
                id_exam_blueprint_rules,
                exam_blueprints_id,
                chapters_id,
                main_concepts_id,
                sub_concepts_id,
                bloom_levels_code,
                question_type,
                scenario_id,
                question_count,
                score_per_question,
                sort_order
            FROM exam_blueprint_rules
            WHERE exam_blueprints_id = :bpid
            ORDER BY sort_order ASC, id_exam_blueprint_rules ASC
        ");
        $stmt->execute([':bpid' => $blueprintId]);

        return $stmt->fetchAll();
    }

    public function saveBlueprintWithRules(int $examId, string $title, array $rules, int $userId): int
    {
        $totalQuestions = 0;
        $totalScore = 0.0;
        foreach ($rules as $rule) {
            $totalQuestions += (int)$rule['question_count'];
            $totalScore += ((int)$rule['question_count'] * (float)$rule['score_per_question']);
        }
        $totalScore = round($totalScore, 2);

        $this->db->beginTransaction();
        try {
            $existing = $this->findBlueprintByExam($examId);
            if ($existing) {
                $blueprintId = (int)$existing['blueprint']['id_exam_blueprints'];
                $this->db->prepare("
                    UPDATE exam_blueprints
                    SET title = :title,
                        total_questions = :total_questions,
                        total_score = :total_score,
                        updated_at = NOW()
                    WHERE id_exam_blueprints = :id
                ")->execute([
                    ':id'              => $blueprintId,
                    ':title'           => $title,
                    ':total_questions' => $totalQuestions,
                    ':total_score'     => $totalScore,
                ]);

                $this->db->prepare("DELETE FROM exam_blueprint_rules WHERE exam_blueprints_id = :bpid")
                    ->execute([':bpid' => $blueprintId]);
            } else {
                $stmt = $this->db->prepare("
                    INSERT INTO exam_blueprints (
                        exams_id, title, total_questions, total_score, created_by, created_at, updated_at
                    ) VALUES (
                        :exams_id, :title, :total_questions, :total_score, :created_by, NOW(), NOW()
                    )
                ");
                $stmt->execute([
                    ':exams_id'        => $examId,
                    ':title'           => $title,
                    ':total_questions' => $totalQuestions,
                    ':total_score'     => $totalScore,
                    ':created_by'      => $userId,
                ]);
                $blueprintId = (int)$this->db->lastInsertId();
            }

            $insRule = $this->db->prepare("
                INSERT INTO exam_blueprint_rules (
                    exam_blueprints_id, chapters_id, main_concepts_id, sub_concepts_id,
                    bloom_levels_code, question_type, scenario_id,
                    question_count, score_per_question, sort_order
                ) VALUES (
                    :bpid, :chapters_id, :main_concepts_id, :sub_concepts_id,
                    :bloom_levels_code, :question_type, :scenario_id,
                    :question_count, :score_per_question, :sort_order
                )
            ");

            foreach ($rules as $idx => $rule) {
                $insRule->execute([
                    ':bpid'               => $blueprintId,
                    ':chapters_id'        => $rule['chapters_id'],
                    ':main_concepts_id'   => $rule['main_concepts_id'],
                    ':sub_concepts_id'    => $rule['sub_concepts_id'],
                    ':bloom_levels_code'  => $rule['bloom_levels_code'],
                    ':question_type'      => $rule['question_type'] ?? null,
                    ':scenario_id'        => $rule['scenario_id'] ?? null,
                    ':question_count'     => $rule['question_count'],
                    ':score_per_question' => $rule['score_per_question'],
                    ':sort_order'         => $rule['sort_order'] ?? ($idx + 1),
                ]);
            }

            $this->db->commit();
            return $blueprintId;
        } catch (Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $e;
        }
    }

    public function sampleQuestionsByRules(int $courseId, array $rules): array
    {
        $selected = [];
        $usedQuestionIds = [];
        $shortages = [];
        $sortOrder = 1;
        $totalScore = 0.0;

        foreach ($rules as $idx => $rule) {
            $needed = (int)$rule['question_count'];
            $scorePerQ = round((float)$rule['score_per_question'], 2);

            $candidates = $this->findEligibleLatestVersions($courseId, $rule, $usedQuestionIds);
            $availableCount = count($candidates);

            if ($availableCount < $needed) {
                $shortages[] = [
                    'rule_index'        => $idx + 1,
                    'chapters_id'       => $rule['chapters_id'],
                    'main_concepts_id'  => $rule['main_concepts_id'],
                    'sub_concepts_id'   => $rule['sub_concepts_id'],
                    'bloom_levels_code' => $rule['bloom_levels_code'],
                    'required'          => $needed,
                    'available'         => $availableCount,
                    'missing'           => $needed - $availableCount,
                ];
                continue;
            }

            shuffle($candidates);
            $picked = array_slice($candidates, 0, $needed);
            foreach ($picked as $row) {
                $qid = (int)$row['questions_id'];
                $usedQuestionIds[] = $qid;
                $selected[] = [
                    'questions_id'        => $qid,
                    'question_version_id' => (int)$row['id_question_versions'],
                    'bloom_levels_code'   => $row['bloom_levels_code'],
                    'sort_order'          => $sortOrder++,
                    'score'               => $scorePerQ,
                ];
                $totalScore += $scorePerQ;
            }
        }

        return [
            'ok'              => $shortages === [],
            'selected'        => $selected,
            'shortages'       => $shortages,
            'total_questions' => count($selected),
            'total_score'     => round($totalScore, 2),
        ];
    }

    public function applyGeneratedQuestions(int $examId, int $courseId, array $selected): void
    {
        $this->db->beginTransaction();
        try {
            $this->db->prepare("DELETE FROM exam_questions WHERE exams_id = :eid")
                ->execute([':eid' => $examId]);

            $ins = $this->db->prepare("
                INSERT INTO exam_questions (
                    exams_id, questions_id, question_version_id, courses_id, sort_order, score
                ) VALUES (
                    :eid, :qid, :vid, :cid, :sort_order, :score
                )
            ");

            $totalScore = 0.0;
            foreach ($selected as $item) {
                $ins->execute([
                    ':eid'        => $examId,
                    ':qid'        => $item['questions_id'],
                    ':vid'        => $item['question_version_id'],
                    ':cid'        => $courseId,
                    ':sort_order' => $item['sort_order'],
                    ':score'      => $item['score'],
                ]);
                $totalScore += (float)$item['score'];
            }

            $snapshot = [
                'source'          => 'blueprint',
                'questions'       => $selected,
                'total_questions' => count($selected),
                'total_score'     => round($totalScore, 2),
                'generated_at'    => date('Y-m-d H:i:s'),
            ];

            $this->db->prepare("
                UPDATE exams
                SET exam_snapshot = :snapshot,
                    updated_at = NOW()
                WHERE id_exams = :eid
            ")->execute([
                ':eid'      => $examId,
                ':snapshot' => json_encode($snapshot, JSON_UNESCAPED_UNICODE),
            ]);

            $this->db->commit();
        } catch (Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $e;
        }
    }

    private function findEligibleLatestVersions(int $courseId, array $rule, array $excludeQuestionIds): array
    {
        $sql = "
            SELECT
                q.id_questions AS questions_id,
                qv.id_question_versions,
                qv.bloom_levels_code
            FROM questions q
            JOIN (
                SELECT questions_id, MAX(id_question_versions) AS latest_vid
                FROM question_versions
                GROUP BY questions_id
            ) lv ON lv.questions_id = q.id_questions
            JOIN question_versions qv ON qv.id_question_versions = lv.latest_vid
            WHERE q.deleted_at IS NULL
              AND q.status = 'published'
              AND q.courses_id = ?
        ";
        $params = [$courseId];

        if (!empty($rule['chapters_id'])) {
            $sql .= " AND q.chapters_id = ?";
            $params[] = (int)$rule['chapters_id'];
        }
        if (!empty($rule['bloom_levels_code'])) {
            $sql .= " AND qv.bloom_levels_code = ?";
            $params[] = (string)$rule['bloom_levels_code'];
        }
        if (!empty($rule['question_type'])) {
            $sql .= " AND q.type = ?";
            $params[] = (string)$rule['question_type'];
        }
        if (!empty($rule['scenario_id'])) {
            $sql .= " AND q.scenario_id = ?";
            $params[] = (int)$rule['scenario_id'];
        }
        if (!empty($rule['main_concepts_id'])) {
            $sql .= " AND EXISTS (
                SELECT 1 FROM question_version_main_concepts qvmc
                WHERE qvmc.question_version_id = qv.id_question_versions
                  AND qvmc.main_concepts_id = ?
            )";
            $params[] = (int)$rule['main_concepts_id'];
        }
        if (!empty($rule['sub_concepts_id'])) {
            $sql .= " AND EXISTS (
                SELECT 1 FROM question_version_sub_concepts qvsc
                WHERE qvsc.question_version_id = qv.id_question_versions
                  AND qvsc.sub_concepts_id = ?
            )";
            $params[] = (int)$rule['sub_concepts_id'];
        }
        if ($excludeQuestionIds !== []) {
            $placeholders = implode(',', array_fill(0, count($excludeQuestionIds), '?'));
            $sql .= " AND q.id_questions NOT IN ($placeholders)";
            $params = array_merge($params, $excludeQuestionIds);
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll();
    }
}
