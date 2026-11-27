<?php

declare(strict_types=1);

final class ExamScheduleController extends Controller
{
    private ExamScheduleModel $model;

    public function __construct()
    {
        $this->model = new ExamScheduleModel();
    }

    // POST /exams/{id}/schedule
    public function updateSchedule(int|string $id): void
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

        $openAt  = array_key_exists('open_at', $input) ? trim((string)$input['open_at']) : (string)$exam['open_at'];
        $closeAt = array_key_exists('close_at', $input) ? trim((string)$input['close_at']) : (string)$exam['close_at'];

        $openTs  = strtotime($openAt);
        $closeTs = strtotime($closeAt);
        if ($openAt === '' || $closeAt === '' || $openTs === false || $closeTs === false || $closeTs <= $openTs) {
            $this->json(422, null, [
                'code'    => 'INVALID_TIME_WINDOW',
                'message' => 'เวลาปิดสอบ (close_at) ต้องมากกว่าเวลาเปิดสอบ (open_at)',
            ]);
            return;
        }

        $durationMin = array_key_exists('duration_min', $input)
            ? $this->parsePositiveId($input['duration_min'])
            : (int)$exam['duration_min'];
        if ($durationMin === null || $durationMin <= 0) {
            $this->json(422, null, [
                'code'    => 'INVALID_DURATION',
                'message' => 'ระยะเวลาสอบ (duration_min) ต้องมากกว่า 0 นาที',
            ]);
            return;
        }

        $maxAttempts = array_key_exists('max_attempts', $input)
            ? $this->parsePositiveId($input['max_attempts'])
            : (int)$exam['max_attempts'];
        if ($maxAttempts === null || $maxAttempts < 1) {
            $this->json(422, null, [
                'code'    => 'INVALID_MAX_ATTEMPTS',
                'message' => 'จำนวนครั้งที่สอบได้ (max_attempts) ต้องไม่น้อยกว่า 1',
            ]);
            return;
        }

        $gracePeriodMin = array_key_exists('grace_period_min', $input)
            ? (int)$input['grace_period_min']
            : (int)$exam['grace_period_min'];
        if ($gracePeriodMin < 0) {
            $this->json(422, null, ['code' => 'INVALID_GRACE_PERIOD', 'message' => 'grace_period_min ต้องไม่น้อยกว่า 0']);
            return;
        }

        $showAnswer = array_key_exists('show_answer', $input)
            ? (trim((string)$input['show_answer']) !== '' ? trim((string)$input['show_answer']) : null)
            : $exam['show_answer'];
        if ($showAnswer !== null && !in_array($showAnswer, ExamScheduleModel::ALLOWED_SHOW_ANSWER, true)) {
            $this->json(422, null, ['code' => 'INVALID_SHOW_ANSWER', 'message' => 'ค่า show_answer ไม่ถูกต้อง']);
            return;
        }

        $accessCodeTtl = array_key_exists('access_code_ttl_min', $input)
            ? $this->parsePositiveId($input['access_code_ttl_min'])
            : (int)$exam['access_code_ttl_min'];
        if ($accessCodeTtl === null || $accessCodeTtl < 1) {
            $this->json(422, null, ['code' => 'INVALID_ACCESS_CODE_TTL', 'message' => 'access_code_ttl_min ต้องไม่น้อยกว่า 1 นาที']);
            return;
        }

        $this->model->updateSchedule($examId, [
            'open_at'                  => date('Y-m-d H:i:s', $openTs),
            'close_at'                 => date('Y-m-d H:i:s', $closeTs),
            'duration_min'             => $durationMin,
            'grace_period_min'         => $gracePeriodMin,
            'max_attempts'             => $maxAttempts,
            'show_answer'              => $showAnswer,
            'require_exam_code'        => array_key_exists('require_exam_code', $input) ? (!empty($input['require_exam_code']) ? 1 : 0) : (int)$exam['require_exam_code'],
            'access_code_ttl_min'      => $accessCodeTtl,
            'require_code_on_resume'   => array_key_exists('require_code_on_resume', $input) ? (!empty($input['require_code_on_resume']) ? 1 : 0) : (int)$exam['require_code_on_resume'],
            'require_location_consent' => array_key_exists('require_location_consent', $input) ? (!empty($input['require_location_consent']) ? 1 : 0) : (int)$exam['require_location_consent'],
        ]);

        $this->json(200, [
            'exam'      => $this->model->findExamById($examId),
            'checklist' => $this->model->getPublishChecklist($examId),
        ], null);
    }

    // GET /exams/{id}/publish-checklist
    public function checklist(int|string $id): void
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

        $this->json(200, ['checklist' => $this->model->getPublishChecklist($examId)], null);
    }

    // POST /exams/{id}/publish
    public function publish(int|string $id): void
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

        $checklist = $this->model->getPublishChecklist($examId);
        if (!$checklist['ready']) {
            $this->json(422, null, [
                'code'      => 'PUBLISH_PRECONDITION_FAILED',
                'message'   => 'ไม่สามารถเผยแพร่ชุดสอบได้ เนื่องจากข้อสอบ สิทธิ์ผู้เข้าสอบ หรือเวลาเปิดสอบยังไม่ครบถ้วน',
                'checklist' => $checklist,
            ]);
            return;
        }

        $this->model->publishExam($examId);
        $this->json(200, [
            'exam'      => $this->model->findExamById($examId),
            'checklist' => $checklist,
        ], null);
    }

    // POST /exams/{id}/close
    public function close(int|string $id): void
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

        $this->model->closeExam($examId);
        $this->json(200, ['exam' => $this->model->findExamById($examId)], null);
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