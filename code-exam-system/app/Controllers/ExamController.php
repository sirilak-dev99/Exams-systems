<?php

declare(strict_types=1);

final class ExamController extends Controller
{
    private ExamModel $model;

    public function __construct()
    {
        $this->model = new ExamModel();
    }

    // GET /exams
    public function index(): void
    {
        Security::requirePermission('exam.manage');

        $courseId = isset($_GET['courses_id']) && $_GET['courses_id'] !== ''
            ? $this->parsePositiveId($_GET['courses_id'])
            : null;
        $offeringId = isset($_GET['course_offerings_id']) && $_GET['course_offerings_id'] !== ''
            ? $this->parsePositiveId($_GET['course_offerings_id'])
            : null;

        $this->json(200, ['exams' => $this->model->listExams($courseId, $offeringId)], null);
    }

    // GET /exams/{id}
    public function show(int|string $id): void
    {
        Security::requirePermission('exam.manage');

        $examId = $this->parsePositiveId($id);
        if ($examId === null) {
            $this->json(400, null, ['code' => 'INVALID_ID', 'message' => 'รหัสชุดข้อสอบต้องเป็นจำนวนเต็มบวก']);
            return;
        }

        $detail = $this->model->findExamDetail($examId);
        if (!$detail) {
            $this->json(404, null, ['code' => 'NOT_FOUND', 'message' => 'ไม่พบข้อมูลชุดข้อสอบ']);
            return;
        }

        $this->json(200, $detail, null);
    }

    // POST /exams
    public function store(): void
    {
        Security::requirePermission('exam.manage');
        $this->verifyCsrfIfWeb();

        $input = $this->readInput();
        $offeringId = $this->parsePositiveId($input['course_offerings_id'] ?? '');
        if ($offeringId === null) {
            $this->json(422, null, ['code' => 'VALIDATION_ERROR', 'message' => 'กรุณาระบุ course_offerings_id']);
            return;
        }

        $offering = $this->model->findCourseOffering($offeringId);
        if (!$offering) {
            $this->json(404, null, ['code' => 'OFFERING_NOT_FOUND', 'message' => 'ไม่พบการเปิดสอนรายวิชา (course_offerings)']);
            return;
        }

        $courseId = (int)$offering['courses_id'];
        if (array_key_exists('courses_id', $input) && $input['courses_id'] !== '' && $input['courses_id'] !== null) {
            $inputCourseId = $this->parsePositiveId($input['courses_id']);
            if ($inputCourseId !== $courseId) {
                $this->json(422, null, [
                    'code'    => 'OFFERING_COURSE_MISMATCH',
                    'message' => 'courses_id ไม่ตรงกับรายวิชาของ course_offerings ที่ระบุ',
                ]);
                return;
            }
        }

        $kind        = trim((string)($input['kind'] ?? 'quiz'));
        $title       = trim((string)($input['title'] ?? ''));
        $mode        = trim((string)($input['mode'] ?? 'fixed'));
        $durationMin = $this->parsePositiveId($input['duration_min'] ?? '');
        $maxAttempts = $this->parsePositiveId($input['max_attempts'] ?? 1);
        $openAt      = trim((string)($input['open_at'] ?? ''));
        $closeAt     = trim((string)($input['close_at'] ?? ''));
        $status      = trim((string)($input['status'] ?? 'draft'));
        $showAnswer  = isset($input['show_answer']) && trim((string)$input['show_answer']) !== ''
            ? trim((string)$input['show_answer'])
            : null;

        if (!in_array($kind, ExamModel::ALLOWED_KINDS, true)
            || !in_array($mode, ExamModel::ALLOWED_MODES, true)
            || !in_array($status, ExamModel::ALLOWED_STATUSES, true)
            || ($showAnswer !== null && !in_array($showAnswer, ExamModel::ALLOWED_SHOW_ANSWER, true))
        ) {
            $this->json(422, null, ['code' => 'VALIDATION_ERROR', 'message' => 'ค่า kind, mode, status หรือ show_answer ไม่ถูกต้อง']);
            return;
        }

        if ($title === '' || mb_strlen($title) > 150 || $durationMin === null || $maxAttempts === null) {
            $this->json(422, null, ['code' => 'VALIDATION_ERROR', 'message' => 'กรุณาระบุ title, duration_min (>0) และ max_attempts (>0)']);
            return;
        }

        if ($openAt === '' || $closeAt === '' || strtotime($closeAt) <= strtotime($openAt)) {
            $this->json(422, null, ['code' => 'INVALID_TIME_WINDOW', 'message' => 'close_at ต้องมากกว่า open_at']);
            return;
        }

        $sectionIds = $this->parseIdArray($input['section_ids'] ?? []);
        if ($sectionIds === null) {
            $this->json(422, null, ['code' => 'VALIDATION_ERROR', 'message' => 'section_ids ต้องเป็นอาร์เรย์ของจำนวนเต็มบวก']);
            return;
        }

        $invalidSections = $this->model->findInvalidSectionIds($offeringId, $sectionIds);
        if ($invalidSections !== []) {
            $this->json(422, null, [
                'code'    => 'SECTION_OFFERING_MISMATCH',
                'message' => 'พบกลุ่มเรียนที่ไม่ได้อยู่ใน course_offerings นี้: ' . implode(',', $invalidSections),
            ]);
            return;
        }

        $examId = $this->model->createExam([
            'course_offerings_id'      => $offeringId,
            'courses_id'               => $courseId,
            'parent_exam_id'           => isset($input['parent_exam_id']) && $input['parent_exam_id'] !== '' ? $this->parsePositiveId($input['parent_exam_id']) : null,
            'created_by'               => $this->currentUserId(),
            'kind'                     => $kind,
            'title'                    => $title,
            'mode'                     => $mode,
            'duration_min'             => $durationMin,
            'grace_period_min'         => isset($input['grace_period_min']) ? max(0, (int)$input['grace_period_min']) : 5,
            'max_attempts'             => $maxAttempts,
            'open_at'                  => $openAt,
            'close_at'                 => $closeAt,
            'show_answer'              => $showAnswer,
            'shuffle_questions'        => !empty($input['shuffle_questions']) ? 1 : 0,
            'require_exam_code'        => !empty($input['require_exam_code']) ? 1 : 0,
            'access_code_ttl_min'      => isset($input['access_code_ttl_min']) ? max(1, (int)$input['access_code_ttl_min']) : 10,
            'require_code_on_resume'   => array_key_exists('require_code_on_resume', $input) ? (!empty($input['require_code_on_resume']) ? 1 : 0) : 1,
            'require_location_consent' => !empty($input['require_location_consent']) ? 1 : 0,
            'status'                   => $status,
        ], $sectionIds);

        $this->json(201, $this->model->findExamDetail($examId), null);
    }

