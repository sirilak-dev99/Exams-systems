<?php

declare(strict_types=1);

/**
 * ============================================================
 *  QuestionBankModel — คลังข้อสอบ · ใช้โดย QuestionController
 * ------------------------------------------------------------
 *  ตารางที่ใช้: questions · question_versions · question_options · question_learning_outcomes
 *              (อ่านเพิ่ม: bloom_levels, courses, chapters, learning_outcomes, question_scenarios, แนวคิด)
 *
 *  หลักการเก็บเวอร์ชัน
 *    เพิ่มข้อใหม่  → questions 1 แถว + question_versions ฉบับที่ 1 + ตัวเลือก/CLO ผูกกับฉบับนั้น
 *    แก้ไขข้อสอบ  → อัปเดตแถว questions + สร้างฉบับใหม่ (version_no + 1, parent_version_id = ฉบับก่อน)
 *                   ฉบับเดิมและตัวเลือกเดิมยังอยู่ ดูย้อนหลังได้
 *    ทั้งสองแบบทำใน transaction — ผิดพลาดตรงไหนจะไม่มีอะไรถูกบันทึก
 *
 *  ⚠️ ทุกคำสั่งใช้ชื่อพารามิเตอร์ไม่ซ้ำกันในคำสั่งเดียว (Database ปิด emulate prepares)
 * ============================================================
 */
