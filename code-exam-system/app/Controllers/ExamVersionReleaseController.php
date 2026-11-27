<?php

declare(strict_types=1);

final class ExamVersionReleaseController extends Controller
{
    private ExamVersionReleaseModel $model;

    public function __construct()
    {
        $this->model = new ExamVersionReleaseModel();
    }

    // GET /exams/{id}/version-releases
    public function index(int|string $id): void
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

        $this->json(200, [
            'exams_id' => $examId,
            'releases' => $this->model->listReleasesByExam($examId),
        ], null);
    }

    // POST /exams/{id}/version-releases
    public function store(int|string $id): void
    {
        Security::requirePermission('exam.manage');
        $this->verifyCsrfIfWeb();

        $examId = $this->parsePositiveId($id);
        if ($examId === null) {
            $this->json(400, null, ['code' => 'INVALID_ID', 'message' => 'รหัสชุดข้อสอบต้องเป็นจำนวนเต็มบวก']);
            return;
        }

        $exam = $this->model->findExamById($examId);
        if (!$exam) {
            $this->json(404, null, ['code' => 'EXAM_NOT_FOUND', 'message' => 'ไม่พบข้อมูลชุดข้อสอบ']);
            return;
        }

        $input      = $this->readInput();
        $questionId = $this->parsePositiveId($input['questions_id'] ?? '');
        $oldVerId   = $this->parsePositiveId($input['old_question_version_id'] ?? '');
        $newVerId   = $this->parsePositiveId($input['new_question_version_id'] ?? '');
        $reason = trim((string)($input['release_reason'] ?? $input['reason'] ?? ''));
        if ($reason === '') {
            $reason = 'ปรับปรุงเวอร์ชันข้อสอบระหว่างเปิดสอบ';
        }

        if ($questionId === null || $oldVerId === null || $newVerId === null) {
            $this->json(422, null, [
                'code'    => 'VALIDATION_ERROR',
                'message' => 'กรุณาระบุ questions_id, old_question_version_id และ new_question_version_id',
            ]);
            return;
        }

        if ($oldVerId === $newVerId) {
            $this->json(422, null, [
                'code'    => 'SAME_QUESTION_VERSION',
                'message' => 'old_question_version_id และ new_question_version_id ต้องแตกต่างกัน',
            ]);
            return;
        }

        $examQ = $this->model->findExamQuestionRow($examId, $questionId);
        if (!$examQ) {
            $this->json(404, null, ['code' => 'EXAM_QUESTION_NOT_FOUND', 'message' => 'ไม่พบโจทย์ข้อนี้ในชุดข้อสอบ']);
            return;
        }

        if ((int)$examQ['question_version_id'] !== $oldVerId) {
            $this->json(409, null, [
                'code'    => 'OLD_VERSION_MISMATCH',
                'message' => 'old_question_version_id ไม่ตรงกับเวอร์ชันปัจจุบันของโจทย์ในชุดข้อสอบ',
            ]);
            return;
        }

        if (!$this->model->versionBelongsToQuestion($newVerId, $questionId, (int)$exam['courses_id'])) {
            $this->json(422, null, [
                'code'    => 'INVALID_NEW_VERSION',
                'message' => 'new_question_version_id ไม่มีอยู่จริง หรือไม่ได้เป็นเวอร์ชันของโจทย์ข้อเดียวกันในรายวิชานี้',
            ]);
            return;
        }

        $result = $this->model->releaseNewVersion(
            (int)$examQ['id_exam_questions'],
            $examId,
            $questionId,
            $oldVerId,
            $newVerId,
            $reason,
            $this->currentUserId()
        );

        $this->json(201, $result, null);
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