    // POST /exams/{id} (รองรับ _method=DELETE)
    public function update(int|string $id): void
    {
        Security::requirePermission('exam.manage');
        $this->verifyCsrfIfWeb();

        $examId = $this->parsePositiveId($id);
        if ($examId === null) {
            $this->json(400, null, ['code' => 'INVALID_ID', 'message' => 'รหัสชุดข้อสอบต้องเป็นจำนวนเต็มบวก']);
            return;
        }

        $input = $this->readInput();
        $method = strtoupper(trim((string)($input['_method'] ?? ($_SERVER['REQUEST_METHOD'] ?? 'POST'))));
        if ($method === 'DELETE') {
            if (!$this->model->findExamDetail($examId)) {
                $this->json(404, null, ['code' => 'NOT_FOUND', 'message' => 'ไม่พบข้อมูลชุดข้อสอบ']);
                return;
            }
            $this->model->softDeleteExam($examId);
            $this->json(200, ['deleted_id' => $examId, 'soft_deleted' => true], null);
            return;
        }

        $this->json(405, null, ['code' => 'METHOD_NOT_ALLOWED', 'message' => 'ใช้ _method=DELETE หรือ endpoint /sections และ /questions']);
    }

    // POST /exams/{id}/sections
    public function syncSections(int|string $id): void
    {
        Security::requirePermission('exam.manage');
        $this->verifyCsrfIfWeb();

        $examId = $this->parsePositiveId($id);
        if ($examId === null) {
            $this->json(400, null, ['code' => 'INVALID_ID', 'message' => 'รหัสชุดข้อสอบต้องเป็นจำนวนเต็มบวก']);
            return;
        }

        $detail = $this->model->findExamDetail($examId);
        if (!$detail) {
            $this->json(404, null, ['code' => 'NOT_FOUND', 'message' => 'ไม่พบข้อมูลชุดข้อสอบ']);
            return;
        }

        $input = $this->readInput();
        $sectionIds = $this->parseIdArray($input['section_ids'] ?? []);
        if ($sectionIds === null) {
            $this->json(422, null, ['code' => 'VALIDATION_ERROR', 'message' => 'section_ids ต้องเป็นอาร์เรย์ของจำนวนเต็มบวก']);
            return;
        }

        $offeringId = (int)$detail['exam']['course_offerings_id'];
        $invalidSections = $this->model->findInvalidSectionIds($offeringId, $sectionIds);
        if ($invalidSections !== []) {
            $this->json(422, null, [
                'code'    => 'SECTION_OFFERING_MISMATCH',
                'message' => 'พบกลุ่มเรียนที่ไม่ได้อยู่ใน course_offerings ของชุดสอบนี้: ' . implode(',', $invalidSections),
            ]);
            return;
        }

        $this->model->syncExamSections($examId, $offeringId, $sectionIds);
        $this->json(200, $this->model->findExamDetail($examId), null);
    }

