<?php

declare(strict_types=1);

final class ExamBlueprintController extends Controller
{
    private ExamBlueprintModel $model;

    public function __construct()
    {
        $this->model = new ExamBlueprintModel();
    }

    // GET /exams/{id}/blueprint
    public function show(int|string $id): void
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

        $bp = $this->model->findBlueprintByExam($examId);
        if (!$bp) {
            $this->json(404, null, ['code' => 'BLUEPRINT_NOT_FOUND', 'message' => 'ยังไม่มี Blueprint สำหรับชุดสอบนี้']);
            return;
        }

        $this->json(200, $bp, null);
    }

    // POST /exams/{id}/blueprint
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
        $title = trim((string)($input['title'] ?? $input['name'] ?? ('Blueprint - ' . $exam['title'])));
        $rules = $this->parseRules($input['rules'] ?? null);

        if ($title === '' || $rules === null || $rules === []) {
            $this->json(422, null, [
                'code'    => 'VALIDATION_ERROR',
                'message' => 'กรุณาระบุชื่อ Blueprint และรายการ rules อย่างน้อย 1 กติกา (question_count >= 1, score_per_question >= 0)',
            ]);
            return;
        }

        $this->model->saveBlueprintWithRules($examId, $title, $rules, $this->currentUserId());
        $this->json(201, $this->model->findBlueprintByExam($examId), null);
    }

    // POST /exams/{id}/blueprint/preview (สุ่มตัวอย่างโดยไม่บันทึก)
    public function preview(int|string $id): void
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
        if (array_key_exists('rules', $input)) {
            $rules = $this->parseRules($input['rules']);
            if ($rules === null || $rules === []) {
                $this->json(422, null, ['code' => 'VALIDATION_ERROR', 'message' => 'รูปแบบ rules ไม่ถูกต้อง']);
                return;
            }
        } else {
            $bp = $this->model->findBlueprintByExam($examId);
            if (!$bp || $bp['rules'] === []) {
                $this->json(404, null, ['code' => 'BLUEPRINT_NOT_FOUND', 'message' => 'ไม่พบ Blueprint หรือกติกาการสุ่ม']);
                return;
            }
            $rules = $bp['rules'];
        }

        $result = $this->model->sampleQuestionsByRules((int)$exam['courses_id'], $rules);
        if (!$result['ok']) {
            $this->json(422, null, [
                'code'      => 'INSUFFICIENT_QUESTION_BANK',
                'message'   => 'คลังข้อสอบมีจำนวนข้อไม่เพียงพอตามกติกา Blueprint',
                'shortages' => $result['shortages'],
            ]);
            return;
        }

        $this->json(200, [
            'preview'         => true,
            'total_questions' => $result['total_questions'],
            'total_score'     => $result['total_score'],
            'questions'       => $result['selected'],
        ], null);
    }

    // POST /exams/{id}/blueprint/generate (สุ่มจริงและบันทึกลง exam_questions)
    public function generate(int|string $id): void
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

        $bp = $this->model->findBlueprintByExam($examId);
        if (!$bp || $bp['rules'] === []) {
            $this->json(404, null, ['code' => 'BLUEPRINT_NOT_FOUND', 'message' => 'กรุณาสร้าง Blueprint ก่อนสั่งสุ่มข้อสอบ']);
            return;
        }

        $result = $this->model->sampleQuestionsByRules((int)$exam['courses_id'], $bp['rules']);
        if (!$result['ok']) {
            $this->json(422, null, [
                'code'      => 'INSUFFICIENT_QUESTION_BANK',
                'message'   => 'คลังข้อสอบไม่เพียงพอ ไม่สามารถสร้างชุดข้อสอบตาม Blueprint ได้',
                'shortages' => $result['shortages'],
            ]);
            return;
        }

        $this->model->applyGeneratedQuestions($examId, (int)$exam['courses_id'], $result['selected']);
        $this->json(200, [
            'exams_id'        => $examId,
            'total_questions' => $result['total_questions'],
            'total_score'     => $result['total_score'],
            'questions'       => $result['selected'],
        ], null);
    }

    private function parseRules(mixed $raw): ?array
    {
        if (!is_array($raw)) {
            return null;
        }
        $clean = [];
        foreach ($raw as $item) {
            if (!is_array($item)) {
                return null;
            }
            $count = $this->parsePositiveId($item['question_count'] ?? '');
            $score = isset($item['score_per_question']) && is_numeric($item['score_per_question'])
                ? (float)$item['score_per_question']
                : -1.0;

            if ($count === null || $score < 0) {
                return null;
            }

            $clean[] = [
                'chapters_id'        => isset($item['chapters_id']) && $item['chapters_id'] !== '' ? $this->parsePositiveId($item['chapters_id']) : null,
                'main_concepts_id'   => isset($item['main_concepts_id']) && $item['main_concepts_id'] !== '' ? $this->parsePositiveId($item['main_concepts_id']) : null,
                'sub_concepts_id'    => isset($item['sub_concepts_id']) && $item['sub_concepts_id'] !== '' ? $this->parsePositiveId($item['sub_concepts_id']) : null,
                'bloom_levels_code'  => isset($item['bloom_levels_code']) && trim((string)$item['bloom_levels_code']) !== ''
                    ? strtoupper(trim((string)$item['bloom_levels_code']))
                    : null,
                'question_count'     => $count,
                'score_per_question' => round($score, 2),
            ];
        }
        return $clean;
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