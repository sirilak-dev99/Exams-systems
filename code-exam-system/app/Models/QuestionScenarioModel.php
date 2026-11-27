<?php

declare(strict_types=1);

final class QuestionScenarioModel
{
    private PDO $db;

    public const ALLOWED_STATUSES = ['draft', 'published', 'archived'];

    public function __construct()
    {
        $this->db = Database::getConnection();
    }

    public function activeCourseExists(?int $courseId): bool
    {
        if ($courseId === null) {
            return true;
        }
        $stmt = $this->db->prepare("
            SELECT 1
            FROM courses
            WHERE id_courses = :id
              AND deleted_at IS NULL
            LIMIT 1
        ");
        $stmt->execute([':id' => $courseId]);

        return (bool)$stmt->fetchColumn();
    }

    public function listScenarios(?int $courseId = null, ?string $status = null): array
    {
        $sql = "
            SELECT
                id_question_scenarios,
                courses_id,
                title,
                content,
                image,
                status,
                created_by,
                created_at,
                updated_at
            FROM question_scenarios
            WHERE deleted_at IS NULL
        ";
        $params = [];

        if ($courseId !== null) {
            $sql .= " AND (courses_id = :course_id OR courses_id IS NULL)";
            $params[':course_id'] = $courseId;
        }
        if ($status !== null && in_array($status, self::ALLOWED_STATUSES, true)) {
            $sql .= " AND status = :status";
            $params[':status'] = $status;
        }

        $sql .= " ORDER BY id_question_scenarios DESC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll();
    }

    public function findScenarioById(int $id): ?array
    {
        $stmt = $this->db->prepare("
            SELECT
                id_question_scenarios,
                courses_id,
                title,
                content,
                image,
                status,
                created_by,
                created_at,
                updated_at
            FROM question_scenarios
            WHERE id_question_scenarios = :id
              AND deleted_at IS NULL
            LIMIT 1
        ");
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    public function createScenario(array $data): int
    {
        $stmt = $this->db->prepare("
            INSERT INTO question_scenarios (
                courses_id, title, content, image, status, created_by
            ) VALUES (
                :courses_id, :title, :content, :image, :status, :created_by
            )
        ");
        $stmt->execute([
            ':courses_id' => $data['courses_id'],
            ':title'      => $data['title'],
            ':content'    => $data['content'],
            ':image'      => $data['image'],
            ':status'     => $data['status'],
            ':created_by' => $data['created_by'],
        ]);

        return (int)$this->db->lastInsertId();
    }

    public function updateScenario(int $id, array $data): bool
    {
        $stmt = $this->db->prepare("
            UPDATE question_scenarios
            SET courses_id = :courses_id,
                title = :title,
                content = :content,
                image = :image,
                status = :status
            WHERE id_question_scenarios = :id
              AND deleted_at IS NULL
        ");

        return $stmt->execute([
            ':id'         => $id,
            ':courses_id' => $data['courses_id'],
            ':title'      => $data['title'],
            ':content'    => $data['content'],
            ':image'      => $data['image'],
            ':status'     => $data['status'],
        ]);
    }

    public function softDeleteScenario(int $id): bool
    {
        $stmt = $this->db->prepare("
            UPDATE question_scenarios
            SET deleted_at = NOW()
            WHERE id_question_scenarios = :id
              AND deleted_at IS NULL
        ");

        return $stmt->execute([':id' => $id]);
    }

    public function findQuestionById(int $questionId): ?array
    {
        $stmt = $this->db->prepare("
            SELECT id_questions, courses_id, scenario_id, scenario_sort_order
            FROM questions
            WHERE id_questions = :id
              AND deleted_at IS NULL
            LIMIT 1
        ");
        $stmt->execute([':id' => $questionId]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    public function findQuestionVersion(int $questionId, int $versionId): ?array
    {
        $stmt = $this->db->prepare("
            SELECT
                qv.id_question_versions,
                qv.questions_id,
                COALESCE(qv.courses_id, q.courses_id) AS courses_id
            FROM question_versions qv
            JOIN questions q ON q.id_questions = qv.questions_id
            WHERE qv.id_question_versions = :vid
              AND qv.questions_id = :qid
              AND q.deleted_at IS NULL
            LIMIT 1
        ");
        $stmt->execute([':vid' => $versionId, ':qid' => $questionId]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    public function findInvalidMainConceptIds(int $courseId, array $mainIds): array
    {
        if ($mainIds === []) {
            return [];
        }
        $placeholders = implode(',', array_fill(0, count($mainIds), '?'));
        $stmt = $this->db->prepare("
            SELECT id_main_concepts
            FROM main_concepts
            WHERE courses_id = ?
              AND id_main_concepts IN ($placeholders)
        ");
        $stmt->execute(array_merge([$courseId], $mainIds));
        $valid = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));

        return array_values(array_diff($mainIds, $valid));
    }

    public function findInvalidSubConceptIds(int $courseId, array $subIds, array $allowedMainIds = []): array
    {
        if ($subIds === []) {
            return [];
        }
        $placeholders = implode(',', array_fill(0, count($subIds), '?'));
        $sql = "
            SELECT id_sub_concepts
            FROM sub_concepts
            WHERE courses_id = ?
              AND id_sub_concepts IN ($placeholders)
        ";
        $params = array_merge([$courseId], $subIds);

        if ($allowedMainIds !== []) {
            $mainPlaceholders = implode(',', array_fill(0, count($allowedMainIds), '?'));
            $sql .= " AND main_concepts_id IN ($mainPlaceholders)";
            $params = array_merge($params, $allowedMainIds);
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $valid = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));

        return array_values(array_diff($subIds, $valid));
    }

    public function syncVersionConcepts(int $questionId, int $versionId, int $courseId, array $mainIds, array $subIds): void
    {
        $this->db->beginTransaction();
        try {
            $this->db->prepare("DELETE FROM question_version_main_concepts WHERE question_version_id = :vid")
                ->execute([':vid' => $versionId]);
            $this->db->prepare("DELETE FROM question_version_sub_concepts WHERE question_version_id = :vid")
                ->execute([':vid' => $versionId]);

            $insMain = $this->db->prepare("
                INSERT INTO question_version_main_concepts (
                    question_version_id, main_concepts_id, questions_id, courses_id
                ) VALUES (:vid, :mid, :qid, :cid)
            ");
            foreach (array_unique($mainIds) as $mid) {
                $insMain->execute([
                    ':vid' => $versionId,
                    ':mid' => $mid,
                    ':qid' => $questionId,
                    ':cid' => $courseId,
                ]);
            }

            $insSub = $this->db->prepare("
                INSERT INTO question_version_sub_concepts (
                    question_version_id, sub_concepts_id, questions_id, courses_id
                ) VALUES (:vid, :sid, :qid, :cid)
            ");
            foreach (array_unique($subIds) as $sid) {
                $insSub->execute([
                    ':vid' => $versionId,
                    ':sid' => $sid,
                    ':qid' => $questionId,
                    ':cid' => $courseId,
                ]);
            }

            $this->db->commit();
        } catch (Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $e;
        }
    }

    public function getVersionConcepts(int $versionId): array
    {
        $stmtMain = $this->db->prepare("
            SELECT main_concepts_id
            FROM question_version_main_concepts
            WHERE question_version_id = :vid
            ORDER BY main_concepts_id ASC
        ");
        $stmtMain->execute([':vid' => $versionId]);

        $stmtSub = $this->db->prepare("
            SELECT sub_concepts_id
            FROM question_version_sub_concepts
            WHERE question_version_id = :vid
            ORDER BY sub_concepts_id ASC
        ");
        $stmtSub->execute([':vid' => $versionId]);

        return [
            'main_concept_ids' => array_map('intval', $stmtMain->fetchAll(PDO::FETCH_COLUMN)),
            'sub_concept_ids'  => array_map('intval', $stmtSub->fetchAll(PDO::FETCH_COLUMN)),
        ];
    }

    public function attachScenarioToQuestion(int $questionId, ?int $scenarioId, ?int $sortOrder): bool
    {
        $stmt = $this->db->prepare("
            UPDATE questions
            SET scenario_id = :scenario_id,
                scenario_sort_order = :sort_order,
                updated_at = NOW()
            WHERE id_questions = :qid
              AND deleted_at IS NULL
        ");

        return $stmt->execute([
            ':qid'         => $questionId,
            ':scenario_id' => $scenarioId,
            ':sort_order'  => $scenarioId !== null ? $sortOrder : null,
        ]);
    }
}