    // POST /exams/{id}/questions
    public function syncQuestions(int|string $id): void
    {
        Security::requirePermission('exam.manage');
        $this->verifyCsrfIfWeb();

        $examId = $this->parsePositiveId($id);
        if ($examId === null) {
            $this->json(400, null, ['code' => 'INVALID_ID', 'message' => 'รหัสชุดข้อสอบต้องเป็นจำนวนเต็มบวก']);
            return;
        }

        $detail = $this->model->findExamDetail($examId);
        if (!$detail) {
            $this->json(404, null, ['code' => 'NOT_FOUND', 'message' => 'ไม่พบข้อมูลชุดข้อสอบ']);
            return;
        }

        $input = $this->readInput();
        $rawQuestions = $input['questions'] ?? null;
        if (!is_array($rawQuestions)) {
            $this->json(422, null, ['code' => 'VALIDATION_ERROR', 'message' => 'กรุณาส่งรายการ questions เป็นอาร์เรย์']);
            return;
        }

        $examCourseId = (int)$detail['exam']['courses_id'];
        $prepared = [];
        $seenVersions = [];
        $seenOrders   = [];

        foreach ($rawQuestions as $idx => $item) {
            if (!is_array($item)) {
                $this->json(422, null, ['code' => 'VALIDATION_ERROR', 'message' => 'ข้อมูลข้อสอบในลำดับที่ ' . ($idx + 1) . ' ไม่ถูกต้อง']);
                return;
            }

            $versionId = $this->parsePositiveId($item['question_version_id'] ?? '');
            $sortOrder = isset($item['sort_order']) ? $this->parsePositiveId($item['sort_order']) : ($idx + 1);
            $score     = isset($item['score']) && is_numeric($item['score']) ? (float)$item['score'] : -1.0;

            if ($versionId === null || $sortOrder === null || $score < 0) {
                $this->json(422, null, ['code' => 'VALIDATION_ERROR', 'message' => 'กรุณาระบุ question_version_id, sort_order (>=1) และ score (>=0) ให้ถูกต้อง']);
                return;
            }

            if (isset($seenVersions[$versionId])) {
                $this->json(422, null, ['code' => 'DUPLICATE_QUESTION_VERSION', 'message' => 'พบ question_version_id ซ้ำในชุดสอบเดียวกัน']);
                return;
            }
            if (isset($seenOrders[$sortOrder])) {
                $this->json(422, null, ['code' => 'DUPLICATE_SORT_ORDER', 'message' => 'พบลำดับข้อ (sort_order) ซ้ำในชุดสอบเดียวกัน']);
                return;
            }

            $qv = $this->model->findQuestionVersionWithCourse($versionId);
            if (!$qv || $qv['courses_id'] === null || (int)$qv['courses_id'] !== $examCourseId) {
                $this->json(422, null, [
                    'code'    => 'QUESTION_VERSION_COURSE_MISMATCH',
                    'message' => "question_version_id {$versionId} ไม่มีอยู่จริงหรือไม่ได้อยู่ในรายวิชาเดียวกับชุดข้อสอบ",
                ]);
                return;
            }

            $seenVersions[$versionId] = true;
            $seenOrders[$sortOrder]   = true;
            $prepared[] = [
                'questions_id'        => (int)$qv['questions_id'],
                'question_version_id' => $versionId,
                'sort_order'          => $sortOrder,
                'score'               => round($score, 2),
            ];
        }

        try {
            $this->model->syncExamQuestions($examId, $examCourseId, $prepared);
        } catch (PDOException $e) {
            if ($e->getCode() === '45000') {
                $this->json(409, null, ['code' => 'EXAM_IN_PROGRESS', 'message' => $e->getMessage()]);
                return;
            }
            throw $e;
        }

        $this->json(200, $this->model->findExamDetail($examId), null);
    }

    private function currentUserId(): int
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            @session_start();
        }
        $uid = $_SESSION['user_id'] ?? $_SESSION['id_users'] ?? $_SESSION['user']['id_users'] ?? 2;
        return max(1, (int)$uid);
    }

    private function parseIdArray(mixed $raw): ?array
    {
        if (!is_array($raw)) {
            return null;
        }
        $result = [];
        foreach ($raw as $v) {
            $pid = $this->parsePositiveId($v);
            if ($pid === null) {
                return null;
            }
            $result[] = $pid;
        }
        return $result;
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