<?php

declare(strict_types=1);

final class QuestionController extends Controller
{
    private QuestionModel $model;

    public function __construct()
    {
        $this->model = new QuestionModel();
    }

    // GET /questions
    public function index(): void
    {
        Security::requirePermission('question.manage');

        $courseId = isset($_GET['courses_id']) && $_GET['courses_id'] !== ''
            ? $this->parsePositiveId($_GET['courses_id'])
            : null;
        $status = isset($_GET['status']) && trim((string)$_GET['status']) !== ''
            ? trim((string)$_GET['status'])
            : null;

        $this->json(200, [
            'questions' => $this->model->listQuestions($courseId, $status),
        ], null);
    }

    // GET /questions/{id}
    public function show(int|string $id): void
    {
        Security::requirePermission('question.manage');

        $questionId = $this->parsePositiveId($id);
        if ($questionId === null) {
            $this->json(400, null, ['code' => 'INVALID_ID', 'message' => 'รหัสข้อสอบต้องเป็นจำนวนเต็มบวก']);
            return;
        }

        $detail = $this->model->findQuestionWithLatestVersion($questionId);
        if (!$detail) {
            $this->json(404, null, ['code' => 'NOT_FOUND', 'message' => 'ไม่พบข้อมูลข้อสอบ']);
            return;
        }

        $this->json(200, $detail, null);
    }

    // GET /questions/{id}/versions
    public function versions(int|string $id): void
    {
        Security::requirePermission('question.manage');

        $questionId = $this->parsePositiveId($id);
        if ($questionId === null) {
            $this->json(400, null, ['code' => 'INVALID_ID', 'message' => 'รหัสข้อสอบต้องเป็นจำนวนเต็มบวก']);
            return;
        }

        $detail = $this->model->findQuestionWithLatestVersion($questionId);
        if (!$detail) {
            $this->json(404, null, ['code' => 'NOT_FOUND', 'message' => 'ไม่พบข้อมูลข้อสอบ']);
            return;
        }

        $this->json(200, [
            'questions_id' => $questionId,
            'versions'     => $this->model->getVersionsByQuestion($questionId),
        ], null);
    }

