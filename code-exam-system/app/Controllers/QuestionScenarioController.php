<?php

declare(strict_types=1);

final class QuestionScenarioController extends Controller
{
    private QuestionScenarioModel $model;

    public function __construct()
    {
        $this->model = new QuestionScenarioModel();
    }

    // GET /question-scenarios
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
            'scenarios' => $this->model->listScenarios($courseId, $status),
        ], null);
    }

    // GET /question-scenarios/{id}
    public function show(int|string $id): void
    {
        Security::requirePermission('question.manage');

        $scenarioId = $this->parsePositiveId($id);
        if ($scenarioId === null) {
            $this->json(400, null, ['code' => 'INVALID_ID', 'message' => 'รหัสสถานการณ์ต้องเป็นจำนวนเต็มบวก']);
            return;
        }

        $scenario = $this->model->findScenarioById($scenarioId);
        if (!$scenario) {
            $this->json(404, null, ['code' => 'NOT_FOUND', 'message' => 'ไม่พบข้อมูลสถานการณ์']);
            return;
        }

        $this->json(200, ['scenario' => $scenario], null);
    }

    // POST /question-scenarios
    public function store(): void
    {
        Security::requirePermission('question.manage');
        $this->verifyCsrfIfWeb();

        $input = $this->readInput();
        $courseId = array_key_exists('courses_id', $input) && $input['courses_id'] !== null && $input['courses_id'] !== ''
            ? $this->parsePositiveId($input['courses_id'])
            : null;
        $title   = trim((string)($input['title'] ?? ''));
        $content = trim((string)($input['content'] ?? ''));
        $image   = isset($input['image']) && trim((string)$input['image']) !== '' ? trim((string)$input['image']) : null;
        $status  = trim((string)($input['status'] ?? 'draft'));

        if (!$this->model->activeCourseExists($courseId)) {
            $this->json(404, null, ['code' => 'COURSE_NOT_FOUND', 'message' => 'ไม่พบรายวิชาที่ระบุ']);
            return;
        }

        if ($title === '' || mb_strlen($title) > 200 || $content === '') {
            $this->json(422, null, ['code' => 'VALIDATION_ERROR', 'message' => 'กรุณาระบุ title (1-200 ตัวอักษร) และ content']);
            return;
        }

        if (!in_array($status, QuestionScenarioModel::ALLOWED_STATUSES, true)) {
            $this->json(422, null, ['code' => 'INVALID_STATUS', 'message' => 'status ต้องเป็น draft, published หรือ archived']);
            return;
        }

        $newId = $this->model->createScenario([
            'courses_id' => $courseId,
            'title'      => $title,
            'content'    => $content,
            'image'      => $image,
            'status'     => $status,
            'created_by' => $this->currentUserId(),
        ]);

        $this->json(201, ['scenario' => $this->model->findScenarioById($newId)], null);
    }

    // POST /question-scenarios/{id} (รองรับ _method=DELETE)
    public function update(int|string $id): void
    {
        Security::requirePermission('question.manage');
        $this->verifyCsrfIfWeb();

        $scenarioId = $this->parsePositiveId($id);
        if ($scenarioId === null) {
            $this->json(400, null, ['code' => 'INVALID_ID', 'message' => 'รหัสสถานการณ์ต้องเป็นจำนวนเต็มบวก']);
            return;
        }

        $input = $this->readInput();
        $method = strtoupper(trim((string)($input['_method'] ?? ($_SERVER['REQUEST_METHOD'] ?? 'POST'))));
        if ($method === 'DELETE') {
            if (!$this->model->findScenarioById($scenarioId)) {
                $this->json(404, null, ['code' => 'NOT_FOUND', 'message' => 'ไม่พบข้อมูลสถานการณ์']);
                return;
            }
            $this->model->softDeleteScenario($scenarioId);
            $this->json(200, ['deleted_id' => $scenarioId], null);
            return;
        }

        $existing = $this->model->findScenarioById($scenarioId);
        if (!$existing) {
            $this->json(404, null, ['code' => 'NOT_FOUND', 'message' => 'ไม่พบข้อมูลสถานการณ์']);
            return;
        }

        $courseId = array_key_exists('courses_id', $input)
            ? ($input['courses_id'] !== null && $input['courses_id'] !== '' ? $this->parsePositiveId($input['courses_id']) : null)
            : ($existing['courses_id'] !== null ? (int)$existing['courses_id'] : null);
        $title   = array_key_exists('title', $input) ? trim((string)$input['title']) : (string)$existing['title'];
        $content = array_key_exists('content', $input) ? trim((string)$input['content']) : (string)$existing['content'];
        $image   = array_key_exists('image', $input)
            ? (trim((string)$input['image']) !== '' ? trim((string)$input['image']) : null)
            : $existing['image'];
        $status  = trim((string)($input['status'] ?? $existing['status']));

        if (!$this->model->activeCourseExists($courseId)) {
            $this->json(404, null, ['code' => 'COURSE_NOT_FOUND', 'message' => 'ไม่พบรายวิชาที่ระบุ']);
            return;
        }

        if ($title === '' || mb_strlen($title) > 200 || $content === '') {
            $this->json(422, null, ['code' => 'VALIDATION_ERROR', 'message' => 'กรุณาระบุ title และ content ให้ถูกต้อง']);
            return;
        }

        if (!in_array($status, QuestionScenarioModel::ALLOWED_STATUSES, true)) {
            $this->json(422, null, ['code' => 'INVALID_STATUS', 'message' => 'status ไม่ถูกต้อง']);
            return;
        }

        $this->model->updateScenario($scenarioId, [
            'courses_id' => $courseId,
            'title'      => $title,
            'content'    => $content,
            'image'      => $image,
            'status'     => $status,
        ]);

        $this->json(200, ['scenario' => $this->model->findScenarioById($scenarioId)], null);
    }

    // GET /questions/{id}/versions/{versionId}/concepts
    public function getVersionConcepts(int|string $id, int|string $versionId): void
    {
        Security::requirePermission('question.manage');

        $qid = $this->parsePositiveId($id);
        $vid = $this->parsePositiveId($versionId);
        if ($qid === null || $vid === null) {
            $this->json(400, null, ['code' => 'INVALID_ID', 'message' => 'รหัสข้อสอบและรหัสเวอร์ชันต้องเป็นจำนวนเต็มบวก']);
            return;
        }

        $ver = $this->model->findQuestionVersion($qid, $vid);
        if (!$ver) {
            $this->json(404, null, ['code' => 'VERSION_NOT_FOUND', 'message' => 'ไม่พบเวอร์ชันข้อสอบที่ระบุ']);
            return;
        }

        $concepts = $this->model->getVersionConcepts($vid);
        $this->json(200, [
            'questions_id'        => $qid,
            'question_version_id' => $vid,
            'courses_id'          => $ver['courses_id'] !== null ? (int)$ver['courses_id'] : null,
            'main_concept_ids'    => $concepts['main_concept_ids'],
            'sub_concept_ids'     => $concepts['sub_concept_ids'],
        ], null);
    }

    // POST /questions/{id}/versions/{versionId}/concepts
    public function syncVersionConcepts(int|string $id, int|string $versionId): void
    {
        Security::requirePermission('question.manage');
        $this->verifyCsrfIfWeb();

        $qid = $this->parsePositiveId($id);
        $vid = $this->parsePositiveId($versionId);
        if ($qid === null || $vid === null) {
            $this->json(400, null, ['code' => 'INVALID_ID', 'message' => 'รหัสข้อสอบและรหัสเวอร์ชันต้องเป็นจำนวนเต็มบวก']);
            return;
        }

        $ver = $this->model->findQuestionVersion($qid, $vid);
        if (!$ver) {
            $this->json(404, null, ['code' => 'VERSION_NOT_FOUND', 'message' => 'ไม่พบเวอร์ชันข้อสอบที่ระบุ']);
            return;
        }

        if ($ver['courses_id'] === null) {
            $this->json(422, null, [
                'code'    => 'QUESTION_COURSE_REQUIRED',
                'message' => 'ข้อสอบคลังกลางที่ยังไม่ผูก courses_id ไม่สามารถผูกแนวคิดรายวิชาได้',
            ]);
            return;
        }

        $courseId = (int)$ver['courses_id'];
        $input    = $this->readInput();
        $mainIds  = $this->parseIdArray($input['main_concept_ids'] ?? []);
        $subIds   = $this->parseIdArray($input['sub_concept_ids'] ?? []);

        if ($mainIds === null || $subIds === null) {
            $this->json(422, null, ['code' => 'VALIDATION_ERROR', 'message' => 'main_concept_ids และ sub_concept_ids ต้องเป็นอาร์เรย์ของจำนวนเต็มบวก']);
            return;
        }

        $invalidMain = $this->model->findInvalidMainConceptIds($courseId, $mainIds);
        if ($invalidMain !== []) {
            $this->json(422, null, [
                'code'    => 'CONCEPT_COURSE_MISMATCH',
                'message' => 'พบ main_concepts_id ที่ไม่ได้อยู่ในรายวิชาเดียวกับโจทย์: ' . implode(',', $invalidMain),
            ]);
            return;
        }

        $invalidSub = $this->model->findInvalidSubConceptIds($courseId, $subIds, $mainIds);
        if ($invalidSub !== []) {
            $this->json(422, null, [
                'code'    => 'CONCEPT_COURSE_MISMATCH',
                'message' => 'พบ sub_concepts_id ที่ไม่ได้อยู่ในรายวิชาหรือแนวคิดหลักเดียวกับโจทย์: ' . implode(',', $invalidSub),
            ]);
            return;
        }

        $this->model->syncVersionConcepts($qid, $vid, $courseId, $mainIds, $subIds);
        $concepts = $this->model->getVersionConcepts($vid);

        $this->json(200, [
            'questions_id'        => $qid,
            'question_version_id' => $vid,
            'courses_id'          => $courseId,
            'main_concept_ids'    => $concepts['main_concept_ids'],
            'sub_concept_ids'     => $concepts['sub_concept_ids'],
        ], null);
    }

    // POST /questions/{id}/scenario
    public function attachScenario(int|string $id): void
    {
        Security::requirePermission('question.manage');
        $this->verifyCsrfIfWeb();

        $qid = $this->parsePositiveId($id);
        if ($qid === null) {
            $this->json(400, null, ['code' => 'INVALID_ID', 'message' => 'รหัสข้อสอบต้องเป็นจำนวนเต็มบวก']);
            return;
        }

        $question = $this->model->findQuestionById($qid);
        if (!$question) {
            $this->json(404, null, ['code' => 'QUESTION_NOT_FOUND', 'message' => 'ไม่พบข้อมูลข้อสอบ']);
            return;
        }

        $input = $this->readInput();
        $scenarioId = array_key_exists('scenario_id', $input) && $input['scenario_id'] !== null && $input['scenario_id'] !== ''
            ? $this->parsePositiveId($input['scenario_id'])
            : null;
        $sortOrder = array_key_exists('scenario_sort_order', $input) && $input['scenario_sort_order'] !== null && $input['scenario_sort_order'] !== ''
            ? $this->parsePositiveId($input['scenario_sort_order'])
            : 1;

        if ($scenarioId !== null) {
            $scenario = $this->model->findScenarioById($scenarioId);
            if (!$scenario) {
                $this->json(404, null, ['code' => 'SCENARIO_NOT_FOUND', 'message' => 'ไม่พบข้อมูลสถานการณ์ที่ระบุ']);
                return;
            }

            // ตรวจว่า courses_id ของ scenario (หากไม่เป็น NULL) ต้องตรงกับ courses_id ของโจทย์
            if ($scenario['courses_id'] !== null && (int)$scenario['courses_id'] !== (int)$question['courses_id']) {
                $this->json(422, null, [
                    'code'    => 'SCENARIO_COURSE_MISMATCH',
                    'message' => 'สถานการณ์นี้ไม่ได้อยู่ในรายวิชาเดียวกับโจทย์ข้อสอบ',
                ]);
                return;
            }
        }

        $this->model->attachScenarioToQuestion($qid, $scenarioId, $sortOrder);
        $this->json(200, ['question' => $this->model->findQuestionById($qid)], null);
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