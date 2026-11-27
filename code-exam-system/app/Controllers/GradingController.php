<?php

declare(strict_types=1);

final class GradingController extends Controller
{
    private GradingModel $model;

    public function __construct()
    {
        $this->model = new GradingModel();
    }

    // POST /attempts/{id}/grade-objective (Task 42)
    public function gradeObjective(int|string $id): void
    {
        Security::requirePermission('exam.manage');
        $this->verifyCsrfIfWeb();

        $attemptId = $this->parsePositiveId($id);
        if ($attemptId === null) {
            $this->json(400, null, ['code' => 'INVALID_ID', 'message' => 'รหัสรอบการสอบต้องเป็นจำนวนเต็มบวก']);
            return;
        }

        $result = $this->model->gradeObjectiveAttempt($attemptId, $this->currentUserId());
        if (isset($result['error'])) {
            [$status, $code, $message] = $result['error'];
            $this->json($status, null, ['code' => $code, 'message' => $message]);
            return;
        }

        $this->json(200, $result['data'], null);
    }

    // GET /exams/{id}/grading-assignments (Task 43)
    public function indexAssignments(int|string $id): void
    {
        Security::requirePermission('exam.manage');

        $examId = $this->parsePositiveId($id);
        if ($examId === null) {
            $this->json(400, null, ['code' => 'INVALID_ID', 'message' => 'รหัสชุดข้อสอบต้องเป็นจำนวนเต็มบวก']);
            return;
        }

        if (!$this->model->findExamById($examId)) {
            $this->json(404, null, ['code' => 'EXAM_NOT_FOUND', 'message' => 'ไม่พบข้อมูลชุดข้อสอบ']);
            return;
        }

        $graderId = isset($_GET['grader_id']) && $_GET['grader_id'] !== ''
            ? ($this->isSelf($_GET['grader_id']) ? $this->currentUserId() : $this->parsePositiveId($_GET['grader_id']))
            : null;
        $status = isset($_GET['status']) && trim((string)$_GET['status']) !== ''
            ? trim((string)$_GET['status'])
            : null;

        $this->json(200, [
            'exams_id'    => $examId,
            'assignments' => $this->model->listAssignmentsByExam($examId, $graderId, $status),
        ], null);
    }

    // POST /exams/{id}/grading-assignments (Task 43)
    public function assignGrader(int|string $id): void
    {
        Security::requirePermission('exam.manage');
        $this->verifyCsrfIfWeb();

        $examId = $this->parsePositiveId($id);
        if ($examId === null) {
            $this->json(400, null, ['code' => 'INVALID_ID', 'message' => 'รหัสชุดข้อสอบต้องเป็นจำนวนเต็มบวก']);
            return;
        }

        if (!$this->model->findExamById($examId)) {
            $this->json(404, null, ['code' => 'EXAM_NOT_FOUND', 'message' => 'ไม่พบข้อมูลชุดข้อสอบ']);
            return;
        }

        $input           = $this->readInput();
        $attemptAnswerId = $this->parsePositiveId($input['attempt_answer_id'] ?? '');
        $rawGrader       = $input['grader_id'] ?? '';
        $graderId        = $this->isSelf($rawGrader) ? $this->currentUserId() : $this->parsePositiveId($rawGrader);
        $note            = isset($input['note']) && trim((string)$input['note']) !== '' ? trim((string)$input['note']) : null;

        if ($attemptAnswerId === null || $graderId === null) {
            $this->json(422, null, ['code' => 'VALIDATION_ERROR', 'message' => 'กรุณาระบุ attempt_answer_id และ grader_id']);
            return;
        }

        $result = $this->model->createGradingAssignment(
            $examId,
            $attemptAnswerId,
            $graderId,
            $this->currentUserId(),
            $note
        );

        if (isset($result['error'])) {
            [$status, $code, $message] = $result['error'];
            $this->json($status, null, ['code' => $code, 'message' => $message]);
            return;
        }

        $this->json(201, $result['data'], null);
    }

    // POST /grading-assignments/{id}/status (Task 43)
    public function updateAssignmentStatus(int|string $id): void
    {
        Security::requirePermission('exam.manage');
        $this->verifyCsrfIfWeb();

        $assignmentId = $this->parsePositiveId($id);
        if ($assignmentId === null) {
            $this->json(400, null, ['code' => 'INVALID_ID', 'message' => 'รหัสรายการมอบหมายต้องเป็นจำนวนเต็มบวก']);
            return;
        }

        $input  = $this->readInput();
        $status = trim((string)($input['status'] ?? ''));
        $note   = isset($input['note']) && trim((string)$input['note']) !== '' ? trim((string)$input['note']) : null;

        if (!in_array($status, GradingModel::ALLOWED_ASSIGNMENT_STATUSES, true)) {
            $this->json(422, null, [
                'code'    => 'INVALID_STATUS',
                'message' => 'status ต้องเป็น assigned, in_progress, completed, reassigned หรือ cancelled',
            ]);
            return;
        }

        $result = $this->model->updateAssignmentStatus($assignmentId, $status, $note);
        if (isset($result['error'])) {
            [$codeStatus, $code, $message] = $result['error'];
            $this->json($codeStatus, null, ['code' => $code, 'message' => $message]);
            return;
        }

        $this->json(200, $result['data'], null);
    }