    // POST /questions (สร้างโจทย์ + เวอร์ชันแรก + ตัวเลือกใน Transaction เดียว)
    public function store(): void
    {
        Security::requirePermission('question.manage');
        $this->verifyCsrfIfWeb();

        $input = $this->readInput();
        $courseId = array_key_exists('courses_id', $input) && $input['courses_id'] !== null && $input['courses_id'] !== ''
            ? $this->parsePositiveId($input['courses_id'])
            : null;
        $chapterId = array_key_exists('chapters_id', $input) && $input['chapters_id'] !== null && $input['chapters_id'] !== ''
            ? $this->parsePositiveId($input['chapters_id'])
            : null;

        if ($chapterId !== null && $courseId === null) {
            $this->json(422, null, ['code' => 'COURSE_REQUIRED', 'message' => 'ต้องระบุ courses_id เมื่อระบุ chapters_id']);
            return;
        }

        if (!$this->model->courseExists($courseId)) {
            $this->json(404, null, ['code' => 'COURSE_NOT_FOUND', 'message' => 'ไม่พบรายวิชาที่ระบุ']);
            return;
        }

        if ($chapterId !== null && !$this->model->chapterBelongsToCourse($chapterId, $courseId)) {
            $this->json(422, null, ['code' => 'CHAPTER_COURSE_MISMATCH', 'message' => 'บทเรียนไม่อยู่ในรายวิชาที่ระบุ']);
            return;
        }

        $bloomCode = strtoupper(trim((string)($input['bloom_levels_code'] ?? '')));
        $type = trim((string)($input['type'] ?? ''));
        $text = trim((string)($input['text'] ?? ''));
        $scoringLogic = trim((string)($input['scoring_logic'] ?? 'all_or_nothing'));
        $status = trim((string)($input['status'] ?? 'draft'));

        if ($bloomCode === '' || !$this->model->bloomCodeExists($bloomCode)) {
            $this->json(422, null, ['code' => 'INVALID_BLOOM_LEVEL', 'message' => 'bloom_levels_code ไม่ถูกต้องหรือไม่มีในระบบ']);
            return;
        }

        if (!in_array($type, QuestionModel::ALLOWED_TYPES, true)) {
            $this->json(422, null, ['code' => 'INVALID_QUESTION_TYPE', 'message' => 'type ของข้อสอบไม่ถูกต้อง']);
            return;
        }

        if ($text === '') {
            $this->json(422, null, ['code' => 'VALIDATION_ERROR', 'message' => 'กรุณาระบุข้อความคำถาม (text)']);
            return;
        }

        if (!in_array($status, QuestionModel::ALLOWED_STATUSES, true)) {
            $this->json(422, null, ['code' => 'INVALID_STATUS', 'message' => 'status ไม่ถูกต้อง']);
            return;
        }

        $options = is_array($input['options'] ?? null) ? $input['options'] : [];
        if (in_array($type, ['multiple_choice', 'true_false', 'matching'], true) && count($options) < 2) {
            $this->json(422, null, ['code' => 'OPTIONS_REQUIRED', 'message' => 'ข้อสอบประเภทนี้ต้องมีตัวเลือกอย่างน้อย 2 ตัวเลือก']);
            return;
        }

        $qData = [
            'chapters_id'         => $chapterId,
            'courses_id'          => $courseId,
            'scenario_id'         => isset($input['scenario_id']) && $input['scenario_id'] !== '' ? $this->parsePositiveId($input['scenario_id']) : null,
            'scenario_sort_order' => isset($input['scenario_sort_order']) && $input['scenario_sort_order'] !== '' ? $this->parsePositiveId($input['scenario_sort_order']) : null,
            'bloom_levels_code'   => $bloomCode,
            'type'                => $type,
            'text'                => $text,
            'image'               => isset($input['image']) && trim((string)$input['image']) !== '' ? trim((string)$input['image']) : null,
            'explanation'         => isset($input['explanation']) && trim((string)$input['explanation']) !== '' ? trim((string)$input['explanation']) : null,
            'manual_max_score'    => isset($input['manual_max_score']) && $input['manual_max_score'] !== '' ? (float)$input['manual_max_score'] : null,
            'shuffle_options'     => !empty($input['shuffle_options']) ? 1 : 0,
            'student_visible'     => !empty($input['student_visible']) ? 1 : 0,
            'status'              => $status,
            'scoring_logic'       => $scoringLogic !== '' ? $scoringLogic : 'all_or_nothing',
        ];

        $userId = $this->currentUserId();
        $created = $this->model->createQuestionWithInitialVersion($qData, $options, $userId);

        $this->json(201, $created, null);
    }

