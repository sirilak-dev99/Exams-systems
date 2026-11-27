<?php

declare(strict_types=1);

final class QuestionModel
{
    private PDO $db;

    public const ALLOWED_TYPES = ['multiple_choice', 'true_false', 'short_answer', 'essay', 'matching'];
    public const ALLOWED_STATUSES = ['draft', 'published', 'archived'];

    public function __construct()
    {
        $this->db = Database::getConnection();
    }

    public function bloomCodeExists(string $code): bool
    {
        $stmt = $this->db->prepare("SELECT 1 FROM bloom_levels WHERE code = :code LIMIT 1");
        $stmt->execute([':code' => $code]);
        return (bool)$stmt->fetchColumn();
    }

    public function courseExists(?int $courseId): bool
    {
        if ($courseId === null) {
            return true;
        }
        $stmt = $this->db->prepare("SELECT 1 FROM courses WHERE id_courses = :id AND deleted_at IS NULL LIMIT 1");
        $stmt->execute([':id' => $courseId]);
        return (bool)$stmt->fetchColumn();
    }

    public function chapterBelongsToCourse(int $chapterId, ?int $courseId): bool
    {
        if ($courseId === null) {
            return false;
        }
        $stmt = $this->db->prepare("SELECT 1 FROM chapters WHERE id_chapters = :cid AND courses_id = :course_id LIMIT 1");
        $stmt->execute([':cid' => $chapterId, ':course_id' => $courseId]);
        return (bool)$stmt->fetchColumn();
    }

    public function isQuestionUsedInExam(int $questionId): bool
    {
        $stmt = $this->db->prepare("SELECT 1 FROM exam_questions WHERE questions_id = :qid LIMIT 1");
        $stmt->execute([':qid' => $questionId]);
        return (bool)$stmt->fetchColumn();
    }