    // POST /attempt-answers/{id}/rubric-grade (Task 45)
    public function rubricGrade(int|string $id): void
    {
        Security::requirePermission('exam.manage');
        $this->verifyCsrfIfWeb();

        $attemptAnswerId = $this->parsePositiveId($id);
        if ($attemptAnswerId === null) {
            $this->json(400, null, ['code' => 'INVALID_ID', 'message' => 'รหัสคำตอบต้องเป็นจำนวนเต็มบวก']);
            return;
        }

        $input = $this->readInput();
        $rawCriteria = $input['criteria'] ?? null;
        if (!is_array($rawCriteria) || $rawCriteria === []) {
            $this->json(422, null, ['code' => 'VALIDATION_ERROR', 'message' => 'กรุณาระบุรายการเกณฑ์คะแนน (criteria) อย่างน้อย 1 เกณฑ์']);
            return;
        }

        $criteria = [];
        $seenNames = [];
        foreach ($rawCriteria as $idx => $item) {
            if (!is_array($item)) {
                $this->json(422, null, ['code' => 'VALIDATION_ERROR', 'message' => 'ข้อมูลเกณฑ์ลำดับที่ ' . ($idx + 1) . ' ไม่ถูกต้อง']);
                return;
            }
            $name       = trim((string)($item['criterion_name'] ?? ''));
            $maxScore   = isset($item['max_score']) && is_numeric($item['max_score']) ? round((float)$item['max_score'], 2) : -1.0;
            $scoreGiven = isset($item['score_given']) && is_numeric($item['score_given']) ? round((float)$item['score_given'], 2) : -1.0;
            $comments   = isset($item['comments']) && trim((string)$item['comments']) !== '' ? trim((string)$item['comments']) : null;

            if ($name === '' || mb_strlen($name) > 155 || $maxScore < 0 || $scoreGiven < 0 || $scoreGiven > $maxScore) {
                $this->json(422, null, [
                    'code'    => 'INVALID_CRITERION',
                    'message' => 'แต่ละเกณฑ์ต้องระบุ criterion_name (1-155 ตัวอักษร), max_score >= 0 และ 0 <= score_given <= max_score',
                ]);
                return;
            }

            $key = mb_strtolower($name);
            if (isset($seenNames[$key])) {
                $this->json(422, null, ['code' => 'DUPLICATE_CRITERION', 'message' => 'พบชื่อเกณฑ์ (criterion_name) ซ้ำกัน']);
                return;
            }
            $seenNames[$key] = true;

            $criteria[] = [
                'criterion_name' => $name,
                'max_score'      => $maxScore,
                'score_given'    => $scoreGiven,
                'comments'       => $comments,
            ];
        }

        $assignmentId = isset($input['grading_assignment_id']) && $input['grading_assignment_id'] !== ''
            ? $this->parsePositiveId($input['grading_assignment_id'])
            : null;
        $reason        = isset($input['reason']) ? (string)$input['reason'] : null;
        $markCompleted = !empty($input['mark_completed']);

        $result = $this->model->saveRubricGrade(
            $attemptAnswerId,
            $this->currentUserId(),
            $assignmentId,
            $criteria,
            $reason,
            $markCompleted
        );

        if (isset($result['error'])) {
            [$status, $code, $message] = $result['error'];
            $this->json($status, null, ['code' => $code, 'message' => $message]);
            return;
        }

        $this->json(200, $result['data'], null);
    }

    // GET /attempt-answers/{id}/grading (Task 45)
    public function showGrading(int|string $id): void
    {
        Security::requirePermission('exam.manage');

        $attemptAnswerId = $this->parsePositiveId($id);
        if ($attemptAnswerId === null) {
            $this->json(400, null, ['code' => 'INVALID_ID', 'message' => 'รหัสคำตอบต้องเป็นจำนวนเต็มบวก']);
            return;
        }

        $detail = $this->model->getAnswerGradingDetail($attemptAnswerId);
        if (!$detail) {
            $this->json(404, null, ['code' => 'ATTEMPT_ANSWER_NOT_FOUND', 'message' => 'ไม่พบคำตอบของนิสิตที่ระบุ']);
            return;
        }

        $this->json(200, $detail, null);
    }

    private function isSelf(mixed $raw): bool
    {
        return is_string($raw) && strtolower(trim($raw)) === 'self';
    }

    private function currentUserId(): int
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            @session_start();
        }
        $uid = $_SESSION['user_id'] ?? $_SESSION['id_users'] ?? $_SESSION['user']['id_users'] ?? 2;
        return max(1, (int)$uid);
    }

    private function parsePositiveId(mixed $raw): ?int
    {
        if (is_int($raw)) {
            return $raw > 0 ? $raw : null;
        }
        if (!is_string($raw) && !is_numeric($raw)) {
            return null;
        }
        $trimmed = trim((string)$raw);
        if ($trimmed === '' || !ctype_digit($trimmed)) {
            return null;
        }
        $val = (int)$trimmed;
        return $val > 0 ? $val : null;
    }

    private function readInput(): array
    {
        $raw = file_get_contents('php://input');
        if (is_string($raw) && $raw !== '') {
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }
        return $_POST;
    }

    private function verifyCsrfIfWeb(): void
    {
        if (Security::wantsJson()) {
            return;
        }
        $token = $_POST['csrf_token'] ?? $_POST['_csrf'] ?? null;
        if (!Security::verifyCsrf(is_string($token) ? $token : null)) {
            http_response_code(419);
            echo '419 Page Expired - Invalid CSRF token';
            exit;
        }
    }

    private function json(int $statusCode, mixed $data, ?array $error): void
    {
        http_response_code($statusCode);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'data'  => $data,
            'error' => $error,
        ], JSON_UNESCAPED_UNICODE);
    }
}