    // POST /questions/{id} (แก้ไขโจทย์ สร้างเวอร์ชันใหม่เสมอ ไม่ทับของเดิม + รองรับ _method=DELETE)
    public function update(int|string $id): void
    {
        Security::requirePermission('question.manage');
        $this->verifyCsrfIfWeb();

        $questionId = $this->parsePositiveId($id);
        if ($questionId === null) {
            $this->json(400, null, ['code' => 'INVALID_ID', 'message' => 'รหัสข้อสอบต้องเป็นจำนวนเต็มบวก']);
            return;
        }

        $input = $this->readInput();
        $method = strtoupper(trim((string)($input['_method'] ?? ($_SERVER['REQUEST_METHOD'] ?? 'POST'))));
        if ($method === 'DELETE') {
            $this->destroy($questionId);
            return;
        }

        $existingDetail = $this->model->findQuestionWithLatestVersion($questionId);
        if (!$existingDetail) {
            $this->json(404, null, ['code' => 'NOT_FOUND', 'message' => 'ไม่พบข้อมูลข้อสอบ']);
            return;
        }

        $existingQ = $existingDetail['question'];
        $courseId = array_key_exists('courses_id', $input)
            ? ($input['courses_id'] !== null && $input['courses_id'] !== '' ? $this->parsePositiveId($input['courses_id']) : null)
            : ($existingQ['courses_id'] !== null ? (int)$existingQ['courses_id'] : null);
        $chapterId = array_key_exists('chapters_id', $input)
            ? ($input['chapters_id'] !== null && $input['chapters_id'] !== '' ? $this->parsePositiveId($input['chapters_id']) : null)
            : ($existingQ['chapters_id'] !== null ? (int)$existingQ['chapters_id'] : null);

        if ($chapterId !== null && $courseId === null) {
            $this->json(422, null, ['code' => 'COURSE_REQUIRED', 'message' => 'ต้องระบุ courses_id เมื่อระบุ chapters_id']);
            return;
        }

        if (!$this->model->courseExists($courseId)) {
            $this->json(404, null, ['code' => 'COURSE_NOT_FOUND', 'message' => 'ไม่พบรายวิชาที่ระบุ']);
            return;
        }

        if ($chapterId !== null && !$this->model->chapterBelongsToCourse($chapterId, $courseId)) {
            $this->json(422, null, ['code' => 'CHAPTER_COURSE_MISMATCH', 'message' => 'บทเรียนไม่อยู่ในรายวิชาที่ระบุ']);
            return;
        }

        $bloomCode = array_key_exists('bloom_levels_code', $input)
            ? strtoupper(trim((string)$input['bloom_levels_code']))
            : (string)$existingQ['bloom_levels_code'];
        $type = array_key_exists('type', $input) ? trim((string)$input['type']) : (string)$existingQ['type'];
        $text = array_key_exists('text', $input) ? trim((string)$input['text']) : (string)$existingQ['text'];
        $status = array_key_exists('status', $input) ? trim((string)$input['status']) : (string)$existingQ['status'];
        $scoringLogic = array_key_exists('scoring_logic', $input)
            ? trim((string)$input['scoring_logic'])
            : (string)$existingQ['scoring_logic'];

        if ($bloomCode === '' || !$this->model->bloomCodeExists($bloomCode)) {
            $this->json(422, null, ['code' => 'INVALID_BLOOM_LEVEL', 'message' => 'bloom_levels_code ไม่ถูกต้อง']);
            return;
        }

        if (!in_array($type, QuestionModel::ALLOWED_TYPES, true)) {
            $this->json(422, null, ['code' => 'INVALID_QUESTION_TYPE', 'message' => 'type ของข้อสอบไม่ถูกต้อง']);
            return;
        }

        if ($text === '') {
            $this->json(422, null, ['code' => 'VALIDATION_ERROR', 'message' => 'กรุณาระบุข้อความคำถาม (text)']);
            return;
        }

        if (!in_array($status, QuestionModel::ALLOWED_STATUSES, true)) {
            $this->json(422, null, ['code' => 'INVALID_STATUS', 'message' => 'status ไม่ถูกต้อง']);
            return;
        }

        $options = array_key_exists('options', $input) && is_array($input['options'])
            ? $input['options']
            : null;

        $qData = [
            'chapters_id'         => $chapterId,
            'courses_id'          => $courseId,
            'scenario_id'         => array_key_exists('scenario_id', $input)
                ? ($input['scenario_id'] !== null && $input['scenario_id'] !== '' ? $this->parsePositiveId($input['scenario_id']) : null)
                : ($existingQ['scenario_id'] !== null ? (int)$existingQ['scenario_id'] : null),
            'scenario_sort_order' => array_key_exists('scenario_sort_order', $input)
                ? ($input['scenario_sort_order'] !== null && $input['scenario_sort_order'] !== '' ? $this->parsePositiveId($input['scenario_sort_order']) : null)
                : ($existingQ['scenario_sort_order'] !== null ? (int)$existingQ['scenario_sort_order'] : null),
            'bloom_levels_code'   => $bloomCode,
            'type'                => $type,
            'text'                => $text,
            'image'               => array_key_exists('image', $input)
                ? (trim((string)$input['image']) !== '' ? trim((string)$input['image']) : null)
                : $existingQ['image'],
            'explanation'         => array_key_exists('explanation', $input)
                ? (trim((string)$input['explanation']) !== '' ? trim((string)$input['explanation']) : null)
                : $existingQ['explanation'],
            'manual_max_score'    => array_key_exists('manual_max_score', $input)
                ? ($input['manual_max_score'] !== null && $input['manual_max_score'] !== '' ? (float)$input['manual_max_score'] : null)
                : ($existingQ['manual_max_score'] !== null ? (float)$existingQ['manual_max_score'] : null),
            'shuffle_options'     => array_key_exists('shuffle_options', $input) ? (!empty($input['shuffle_options']) ? 1 : 0) : (int)$existingQ['shuffle_options'],
            'student_visible'     => array_key_exists('student_visible', $input) ? (!empty($input['student_visible']) ? 1 : 0) : (int)$existingQ['student_visible'],
            'status'              => $status,
            'scoring_logic'       => $scoringLogic !== '' ? $scoringLogic : 'all_or_nothing',
        ];

        $userId = $this->currentUserId();
        $updated = $this->model->updateQuestionCreatingVersion($questionId, $qData, $options, $userId);

        $this->json(201, $updated, null);
    }