final class QuestionBankModel
{
    public const TYPES = ['multiple_choice', 'true_false', 'short_answer', 'essay', 'matching'];
    public const STATUSES = ['draft', 'published', 'archived'];

    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getConnection();
    }

    private function all(string $sql, array $params = []): array
    {
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // ==================== รายการ + ตัวกรอง (GET /questions) ====================

    public function getList(array $f): array
    {
        $sql = "
            SELECT q.id_questions, q.text, q.type, q.bloom_levels_code, b.name_th AS bloom_name_th, q.status,
                   q.courses_id, c.code AS course_code, c.name AS course_name,
                   q.chapters_id, ch.chapters_no, ch.name AS chapter_name,
                   q.scenario_id, sc.title AS scenario_title, q.manual_max_score, q.updated_at
            FROM questions q
            JOIN bloom_levels b ON b.code = q.bloom_levels_code
            LEFT JOIN courses c ON c.id_courses = q.courses_id
            LEFT JOIN chapters ch ON ch.id_chapters = q.chapters_id
            LEFT JOIN question_scenarios sc ON sc.id_question_scenarios = q.scenario_id
            WHERE q.deleted_at IS NULL
        ";
        $p = [];
        if (($f['courses_id'] ?? '') === 'none') {
            $sql .= " AND q.courses_id IS NULL";
        } elseif (!empty($f['courses_id']) && ctype_digit((string)$f['courses_id'])) {
            $sql .= " AND q.courses_id = :courses_id";
            $p[':courses_id'] = (int)$f['courses_id'];
        }
        if (!empty($f['chapters_id']) && ctype_digit((string)$f['chapters_id'])) {
            $sql .= " AND q.chapters_id = :chapters_id";
            $p[':chapters_id'] = (int)$f['chapters_id'];
        }
        if (!empty($f['type']) && in_array($f['type'], self::TYPES, true)) {
            $sql .= " AND q.type = :type";
            $p[':type'] = $f['type'];
        }
        if (!empty($f['bloom'])) {
            $sql .= " AND q.bloom_levels_code = :bloom";
            $p[':bloom'] = (string)$f['bloom'];
        }
        if (!empty($f['status']) && in_array($f['status'], self::STATUSES, true)) {
            $sql .= " AND q.status = :status";
            $p[':status'] = $f['status'];
        }
        if (!empty($f['q'])) {
            $q = trim((string)$f['q']);
            if (preg_match('/^Q-?0*(\d+)$/i', $q, $m)) {      // ค้นด้วยรหัสข้อสอบ เช่น Q-0001
                $sql .= " AND q.id_questions = :qid";
                $p[':qid'] = (int)$m[1];
            } else {
                $sql .= " AND q.text LIKE :qtext";
                $p[':qtext'] = '%' . $q . '%';
            }
        }
        $sql .= " ORDER BY q.updated_at DESC, q.id_questions DESC";
        return $this->all($sql, $p);
    }

    public function getStatusCounts(): array
    {
        $counts = ['all' => 0, 'draft' => 0, 'published' => 0, 'archived' => 0];
        foreach ($this->all("SELECT status, COUNT(*) n FROM questions WHERE deleted_at IS NULL GROUP BY status") as $r) {
            $counts[$r['status']] = (int)$r['n'];
            $counts['all'] += (int)$r['n'];
        }
        return $counts;
    }

    // ==================== ตัวเลือกในฟอร์มและตัวกรอง ====================

    public function getCourses(): array
    {
        return $this->all("SELECT id_courses, code, name FROM courses WHERE deleted_at IS NULL ORDER BY code");
    }

    public function getChapters(?int $courseId = null): array
    {
        if ($courseId !== null) {
            return $this->all("SELECT id_chapters, courses_id, chapters_no, name FROM chapters WHERE courses_id = :cid ORDER BY chapters_no", [':cid' => $courseId]);
        }
        return $this->all("SELECT id_chapters, courses_id, chapters_no, name FROM chapters ORDER BY courses_id, chapters_no");
    }

    public function getBloomLevels(): array
    {
        return $this->all("SELECT code, name_th FROM bloom_levels ORDER BY sort_order, code");
    }

    public function getLearningOutcomes(): array
    {
        return $this->all("SELECT id_learning_outcomes, courses_id, code, description FROM learning_outcomes ORDER BY courses_id, code");
    }

    public function getScenarios(): array
    {
        return $this->all("SELECT id_question_scenarios, title FROM question_scenarios WHERE deleted_at IS NULL AND status <> 'archived' ORDER BY title");
    }

    // ==================== ข้อสอบรายข้อ (GET /questions/{id}) ====================

    public function find(int $id): ?array
    {
        $rows = $this->all("
            SELECT q.*, b.name_th AS bloom_name_th, c.code AS course_code, c.name AS course_name,
                   ch.chapters_no, ch.name AS chapter_name, sc.title AS scenario_title, sc.content AS scenario_content,
                   (SELECT u.display_name FROM question_versions v JOIN users u ON u.id_users = v.created_by
                     WHERE v.questions_id = q.id_questions ORDER BY v.version_no ASC LIMIT 1) AS created_by_name
            FROM questions q
            JOIN bloom_levels b ON b.code = q.bloom_levels_code
            LEFT JOIN courses c ON c.id_courses = q.courses_id
            LEFT JOIN chapters ch ON ch.id_chapters = q.chapters_id
            LEFT JOIN question_scenarios sc ON sc.id_question_scenarios = q.scenario_id
            WHERE q.id_questions = :id AND q.deleted_at IS NULL
        ", [':id' => $id]);
        return $rows[0] ?? null;
    }

    /** ฉบับทั้งหมด ใหม่ → เก่า */
    public function getVersions(int $questionId): array
    {
        return $this->all("
            SELECT v.id_question_versions, v.version_no, v.created_at, u.display_name AS created_by_name
            FROM question_versions v LEFT JOIN users u ON u.id_users = v.created_by
            WHERE v.questions_id = :qid ORDER BY v.version_no DESC
        ", [':qid' => $questionId]);
    }

    public function getOptions(int $versionId): array
    {
        return $this->all("SELECT label, text, is_correct, sort_order, image FROM question_options WHERE question_versions_id = :vid ORDER BY sort_order", [':vid' => $versionId]);
    }

    public function getVersionLearningOutcomes(int $versionId): array
    {
        return $this->all("
            SELECT lo.id_learning_outcomes, lo.code, lo.description
            FROM question_learning_outcomes ql JOIN learning_outcomes lo ON lo.id_learning_outcomes = ql.learning_outcomes_id
            WHERE ql.question_version_id = :vid ORDER BY lo.code
        ", [':vid' => $versionId]);
    }

    public function getVersionConcepts(int $versionId): array
    {
        return $this->all("
            SELECT mc.code AS main_code, mc.name AS main_name, sc.code AS sub_code, sc.name AS sub_name
            FROM question_version_main_concepts qm
            JOIN main_concepts mc ON mc.id_main_concepts = qm.main_concepts_id
            LEFT JOIN question_version_sub_concepts qs ON qs.question_version_id = qm.question_version_id
            LEFT JOIN sub_concepts sc ON sc.id_sub_concepts = qs.sub_concepts_id AND sc.main_concepts_id = mc.id_main_concepts
            WHERE qm.question_version_id = :vid
        ", [':vid' => $versionId]);
    }

    public function chapterBelongsToCourse(int $chapterId, int $courseId): bool
    {
        return (bool)$this->all("SELECT 1 FROM chapters WHERE id_chapters = :ch AND courses_id = :c", [':ch' => $chapterId, ':c' => $courseId]);
    }

    public function bloomExists(string $code): bool
    {
        return (bool)$this->all("SELECT 1 FROM bloom_levels WHERE code = :code", [':code' => $code]);
    }

    // ==================== บันทึก (POST /questions · POST /questions/{id}) — อยู่ใน transaction ====================

    /**
     * $data: type, courses_id(?int), chapters_id(?int), scenario_id(?int), bloom_levels_code, text, explanation(?string),
     *        manual_max_score(?float), shuffle_options(0|1), student_visible(0|1), status,
     *        options: list of [label, text, is_correct], lo_ids: list<int>
     */
    public function create(array $data, int $userId): int
    {
        $this->db->beginTransaction();
        try {
            $stmt = $this->db->prepare("
                INSERT INTO questions (chapters_id, courses_id, scenario_id, bloom_levels_code, type, text, explanation,
                                       manual_max_score, shuffle_options, student_visible, status, scoring_logic, created_at, updated_at)
                VALUES (:chapters_id, :courses_id, :scenario_id, :bloom, :type, :text, :explanation,
                        :score, :shuffle, :visible, :status, :scoring, NOW(), NOW())
            ");
            $stmt->execute($this->questionParams($data));
            $questionId = (int)$this->db->lastInsertId();
            $this->insertVersion($questionId, 1, null, $data, $userId);
            $this->db->commit();
            return $questionId;
        } catch (Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    /** แก้ไข = อัปเดตแถวหลัก + เพิ่มฉบับใหม่ (version_no + 1) */
    public function update(int $questionId, array $data, int $userId): int
    {
        $this->db->beginTransaction();
        try {
            $stmt = $this->db->prepare("
                UPDATE questions SET chapters_id = :chapters_id, courses_id = :courses_id, scenario_id = :scenario_id,
                       bloom_levels_code = :bloom, type = :type, text = :text, explanation = :explanation,
                       manual_max_score = :score, shuffle_options = :shuffle, student_visible = :visible,
                       status = :status, scoring_logic = :scoring, updated_at = NOW()
                WHERE id_questions = :id AND deleted_at IS NULL
            ");
            $stmt->execute($this->questionParams($data) + [':id' => $questionId]);

            $latest = $this->all("SELECT id_question_versions, version_no FROM question_versions WHERE questions_id = :qid ORDER BY version_no DESC LIMIT 1", [':qid' => $questionId])[0] ?? null;
            $versionNo = $latest ? (int)$latest['version_no'] + 1 : 1;
            $this->insertVersion($questionId, $versionNo, $latest ? (int)$latest['id_question_versions'] : null, $data, $userId);
            $this->db->commit();
            return $versionNo;
        } catch (Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    private function questionParams(array $d): array
    {
        return [
            ':chapters_id' => $d['chapters_id'],
            ':courses_id'  => $d['courses_id'],
            ':scenario_id' => $d['scenario_id'],
            ':bloom'       => $d['bloom_levels_code'],
            ':type'        => $d['type'],
            ':text'        => $d['text'],
            ':explanation' => $d['explanation'],
            ':score'       => $d['manual_max_score'],
            ':shuffle'     => $d['shuffle_options'],
            ':visible'     => $d['student_visible'],
            ':status'      => $d['status'],
            ':scoring'     => $d['type'] === 'essay' ? 'manual' : 'auto',
        ];
    }

    private function insertVersion(int $questionId, int $versionNo, ?int $parentId, array $d, int $userId): void
    {
        $snapshot = json_encode([
            'type' => $d['type'], 'text' => $d['text'], 'explanation' => $d['explanation'],
            'bloom_levels_code' => $d['bloom_levels_code'], 'options' => $d['options'],
        ], JSON_UNESCAPED_UNICODE);

        $stmt = $this->db->prepare("
            INSERT INTO question_versions (version_no, content_snapshot, bloom_levels_code, parent_version_id, created_at, created_by, questions_id, courses_id)
            VALUES (:no, :snap, :bloom, :parent, NOW(), :uid, :qid, :cid)
        ");
        $stmt->execute([':no' => $versionNo, ':snap' => $snapshot, ':bloom' => $d['bloom_levels_code'], ':parent' => $parentId,
                        ':uid' => $userId, ':qid' => $questionId, ':cid' => $d['courses_id']]);
        $versionId = (int)$this->db->lastInsertId();

        $opt = $this->db->prepare("INSERT INTO question_options (question_versions_id, label, text, is_correct, sort_order) VALUES (:vid, :label, :text, :correct, :sort)");
        foreach (array_values($d['options']) as $i => $o) {
            $opt->execute([':vid' => $versionId, ':label' => $o['label'], ':text' => $o['text'], ':correct' => $o['is_correct'], ':sort' => $i + 1]);
        }

        if ($d['courses_id'] !== null && $d['lo_ids']) {
            $lo = $this->db->prepare("
                INSERT INTO question_learning_outcomes (question_version_id, learning_outcomes_id, questions_id, courses_id)
                SELECT :vid, lo.id_learning_outcomes, :qid, lo.courses_id FROM learning_outcomes lo
                WHERE lo.id_learning_outcomes = :lo_id AND lo.courses_id = :cid
            ");
            foreach ($d['lo_ids'] as $loId) {
                $lo->execute([':vid' => $versionId, ':qid' => $questionId, ':lo_id' => (int)$loId, ':cid' => $d['courses_id']]);
            }
        }
    }
}
