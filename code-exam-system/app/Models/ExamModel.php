<?php

declare(strict_types=1);

final class ExamModel
{
    private PDO $db;

    public const ALLOWED_KINDS = ['midterm', 'final', 'quiz', 'pre_post'];
    public const ALLOWED_MODES = ['fixed', 'blueprint'];
    public const ALLOWED_STATUSES = ['draft', 'published', 'closed', 'archived'];
    public const ALLOWED_SHOW_ANSWER = ['never', 'after_submit', 'after_close', 'after_announce'];

    public function __construct()
    {
        $this->db = Database::getConnection();
    }

    public function findCourseOffering(int $offeringId): ?array
    {
        $stmt = $this->db->prepare("
            SELECT id_course_offerings, courses_id, status
            FROM course_offerings
            WHERE id_course_offerings = :id
            LIMIT 1
        ");
        $stmt->execute([':id' => $offeringId]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    public function findInvalidSectionIds(int $offeringId, array $sectionIds): array
    {
        if ($sectionIds === []) {
            return [];
        }
        $placeholders = implode(',', array_fill(0, count($sectionIds), '?'));
        $stmt = $this->db->prepare("
            SELECT id_sections
            FROM sections
            WHERE course_offerings_id = ?
              AND id_sections IN ($placeholders)
        ");
        $stmt->execute(array_merge([$offeringId], $sectionIds));
        $valid = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));

        return array_values(array_diff($sectionIds, $valid));
    }

    public function findQuestionVersionWithCourse(int $versionId): ?array
    {
        $stmt = $this->db->prepare("
            SELECT
                qv.id_question_versions,
                qv.questions_id,
                COALESCE(qv.courses_id, q.courses_id) AS courses_id
            FROM question_versions qv
            JOIN questions q ON q.id_questions = qv.questions_id
            WHERE qv.id_question_versions = :vid
              AND q.deleted_at IS NULL
            LIMIT 1
        ");
        $stmt->execute([':vid' => $versionId]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    public function listExams(?int $courseId = null, ?int $offeringId = null): array
    {
        $sql = "
            SELECT
                e.id_exams,
                e.course_offerings_id,
                e.courses_id,
                e.kind,
                e.title,
                e.mode,
                e.duration_min,
                e.max_attempts,
                e.open_at,
                e.close_at,
                e.status,
                COUNT(eq.id_exam_questions) AS total_questions,
                COALESCE(SUM(eq.score), 0) AS total_score
            FROM exams e
            LEFT JOIN exam_questions eq ON eq.exams_id = e.id_exams
            WHERE e.deleted_at IS NULL
        ";
        $params = [];

        if ($courseId !== null) {
            $sql .= " AND e.courses_id = :course_id";
            $params[':course_id'] = $courseId;
        }
        if ($offeringId !== null) {
            $sql .= " AND e.course_offerings_id = :offering_id";
            $params[':offering_id'] = $offeringId;
        }

        $sql .= " GROUP BY e.id_exams ORDER BY e.id_exams DESC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll();
    }

    public function findExamDetail(int $examId): ?array
    {
        $stmt = $this->db->prepare("
            SELECT *
            FROM exams
            WHERE id_exams = :id
              AND deleted_at IS NULL
            LIMIT 1
        ");
        $stmt->execute([':id' => $examId]);
        $exam = $stmt->fetch();
        if (!$exam) {
            return null;
        }

        if (is_string($exam['exam_snapshot'])) {
            $decoded = json_decode($exam['exam_snapshot'], true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $exam['exam_snapshot'] = $decoded;
            }
        }

        $sections  = $this->getExamSections($examId);
        $questions = $this->getExamQuestions($examId);

        $totalScore = 0.0;
        foreach ($questions as $q) {
            $totalScore += (float)$q['score'];
        }

        return [
            'exam'            => $exam,
            'sections'        => $sections,
            'questions'       => $questions,
            'total_questions' => count($questions),
            'total_score'     => round($totalScore, 2),
        ];
    }

    public function createExam(array $data, array $sectionIds): int
    {
        $this->db->beginTransaction();
        try {
            $now = date('Y-m-d H:i:s');
            $snapshot = [
                'title'               => $data['title'],
                'kind'                => $data['kind'],
                'mode'                => $data['mode'],
                'course_offerings_id' => $data['course_offerings_id'],
                'courses_id'          => $data['courses_id'],
                'section_ids'         => array_values(array_unique($sectionIds)),
                'questions'           => [],
                'total_score'         => 0,
                'captured_at'         => $now,
            ];

            $stmt = $this->db->prepare("
                INSERT INTO exams (
                    course_offerings_id, courses_id, parent_exam_id, created_by,
                    kind, title, mode, duration_min, grace_period_min, max_attempts,
                    open_at, close_at, show_answer, shuffle_questions,
                    require_exam_code, access_code_ttl_min, require_code_on_resume,
                    require_location_consent, created_at, updated_at, published_at,
                    exam_snapshot, status, deleted_at
                ) VALUES (
                    :course_offerings_id, :courses_id, :parent_exam_id, :created_by,
                    :kind, :title, :mode, :duration_min, :grace_period_min, :max_attempts,
                    :open_at, :close_at, :show_answer, :shuffle_questions,
                    :require_exam_code, :access_code_ttl_min, :require_code_on_resume,
                    :require_location_consent, :created_at, :updated_at, :published_at,
                    :exam_snapshot, :status, NULL
                )
            ");
            $stmt->execute([
                ':course_offerings_id'      => $data['course_offerings_id'],
                ':courses_id'               => $data['courses_id'],
                ':parent_exam_id'           => $data['parent_exam_id'],
                ':created_by'               => $data['created_by'],
                ':kind'                     => $data['kind'],
                ':title'                    => $data['title'],
                ':mode'                     => $data['mode'],
                ':duration_min'             => $data['duration_min'],
                ':grace_period_min'         => $data['grace_period_min'],
                ':max_attempts'             => $data['max_attempts'],
                ':open_at'                  => $data['open_at'],
                ':close_at'                 => $data['close_at'],
                ':show_answer'              => $data['show_answer'],
                ':shuffle_questions'        => $data['shuffle_questions'],
                ':require_exam_code'        => $data['require_exam_code'],
                ':access_code_ttl_min'      => $data['access_code_ttl_min'],
                ':require_code_on_resume'   => $data['require_code_on_resume'],
                ':require_location_consent' => $data['require_location_consent'],
                ':created_at'               => $now,
                ':updated_at'               => $now,
                ':published_at'             => $data['status'] === 'published' ? $now : null,
                ':exam_snapshot'            => json_encode($snapshot, JSON_UNESCAPED_UNICODE),
                ':status'                   => $data['status'],
            ]);

            $examId = (int)$this->db->lastInsertId();
            if ($sectionIds !== []) {
                $this->insertExamSections($examId, (int)$data['course_offerings_id'], $sectionIds);
            }

            $this->db->commit();
            return $examId;
        } catch (Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $e;
        }
    }

    public function syncExamSections(int $examId, int $offeringId, array $sectionIds): void
    {
        $this->db->beginTransaction();
        try {
            $this->db->prepare("DELETE FROM exam_sections WHERE exams_id = :eid")
                ->execute([':eid' => $examId]);
            $this->insertExamSections($examId, $offeringId, $sectionIds);
            $this->db->commit();
        } catch (Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $e;
        }
    }

    public function syncExamQuestions(int $examId, int $courseId, array $items): void
    {
        $this->db->beginTransaction();
        try {
            $this->db->prepare("DELETE FROM exam_questions WHERE exams_id = :eid")
                ->execute([':eid' => $examId]);

            $stmt = $this->db->prepare("
                INSERT INTO exam_questions (
                    exams_id, questions_id, question_version_id, courses_id, sort_order, score
                ) VALUES (
                    :exams_id, :questions_id, :question_version_id, :courses_id, :sort_order, :score
                )
            ");

            $totalScore = 0.0;
            foreach ($items as $item) {
                $stmt->execute([
                    ':exams_id'            => $examId,
                    ':questions_id'        => $item['questions_id'],
                    ':question_version_id' => $item['question_version_id'],
                    ':courses_id'          => $courseId,
                    ':sort_order'          => $item['sort_order'],
                    ':score'               => $item['score'],
                ]);
                $totalScore += (float)$item['score'];
            }

            $snapshot = [
                'questions'       => $items,
                'total_questions' => count($items),
                'total_score'     => round($totalScore, 2),
                'updated_at'      => date('Y-m-d H:i:s'),
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

    public function softDeleteExam(int $examId): bool
    {
        $stmt = $this->db->prepare("
            UPDATE exams
            SET deleted_at = NOW(),
                updated_at = NOW()
            WHERE id_exams = :id
              AND deleted_at IS NULL
        ");

        return $stmt->execute([':id' => $examId]);
    }

    public function getExamSections(int $examId): array
    {
        $stmt = $this->db->prepare("
            SELECT es.exams_id, es.sections_id, es.course_offerings_id, s.section_no
            FROM exam_sections es
            JOIN sections s ON s.id_sections = es.sections_id
            WHERE es.exams_id = :eid
            ORDER BY s.section_no ASC
        ");
        $stmt->execute([':eid' => $examId]);

        return $stmt->fetchAll();
    }

    public function getExamQuestions(int $examId): array
    {
        $stmt = $this->db->prepare("
            SELECT
                eq.id_exam_questions,
                eq.exams_id,
                eq.questions_id,
                eq.question_version_id,
                eq.courses_id,
                eq.sort_order,
                eq.score,
                qv.version_no,
                qv.bloom_levels_code,
                q.type,
                q.text
            FROM exam_questions eq
            JOIN question_versions qv ON qv.id_question_versions = eq.question_version_id
            JOIN questions q ON q.id_questions = eq.questions_id
            WHERE eq.exams_id = :eid
            ORDER BY eq.sort_order ASC, eq.id_exam_questions ASC
        ");
        $stmt->execute([':eid' => $examId]);

        return $stmt->fetchAll();
    }

    private function insertExamSections(int $examId, int $offeringId, array $sectionIds): void
    {
        $stmt = $this->db->prepare("
            INSERT INTO exam_sections (exams_id, sections_id, course_offerings_id)
            VALUES (:eid, :sid, :oid)
        ");
        foreach (array_unique($sectionIds) as $sid) {
            $stmt->execute([':eid' => $examId, ':sid' => $sid, ':oid' => $offeringId]);
        }
    }
}