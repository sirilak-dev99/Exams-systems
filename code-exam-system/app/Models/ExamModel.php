<?php

declare(strict_types=1);

class ExamModel
{
    public function createExam(array $data): int
    {
        $pdo = Database::getConnection();

        $stmt = $pdo->prepare("
            INSERT INTO `exams` (
                `course_offerings_id`,
                `courses_id`,
                `parent_exam_id`,
                `created_by`,
                `kind`,
                `title`,
                `mode`,
                `duration_min`,
                `grace_period_min`,
                `max_attempts`,
                `open_at`,
                `close_at`,
                `show_answer`,
                `shuffle_questions`,
                `require_exam_code`,
                `access_code_ttl_min`,
                `require_code_on_resume`,
                `require_location_consent`,
                `exam_snapshot`,
                `status`,
                `created_at`,
                `updated_at`
            ) VALUES (
                :course_offerings_id,
                :courses_id,
                :parent_exam_id,
                :created_by,
                :kind,
                :title,
                :mode,
                :duration_min,
                :grace_period_min,
                :max_attempts,
                :open_at,
                :close_at,
                :show_answer,
                :shuffle_questions,
                :require_exam_code,
                :access_code_ttl_min,
                :require_code_on_resume,
                :require_location_consent,
                :exam_snapshot,
                :status,
                NOW(),
                NOW()
            )
        ");

        $stmt->execute([
            ':course_offerings_id'    => $data['course_offerings_id'],
            ':courses_id'             => $data['courses_id'],
            ':parent_exam_id'         => $data['parent_exam_id'] ?? null,
            ':created_by'             => $data['created_by'],
            ':kind'                   => $data['kind'] ?? 'quiz',
            ':title'                  => $data['title'],
            ':mode'                   => $data['mode'] ?? 'fixed',
            ':duration_min'           => $data['duration_min'],
            ':grace_period_min'       => $data['grace_period_min'] ?? 5,
            ':max_attempts'           => $data['max_attempts'] ?? 1,
            ':open_at'                => $data['open_at'],
            ':close_at'               => $data['close_at'],
            ':show_answer'            => $data['show_answer'] ?? 'never',
            ':shuffle_questions'      => $data['shuffle_questions'] ?? 0,
            ':require_exam_code'      => $data['require_exam_code'] ?? 0,
            ':access_code_ttl_min'    => $data['access_code_ttl_min'] ?? 10,
            ':require_code_on_resume' => $data['require_code_on_resume'] ?? 1,
            ':require_location_consent' => $data['require_location_consent'] ?? 0,
            ':exam_snapshot'          => $data['exam_snapshot'] ?? '{}',
            ':status'                 => $data['status'] ?? 'draft',
        ]);

        return (int)$pdo->lastInsertId();
    }

    public function getExamsByCreator(int $createdBy): array
    {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare("
        SELECT id_exams, title, kind, mode, duration_min, open_at, close_at, status, created_at
        FROM exams
        WHERE created_by = :created_by
          AND deleted_at IS NULL
        ORDER BY id_exams DESC
    ");
        $stmt->execute([':created_by' => $createdBy]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