    // POST /questions/{id}/versions/{versionId} (รองรับ _method=DELETE เพื่อลบเวอร์ชันที่ยังไม่ถูกใช้ในชุดสอบ)
    public function deleteVersion(int|string $id, int|string $versionId): void
    {
        Security::requirePermission('question.manage');
        $this->verifyCsrfIfWeb();

        $qid = $this->parsePositiveId($id);
        $vid = $this->parsePositiveId($versionId);
        if ($qid === null || $vid === null) {
            $this->json(400, null, ['code' => 'INVALID_ID', 'message' => 'รหัสข้อสอบและรหัสเวอร์ชันต้องเป็นจำนวนเต็มบวก']);
            return;
        }

        $input = $this->readInput();
        $method = strtoupper(trim((string)($input['_method'] ?? ($_SERVER['REQUEST_METHOD'] ?? 'POST'))));
        if ($method !== 'DELETE') {
            $this->json(405, null, ['code' => 'METHOD_NOT_ALLOWED', 'message' => 'ต้องระบุ _method=DELETE เพื่อลบเวอร์ชัน']);
            return;
        }

        $version = $this->model->findVersionById($qid, $vid);
        if (!$version) {
            $this->json(404, null, ['code' => 'VERSION_NOT_FOUND', 'message' => 'ไม่พบเวอร์ชันข้อสอบที่ระบุ']);
            return;
        }

        if ($this->model->isVersionUsedInExam($qid, $vid)) {
            $this->json(409, null, [
                'code'    => 'VERSION_IN_USE',
                'message' => 'ไม่สามารถลบเวอร์ชันข้อสอบนี้ได้ เนื่องจากถูกใช้งานอยู่ในชุดสอบแล้ว',
            ]);
            return;
        }

        try {
            $this->model->deleteUnusedVersion($qid, $vid);
        } catch (PDOException $e) {
            if ($e->getCode() === '23000') {
                $this->json(409, null, [
                    'code'    => 'VERSION_IN_USE',
                    'message' => 'ไม่สามารถลบเวอร์ชันข้อสอบนี้ได้ เนื่องจากมีข้อมูลอ้างอิงในระบบ',
                ]);
                return;
            }
            throw $e;
        }

        $this->json(200, ['deleted_version_id' => $vid], null);
    }

    // POST /questions/{id}/delete
    public function destroy(int|string $id): void
    {
        Security::requirePermission('question.manage');
        $this->verifyCsrfIfWeb();

        $qid = $this->parsePositiveId($id);
        if ($qid === null) {
            $this->json(400, null, ['code' => 'INVALID_ID', 'message' => 'รหัสข้อสอบต้องเป็นจำนวนเต็มบวก']);
            return;
        }

        if (!$this->model->findQuestionWithLatestVersion($qid)) {
            $this->json(404, null, ['code' => 'NOT_FOUND', 'message' => 'ไม่พบข้อมูลข้อสอบ']);
            return;
        }

        if ($this->model->isQuestionUsedInExam($qid)) {
            $this->json(409, null, [
                'code'    => 'QUESTION_IN_USE',
                'message' => 'ไม่สามารถลบข้อสอบได้ เนื่องจากถูกใช้งานอยู่ในชุดสอบแล้ว',
            ]);
            return;
        }

        $this->model->softDeleteQuestion($qid);
        $this->json(200, ['deleted_id' => $qid, 'soft_deleted' => true], null);
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