<?php

declare(strict_types=1);

final class ExamAccessCodeController extends Controller
{
    private ExamAccessCodeModel $model;

    public function __construct()
    {
        $this->model = new ExamAccessCodeModel();
    }

    // GET /exams/{id}/access-codes
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
            'exams_id'     => $examId,
            'access_codes' => $this->model->listByExam($examId),
        ], null);
    }

    // POST /exams/{id}/access-codes (สร้างรหัส 5 หลักใหม่ + ยกเลิกรหัสเดิมใน Transaction เดียว)
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

        $input = $this->readInput();
        $sectionId = array_key_exists('sections_id', $input) && $input['sections_id'] !== null && $input['sections_id'] !== ''
            ? $this->parsePositiveId($input['sections_id'])
            : null;

        if ($sectionId !== null && !$this->model->examSectionExists($examId, $sectionId)) {
            $this->json(422, null, [
                'code'    => 'SECTION_NOT_ASSIGNED_TO_EXAM',
                'message' => 'กลุ่มเรียนนี้ไม่ได้ถูกผูกเข้ากับชุดข้อสอบ',
            ]);
            return;
        }

        $defaultTtl = max(1, (int)($exam['access_code_ttl_min'] ?? 10));
        $ttlMin = array_key_exists('ttl_min', $input) && $input['ttl_min'] !== null && $input['ttl_min'] !== ''
            ? $this->parsePositiveId($input['ttl_min'])
            : $defaultTtl;

        if ($ttlMin === null || $ttlMin < 1) {
            $this->json(422, null, ['code' => 'INVALID_TTL', 'message' => 'อายุรหัส (ttl_min) ต้องไม่น้อยกว่า 1 นาที']);
            return;
        }

        $result = $this->model->rotateAccessCode($examId, $sectionId, $ttlMin, $this->currentUserId());
        $this->json(201, $result, null);
    }

    // POST /access-codes/{id}/revoke
    public function revoke(int|string $id): void
    {
        Security::requirePermission('exam.manage');
        $this->verifyCsrfIfWeb();

        $codeId = $this->parsePositiveId($id);
        if ($codeId === null) {
            $this->json(400, null, ['code' => 'INVALID_ID', 'message' => 'รหัส access_code_id ต้องเป็นจำนวนเต็มบวก']);
            return;
        }

        $existing = $this->model->findById($codeId);
        if (!$existing) {
            $this->json(404, null, ['code' => 'ACCESS_CODE_NOT_FOUND', 'message' => 'ไม่พบรหัสเข้าสอบที่ระบุ']);
            return;
        }

        $updated = $this->model->revokeCode($codeId, $this->currentUserId());
        $this->json(200, ['access_code' => $updated], null);
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