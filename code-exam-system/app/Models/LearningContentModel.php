<?php

declare(strict_types=1);

final class LearningContentModel
{
    private PDO $db;

    public const ALLOWED_TYPES = ['text', 'file', 'video', 'link', 'mixed'];
    public const ALLOWED_STATUSES = ['draft', 'published', 'archived'];

    public function __construct()
    {
        $this->db = Database::getConnection();
    }

    public function activeCourseExists(int $courseId): bool
    {
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

    /**
     * คืนค่า null หากทุก main_concept_ids อยู่ใน courseId เดียวกัน
     * คืนค่า array ของ id ที่ไม่ตรงกับรายวิชา หากมีบางตัวไม่ถูกต้อง
     */
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

    /**
     * ตรวจสอบว่า sub_concept_ids อยู่ใน courses_id เดียวกัน
     * (และหากระบุ main_concept_ids มาด้วย จะตรวจด้วยว่า sub อยู่ใต้ main ที่เลือกในวิชานั้น)
     */
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

    public function getByCourse(int $courseId): array
    {
        $stmt = $this->db->prepare("
            SELECT
                id_learning_contents,
                courses_id,
                title,
                content_type,
                content_body,
                source_url,
                description,
                status,
                created_at,
                updated_at
            FROM learning_contents
            WHERE courses_id = :course_id
            ORDER BY id_learning_contents DESC
        ");
        $stmt->execute([':course_id' => $courseId]);
        $rows = $stmt->fetchAll();

        foreach ($rows as &$row) {
            $cid = (int)$row['id_learning_contents'];
            $row['main_concept_ids'] = $this->getMainConceptIds($cid);
            $row['sub_concept_ids']  = $this->getSubConceptIds($cid);
        }

        return $rows;
    }

    public function findById(int $contentId): ?array
    {
        $stmt = $this->db->prepare("
            SELECT
                id_learning_contents,
                courses_id,
                title,
                content_type,
                content_body,
                source_url,
                description,
                status,
                created_at,
                updated_at
            FROM learning_contents
            WHERE id_learning_contents = :id
            LIMIT 1
        ");
        $stmt->execute([':id' => $contentId]);
        $row = $stmt->fetch();
        if (!$row) {
            return null;
        }

        $row['main_concept_ids'] = $this->getMainConceptIds($contentId);
        $row['sub_concept_ids']  = $this->getSubConceptIds($contentId);

        return $row;
    }

    public function createWithConcepts(array $data, array $mainIds, array $subIds): int
    {
        $this->db->beginTransaction();
        try {
            $stmt = $this->db->prepare("
                INSERT INTO learning_contents (
                    courses_id, title, content_type, content_body, source_url, description, status
                ) VALUES (
                    :courses_id, :title, :content_type, :content_body, :source_url, :description, :status
                )
            ");
            $stmt->execute([
                ':courses_id'   => $data['courses_id'],
                ':title'        => $data['title'],
                ':content_type' => $data['content_type'],
                ':content_body' => $data['content_body'],
                ':source_url'   => $data['source_url'],
                ':description'  => $data['description'],
                ':status'       => $data['status'],
            ]);

            $contentId = (int)$this->db->lastInsertId();
            $this->syncConcepts($contentId, (int)$data['courses_id'], $mainIds, $subIds);

            $this->db->commit();
            return $contentId;
        } catch (Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $e;
        }
    }

    public function updateWithConcepts(int $contentId, int $courseId, array $data, ?array $mainIds, ?array $subIds): bool
    {
        $this->db->beginTransaction();
        try {
            $stmt = $this->db->prepare("
                UPDATE learning_contents
                SET title = :title,
                    content_type = :content_type,
                    content_body = :content_body,
                    source_url = :source_url,
                    description = :description,
                    status = :status
                WHERE id_learning_contents = :id
            ");
            $stmt->execute([
                ':id'           => $contentId,
                ':title'        => $data['title'],
                ':content_type' => $data['content_type'],
                ':content_body' => $data['content_body'],
                ':source_url'   => $data['source_url'],
                ':description'  => $data['description'],
                ':status'       => $data['status'],
            ]);

            if ($mainIds !== null || $subIds !== null) {
                $finalMain = $mainIds ?? $this->getMainConceptIds($contentId);
                $finalSub  = $subIds ?? $this->getSubConceptIds($contentId);
                $this->syncConcepts($contentId, $courseId, $finalMain, $finalSub);
            }

            $this->db->commit();
            return true;
        } catch (Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $e;
        }
    }

    public function deleteContent(int $contentId): bool
    {
        $this->db->beginTransaction();
        try {
            $this->db->prepare("DELETE FROM learning_content_main_concepts WHERE learning_content_id = :id")
                ->execute([':id' => $contentId]);
            $this->db->prepare("DELETE FROM learning_content_sub_concepts WHERE learning_content_id = :id")
                ->execute([':id' => $contentId]);

            $stmt = $this->db->prepare("DELETE FROM learning_contents WHERE id_learning_contents = :id");
            $stmt->execute([':id' => $contentId]);

            $this->db->commit();
            return true;
        } catch (Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $e;
        }
    }

    private function syncConcepts(int $contentId, int $courseId, array $mainIds, array $subIds): void
    {
        $this->db->prepare("DELETE FROM learning_content_main_concepts WHERE learning_content_id = :id")
            ->execute([':id' => $contentId]);
        $this->db->prepare("DELETE FROM learning_content_sub_concepts WHERE learning_content_id = :id")
            ->execute([':id' => $contentId]);

        $insMain = $this->db->prepare("
            INSERT INTO learning_content_main_concepts (learning_content_id, main_concepts_id, courses_id)
            VALUES (:cid, :mid, :course_id)
        ");
        foreach (array_unique($mainIds) as $mid) {
            $insMain->execute([':cid' => $contentId, ':mid' => $mid, ':course_id' => $courseId]);
        }

        $insSub = $this->db->prepare("
            INSERT INTO learning_content_sub_concepts (learning_content_id, sub_concepts_id, courses_id)
            VALUES (:cid, :sid, :course_id)
        ");
        foreach (array_unique($subIds) as $sid) {
            $insSub->execute([':cid' => $contentId, ':sid' => $sid, ':course_id' => $courseId]);
        }
    }

    private function getMainConceptIds(int $contentId): array
    {
        $stmt = $this->db->prepare("
            SELECT main_concepts_id
            FROM learning_content_main_concepts
            WHERE learning_content_id = :id
            ORDER BY main_concepts_id ASC
        ");
        $stmt->execute([':id' => $contentId]);

        return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
    }

    private function getSubConceptIds(int $contentId): array
    {
        $stmt = $this->db->prepare("
            SELECT sub_concepts_id
            FROM learning_content_sub_concepts
            WHERE learning_content_id = :id
            ORDER BY sub_concepts_id ASC
        ");
        $stmt->execute([':id' => $contentId]);

        return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
    }
}