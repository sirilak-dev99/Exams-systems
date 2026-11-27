<?php

declare(strict_types=1);

final class ExamScheduleModel
{
    private PDO $db;

    public const ALLOWED_SHOW_ANSWER = ['never', 'after_submit', 'after_close', 'after_announce'];

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

    public function updateSchedule(int $examId, array $data): bool
    {
        $stmt = $this->db->prepare("
            UPDATE exams
            SET open_at = :open_at,
                close_at = :close_at,
                duration_min = :duration_min,
                grace_period_min = :grace_period_min,
                max_attempts = :max_attempts,
                show_answer = :show_answer,
                require_exam_code = :require_exam_code,
                access_code_ttl_min = :access_code_ttl_min,
                require_code_on_resume = :require_code_on_resume,
                require_location_consent = :require_location_consent,
                updated_at = NOW()
            WHERE id_exams = :id
              AND deleted_at IS NULL
        ");

        return $stmt->execute([
            ':id'                       => $examId,
            ':open_at'                  => $data['open_at'],
            ':close_at'                 => $data['close_at'],
            ':duration_min'             => $data['duration_min'],
            ':grace_period_min'         => $data['grace_period_min'],
            ':max_attempts'             => $data['max_attempts'],
            ':show_answer'              => $data['show_answer'],
            ':require_exam_code'        => $data['require_exam_code'],
            ':access_code_ttl_min'      => $data['access_code_ttl_min'],
            ':require_code_on_resume'   => $data['require_code_on_resume'],
            ':require_location_consent' => $data['require_location_consent'],
        ]);
    }

    public function getPublishChecklist(int $examId): array
    {
        $exam = $this->findExamById($examId);
        if (!$exam) {
            return ['ready' => false, 'missing' => ['EXAM_NOT_FOUND']];
        }

        $qStmt = $this->db->prepare("SELECT COUNT(*) FROM exam_questions WHERE exams_id = :eid");
        $qStmt->execute([':eid' => $examId]);
        $questionsCount = (int)$qStmt->fetchColumn();

        $sStmt = $this->db->prepare("SELECT COUNT(*) FROM exam_sections WHERE exams_id = :eid");
        $sStmt->execute([':eid' => $examId]);
        $sectionsCount = (int)$sStmt->fetchColumn();

        $eStmt = $this->db->prepare("
            SELECT COUNT(DISTINCT ee.id_exam_eligibilities)
            FROM exam_eligibilities ee
            JOIN exam_eligibility_grants eeg
              ON eeg.exam_eligibilities_id = ee.id_exam_eligibilities
             AND eeg.status = 'active'
            WHERE ee.exams_id = :eid
              AND ee.status = 'eligible'
        ");
        $eStmt->execute([':eid' => $examId]);
        $eligibleWithGrantCount = (int)$eStmt->fetchColumn();

        $validTimeWindow = strtotime((string)$exam['close_at']) > strtotime((string)$exam['open_at']);
        $validDuration   = (int)$exam['duration_min'] > 0;
        $validAttempts   = (int)$exam['max_attempts'] >= 1;

        $missing = [];
        if (!$validTimeWindow) {
            $missing[] = 'INVALID_TIME_WINDOW';
        }
        if (!$validDuration) {
            $missing[] = 'INVALID_DURATION';
        }
        if (!$validAttempts) {
            $missing[] = 'INVALID_MAX_ATTEMPTS';
        }
        if ($questionsCount < 1) {
            $missing[] = 'NO_QUESTIONS';
        }
        if ($sectionsCount < 1) {
            $missing[] = 'NO_SECTIONS';
        }
        if ($eligibleWithGrantCount < 1) {
            $missing[] = 'NO_ELIGIBLE_STUDENTS';
        }

        return [
            'ready'                     => $missing === [],
            'questions_count'           => $questionsCount,
            'sections_count'            => $sectionsCount,
            'eligible_with_grant_count' => $eligibleWithGrantCount,
            'valid_time_window'         => $validTimeWindow,
            'valid_duration'            => $validDuration,
            'valid_max_attempts'        => $validAttempts,
            'missing'                   => $missing,
        ];
    }

    public function publishExam(int $examId): bool
    {
        $stmt = $this->db->prepare("
            UPDATE exams
            SET status = 'published',
                published_at = COALESCE(published_at, NOW()),
                updated_at = NOW()
            WHERE id_exams = :id
              AND deleted_at IS NULL
        ");

        return $stmt->execute([':id' => $examId]);
    }

    public function closeExam(int $examId): bool
    {
        $stmt = $this->db->prepare("
            UPDATE exams
            SET status = 'closed',
                updated_at = NOW()
            WHERE id_exams = :id
              AND deleted_at IS NULL
        ");

        return $stmt->execute([':id' => $examId]);
    }
}