    public function isVersionUsedInExam(int $questionId, int $versionId): bool
    {
        $stmt = $this->db->prepare("
            SELECT 1
            FROM exam_questions
            WHERE questions_id = :qid
              AND question_version_id = :vid
            LIMIT 1
        ");
        $stmt->execute([':qid' => $questionId, ':vid' => $versionId]);
        return (bool)$stmt->fetchColumn();
    }

    public function listQuestions(?int $courseId = null, ?string $status = null): array
    {
        $sql = "
            SELECT
                q.id_questions,
                q.chapters_id,
                q.courses_id,
                q.scenario_id,
                q.scenario_sort_order,
                q.bloom_levels_code,
                q.type,
                q.text,
                q.image,
                q.explanation,
                q.manual_max_score,
                q.shuffle_options,
                q.student_visible,
                q.status,
                q.scoring_logic,
                q.created_at,
                q.updated_at,
                q.deleted_at,
                MAX(qv.id_question_versions) AS latest_version_id,
                MAX(qv.version_no) AS latest_version_no
            FROM questions q
            LEFT JOIN question_versions qv ON qv.questions_id = q.id_questions
            WHERE q.deleted_at IS NULL
        ";
        $params = [];

        if ($courseId !== null) {
            $sql .= " AND q.courses_id = :course_id";
            $params[':course_id'] = $courseId;
        }
        if ($status !== null && in_array($status, self::ALLOWED_STATUSES, true)) {
            $sql .= " AND q.status = :status";
            $params[':status'] = $status;
        }

        $sql .= " GROUP BY q.id_questions ORDER BY q.id_questions DESC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll();
    }

    public function findQuestionWithLatestVersion(int $questionId): ?array
    {
        $stmt = $this->db->prepare("
            SELECT *
            FROM questions
            WHERE id_questions = :id
              AND deleted_at IS NULL
            LIMIT 1
        ");
        $stmt->execute([':id' => $questionId]);
        $q = $stmt->fetch();
        if (!$q) {
            return null;
        }

        $latestVersion = $this->getLatestVersion($questionId);
        $options = $latestVersion ? $this->getOptionsByVersion((int)$latestVersion['id_question_versions']) : [];

        return [
            'question' => $q,
            'version'  => $latestVersion,
            'options'  => $options,
        ];
    }

    public function getVersionsByQuestion(int $questionId): array
    {
        $stmt = $this->db->prepare("
            SELECT
                id_question_versions,
                version_no,
                content_snapshot,
                bloom_levels_code,
                parent_version_id,
                created_at,
                created_by,
                questions_id,
                courses_id
            FROM question_versions
            WHERE questions_id = :qid
            ORDER BY version_no DESC, id_question_versions DESC
        ");
        $stmt->execute([':qid' => $questionId]);
        $versions = $stmt->fetchAll();

        foreach ($versions as &$v) {
            if (is_string($v['content_snapshot'])) {
                $decoded = json_decode($v['content_snapshot'], true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    $v['content_snapshot'] = $decoded;
                }
            }
            $v['options'] = $this->getOptionsByVersion((int)$v['id_question_versions']);
        }

        return $versions;
    }

    public function findVersionById(int $questionId, int $versionId): ?array
    {
        $stmt = $this->db->prepare("
            SELECT *
            FROM question_versions
            WHERE id_question_versions = :vid
              AND questions_id = :qid
            LIMIT 1
        ");
        $stmt->execute([':vid' => $versionId, ':qid' => $questionId]);
        $row = $stmt->fetch();
        if (!$row) {
            return null;
        }

        if (is_string($row['content_snapshot'])) {
            $decoded = json_decode($row['content_snapshot'], true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $row['content_snapshot'] = $decoded;
            }
        }
        $row['options'] = $this->getOptionsByVersion($versionId);

        return $row;
    }

    public function getOptionsByVersion(int $versionId): array
    {
        $stmt = $this->db->prepare("
            SELECT
                id_question_options,
                question_versions_id,
                label,
                text,
                is_correct,
                sort_order,
                image
            FROM question_options
            WHERE question_versions_id = :vid
            ORDER BY sort_order ASC, id_question_options ASC
        ");
        $stmt->execute([':vid' => $versionId]);

        return $stmt->fetchAll();
    }

    /**
     * สร้างโจทย์ + เวอร์ชันแรก (v1) + ตัวเลือกใน Transaction เดียว
     */
    public function createQuestionWithInitialVersion(array $qData, array $options, int $userId): array
    {
        $this->db->beginTransaction();
        try {
            $now = date('Y-m-d H:i:s');
            $stmtQ = $this->db->prepare("
                INSERT INTO questions (
                    chapters_id, courses_id, scenario_id, scenario_sort_order,
                    bloom_levels_code, type, text, image, explanation,
                    manual_max_score, shuffle_options, student_visible,
                    status, scoring_logic, created_at, updated_at, deleted_at
                ) VALUES (
                    :chapters_id, :courses_id, :scenario_id, :scenario_sort_order,
                    :bloom_levels_code, :type, :text, :image, :explanation,
                    :manual_max_score, :shuffle_options, :student_visible,
                    :status, :scoring_logic, :created_at, :updated_at, NULL
                )
            ");
            $stmtQ->execute([
                ':chapters_id'         => $qData['chapters_id'],
                ':courses_id'          => $qData['courses_id'],
                ':scenario_id'         => $qData['scenario_id'],
                ':scenario_sort_order' => $qData['scenario_sort_order'],
                ':bloom_levels_code'   => $qData['bloom_levels_code'],
                ':type'                => $qData['type'],
                ':text'                => $qData['text'],
                ':image'               => $qData['image'],
                ':explanation'         => $qData['explanation'],
                ':manual_max_score'    => $qData['manual_max_score'],
                ':shuffle_options'     => $qData['shuffle_options'],
                ':student_visible'     => $qData['student_visible'],
                ':status'              => $qData['status'],
                ':scoring_logic'       => $qData['scoring_logic'],
                ':created_at'          => $now,
                ':updated_at'          => $now,
            ]);

            $questionId = (int)$this->db->lastInsertId();
            $normalizedOptions = $this->normalizeOptions($options);

            $snapshot = $this->buildSnapshot(1, null, $qData, $normalizedOptions, $userId, $now);
            $versionId = $this->insertVersionRow(
                $questionId,
                1,
                $snapshot,
                $qData['bloom_levels_code'],
                null,
                $now,
                $userId,
                $qData['courses_id']
            );

            $insertedOptions = $this->insertOptionsForVersion($versionId, $normalizedOptions);

            $this->db->commit();

            return [
                'question' => $this->findQuestionRow($questionId),
                'version'  => $this->findVersionById($questionId, $versionId),
                'options'  => $insertedOptions,
            ];
        } catch (Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $e;
        }
    }

    /**
     * แก้ไขโจทย์: หากถูกใช้ในชุดสอบแล้ว หรือมีการแก้เนื้อหา/ตัวเลือก/Bloom จะสร้างเวอร์ชันใหม่เสมอ (ไม่ทับของเดิม)
     */
    public function updateQuestionCreatingVersion(int $questionId, array $qData, ?array $options, int $userId): array
    {
        $this->db->beginTransaction();
        try {
            $existingQ = $this->findQuestionRow($questionId);
            if (!$existingQ) {
                throw new RuntimeException('QUESTION_NOT_FOUND');
            }

            $latestVersion = $this->getLatestVersion($questionId);
            $prevVersionNo = $latestVersion ? (int)$latestVersion['version_no'] : 0;
            $parentVersionId = $latestVersion ? (int)$latestVersion['id_question_versions'] : null;
            $nextVersionNo = $prevVersionNo + 1;

            $effectiveOptions = $options !== null
                ? $this->normalizeOptions($options)
                : $this->normalizeOptionsFromExisting(
                    $latestVersion ? $this->getOptionsByVersion((int)$latestVersion['id_question_versions']) : []
                );

            $now = date('Y-m-d H:i:s');
            $stmtU = $this->db->prepare("
                UPDATE questions
                SET chapters_id = :chapters_id,
                    courses_id = :courses_id,
                    scenario_id = :scenario_id,
                    scenario_sort_order = :scenario_sort_order,
                    bloom_levels_code = :bloom_levels_code,
                    type = :type,
                    text = :text,
                    image = :image,
                    explanation = :explanation,
                    manual_max_score = :manual_max_score,
                    shuffle_options = :shuffle_options,
                    student_visible = :student_visible,
                    status = :status,
                    scoring_logic = :scoring_logic,
                    updated_at = :updated_at
                WHERE id_questions = :id
            ");
            $stmtU->execute([
                ':id'                  => $questionId,
                ':chapters_id'         => $qData['chapters_id'],
                ':courses_id'          => $qData['courses_id'],
                ':scenario_id'         => $qData['scenario_id'],
                ':scenario_sort_order' => $qData['scenario_sort_order'],
                ':bloom_levels_code'   => $qData['bloom_levels_code'],
                ':type'                => $qData['type'],
                ':text'                => $qData['text'],
                ':image'               => $qData['image'],
                ':explanation'         => $qData['explanation'],
                ':manual_max_score'    => $qData['manual_max_score'],
                ':shuffle_options'     => $qData['shuffle_options'],
                ':student_visible'     => $qData['student_visible'],
                ':status'              => $qData['status'],
                ':scoring_logic'       => $qData['scoring_logic'],
                ':updated_at'          => $now,
            ]);

            $snapshot = $this->buildSnapshot($nextVersionNo, $parentVersionId, $qData, $effectiveOptions, $userId, $now);
            $newVersionId = $this->insertVersionRow(
                $questionId,
                $nextVersionNo,
                $snapshot,
                $qData['bloom_levels_code'],
                $parentVersionId,
                $now,
                $userId,
                $qData['courses_id']
            );

            $insertedOptions = $this->insertOptionsForVersion($newVersionId, $effectiveOptions);

            $this->db->commit();

            return [
                'question' => $this->findQuestionRow($questionId),
                'version'  => $this->findVersionById($questionId, $newVersionId),
                'options'  => $insertedOptions,
            ];
        } catch (Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $e;
        }
    }

    public function deleteUnusedVersion(int $questionId, int $versionId): bool
    {
        $this->db->beginTransaction();
        try {
            $this->db->prepare("
                DELETE FROM question_options
                WHERE question_versions_id = :vid
            ")->execute([':vid' => $versionId]);

            $stmt = $this->db->prepare("
                DELETE FROM question_versions
                WHERE id_question_versions = :vid
                  AND questions_id = :qid
            ");
            $stmt->execute([':vid' => $versionId, ':qid' => $questionId]);

            $this->db->commit();
            return true;
        } catch (Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $e;
        }
    }

    public function softDeleteQuestion(int $questionId): bool
    {
        $stmt = $this->db->prepare("
            UPDATE questions
            SET deleted_at = :deleted_at,
                updated_at = :updated_at
            WHERE id_questions = :id
        ");
        $now = date('Y-m-d H:i:s');
        return $stmt->execute([':id' => $questionId, ':deleted_at' => $now, ':updated_at' => $now]);
    }

    private function findQuestionRow(int $questionId): ?array
    {
        $stmt = $this->db->prepare("SELECT * FROM questions WHERE id_questions = :id LIMIT 1");
        $stmt->execute([':id' => $questionId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    private function getLatestVersion(int $questionId): ?array
    {
        $stmt = $this->db->prepare("
            SELECT *
            FROM question_versions
            WHERE questions_id = :qid
            ORDER BY version_no DESC, id_question_versions DESC
            LIMIT 1
        ");
        $stmt->execute([':qid' => $questionId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    private function insertVersionRow(
        int $questionId,
        int $versionNo,
        array $snapshot,
        string $bloomCode,
        ?int $parentVersionId,
        string $createdAt,
        int $userId,
        ?int $courseId
    ): int {
        $stmt = $this->db->prepare("
            INSERT INTO question_versions (
                version_no, content_snapshot, bloom_levels_code,
                parent_version_id, created_at, created_by,
                questions_id, courses_id
            ) VALUES (
                :version_no, :content_snapshot, :bloom_levels_code,
                :parent_version_id, :created_at, :created_by,
                :questions_id, :courses_id
            )
        ");
        $stmt->execute([
            ':version_no'        => $versionNo,
            ':content_snapshot'  => json_encode($snapshot, JSON_UNESCAPED_UNICODE),
            ':bloom_levels_code' => $bloomCode,
            ':parent_version_id' => $parentVersionId,
            ':created_at'        => $createdAt,
            ':created_by'        => $userId,
            ':questions_id'      => $questionId,
            ':courses_id'        => $courseId,
        ]);

        return (int)$this->db->lastInsertId();
    }

    private function normalizeOptions(array $options): array
    {
        $clean = [];
        $idx = 1;
        foreach ($options as $opt) {
            $clean[] = [
                'label'      => trim((string)($opt['label'] ?? (string)$idx)),
                'text'       => trim((string)($opt['text'] ?? '')),
                'is_correct' => !empty($opt['is_correct']) ? 1 : 0,
                'sort_order' => isset($opt['sort_order']) ? max(1, (int)$opt['sort_order']) : $idx,
                'image'      => isset($opt['image']) && trim((string)$opt['image']) !== ''
                    ? trim((string)$opt['image'])
                    : null,
            ];
            $idx++;
        }
        return $clean;
    }

    private function normalizeOptionsFromExisting(array $rows): array
    {
        $clean = [];
        foreach ($rows as $idx => $r) {
            $clean[] = [
                'label'      => (string)$r['label'],
                'text'       => (string)$r['text'],
                'is_correct' => (int)$r['is_correct'] === 1 ? 1 : 0,
                'sort_order' => (int)$r['sort_order'] > 0 ? (int)$r['sort_order'] : ($idx + 1),
                'image'      => $r['image'] ?? null,
            ];
        }
        return $clean;
    }

    private function insertOptionsForVersion(int $versionId, array $options): array
    {
        $stmt = $this->db->prepare("
            INSERT INTO question_options (
                question_versions_id, label, text, is_correct, sort_order, image
            ) VALUES (
                :vid, :label, :text, :is_correct, :sort_order, :image
            )
        ");

        $result = [];
        foreach ($options as $opt) {
            $stmt->execute([
                ':vid'        => $versionId,
                ':label'      => $opt['label'],
                ':text'       => $opt['text'],
                ':is_correct' => $opt['is_correct'],
                ':sort_order' => $opt['sort_order'],
                ':image'      => $opt['image'],
            ]);
            $optId = (int)$this->db->lastInsertId();
            $result[] = [
                'id_question_options'  => $optId,
                'question_versions_id' => $versionId,
                'label'                => $opt['label'],
                'text'                 => $opt['text'],
                'is_correct'           => $opt['is_correct'],
                'sort_order'           => $opt['sort_order'],
                'image'                => $opt['image'],
            ];
        }

        return $result;
    }

    private function buildSnapshot(
        int $versionNo,
        ?int $parentVersionId,
        array $qData,
        array $options,
        int $userId,
        string $timestamp
    ): array {
        return [
            'version_no'        => $versionNo,
            'parent_version_id' => $parentVersionId,
            'captured_at'       => $timestamp,
            'created_by'        => $userId,
            'question'          => $qData,
            'options'           => $options,
        ];
    }
}