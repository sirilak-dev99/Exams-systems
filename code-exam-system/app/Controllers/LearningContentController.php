<?php

declare(strict_types=1);

final class LearningContentController extends Controller
{
    private LearningContentModel $model;

    public function __construct()
    {
        $this->model = new LearningContentModel();
    }

    // GET /courses/{id}/learning-contents
    public function index(int|string $id): void
    {
        Security::requirePermission('course.view');

        $courseId = $this->parsePositiveId($id);
        if ($courseId === null) {
            $this->json(400, null, ['code' => 'INVALID_ID', 'message' => 'รหัสรายวิชาต้องเป็นจำนวนเต็มบวก']);
            return;
        }

        if (!$this->model->activeCourseExists($courseId)) {
            $this->json(404, null, ['code' => 'COURSE_NOT_FOUND', 'message' => 'ไม่พบรายวิชาที่ระบุ']);
            return;
        }

        $this->json(200, [
            'courses_id'        => $courseId,
            'learning_contents' => $this->model->getByCourse($courseId),
        ], null);
    }

    // GET /learning-contents/{id}
    public function show(int|string $id): void
    {
        Security::requirePermission('course.view');

        $contentId = $this->parsePositiveId($id);
        if ($contentId === null) {
            $this->json(400, null, ['code' => 'INVALID_ID', 'message' => 'รหัสเนื้อหาต้องเป็นจำนวนเต็มบวก']);
            return;
        }

        $item = $this->model->findById($contentId);
        if (!$item) {
            $this->json(404, null, ['code' => 'NOT_FOUND', 'message' => 'ไม่พบข้อมูลเนื้อหาการเรียน']);
            return;
        }

        $this->json(200, ['learning_content' => $item], null);
    }

    // POST /courses/{id}/learning-contents
    public function store(int|string $id): void
    {
        Security::requirePermission('course.manage');
        $this->verifyCsrfIfWeb();

        $courseId = $this->parsePositiveId($id);
        if ($courseId === null) {
            $this->json(400, null, ['code' => 'INVALID_ID', 'message' => 'รหัสรายวิชาต้องเป็นจำนวนเต็มบวก']);
            return;
        }

        if (!$this->model->activeCourseExists($courseId)) {
            $this->json(404, null, ['code' => 'COURSE_NOT_FOUND', 'message' => 'ไม่พบรายวิชาที่ระบุ']);
            return;
        }

        $input       = $this->readInput();
        $title       = trim((string)($input['title'] ?? ''));
        $contentType = trim((string)($input['content_type'] ?? 'text'));
        $contentBody = array_key_exists('content_body', $input) && $input['content_body'] !== null
            ? (string)$input['content_body']
            : null;
        $sourceUrl   = isset($input['source_url']) && trim((string)$input['source_url']) !== ''
            ? trim((string)$input['source_url'])
            : null;
        $description = isset($input['description']) && trim((string)$input['description']) !== ''
            ? trim((string)$input['description'])
            : null;
        $status      = trim((string)($input['status'] ?? 'draft'));

        if ($title === '' || mb_strlen($title) > 255) {
            $this->json(422, null, ['code' => 'VALIDATION_ERROR', 'message' => 'กรุณาระบุชื่อเนื้อหา (1-255 ตัวอักษร)']);
            return;
        }

        if (!in_array($contentType, LearningContentModel::ALLOWED_TYPES, true)) {
            $this->json(422, null, ['code' => 'INVALID_CONTENT_TYPE', 'message' => 'content_type ไม่ถูกต้อง']);
            return;
        }

        if (!in_array($status, LearningContentModel::ALLOWED_STATUSES, true)) {
            $this->json(422, null, ['code' => 'INVALID_STATUS', 'message' => 'status ไม่ถูกต้อง']);
            return;
        }

        $mainIds = $this->parseIdArray($input['main_concept_ids'] ?? []);
        $subIds  = $this->parseIdArray($input['sub_concept_ids'] ?? []);
        if ($mainIds === null || $subIds === null) {
            $this->json(422, null, ['code' => 'VALIDATION_ERROR', 'message' => 'main_concept_ids และ sub_concept_ids ต้องเป็นอาร์เรย์ของจำนวนเต็มบวก']);
            return;
        }

        $invalidMain = $this->model->findInvalidMainConceptIds($courseId, $mainIds);
        if ($invalidMain !== []) {
            $this->json(422, null, [
                'code'    => 'CONCEPT_COURSE_MISMATCH',
                'message' => 'พบ main_concepts_id ที่ไม่มีอยู่หรือไม่ได้อยู่ในรายวิชานี้: ' . implode(',', $invalidMain),
            ]);
            return;
        }

        $invalidSub = $this->model->findInvalidSubConceptIds($courseId, $subIds, $mainIds);
        if ($invalidSub !== []) {
            $this->json(422, null, [
                'code'    => 'CONCEPT_COURSE_MISMATCH',
                'message' => 'พบ sub_concepts_id ที่ไม่อยู่ในรายวิชาเดียวกันหรือไม่ได้อยู่ใต้ main_concept ที่เลือก: ' . implode(',', $invalidSub),
            ]);
            return;
        }

        $newId = $this->model->createWithConcepts([
            'courses_id'   => $courseId,
            'title'        => $title,
            'content_type' => $contentType,
            'content_body' => $contentBody,
            'source_url'   => $sourceUrl,
            'description'  => $description,
            'status'       => $status,
        ], $mainIds, $subIds);

        $this->json(201, ['learning_content' => $this->model->findById($newId)], null);
    }

    // POST /learning-contents/{id} (รองรับ _method=DELETE)
    public function update(int|string $id): void
    {
        Security::requirePermission('course.manage');
        $this->verifyCsrfIfWeb();

        $contentId = $this->parsePositiveId($id);
        if ($contentId === null) {
            $this->json(400, null, ['code' => 'INVALID_ID', 'message' => 'รหัสเนื้อหาต้องเป็นจำนวนเต็มบวก']);
            return;
        }

        $input  = $this->readInput();
        $method = strtoupper(trim((string)($input['_method'] ?? ($_SERVER['REQUEST_METHOD'] ?? 'POST'))));
        if ($method === 'DELETE') {
            $this->destroy($contentId);
            return;
        }

        $existing = $this->model->findById($contentId);
        if (!$existing) {
            $this->json(404, null, ['code' => 'NOT_FOUND', 'message' => 'ไม่พบข้อมูลเนื้อหาการเรียน']);
            return;
        }

        $courseId    = (int)$existing['courses_id'];
        $title       = array_key_exists('title', $input) ? trim((string)$input['title']) : (string)$existing['title'];
        $contentType = trim((string)($input['content_type'] ?? $existing['content_type']));
        $contentBody = array_key_exists('content_body', $input)
            ? ($input['content_body'] !== null ? (string)$input['content_body'] : null)
            : $existing['content_body'];
        $sourceUrl   = array_key_exists('source_url', $input)
            ? (trim((string)$input['source_url']) !== '' ? trim((string)$input['source_url']) : null)
            : $existing['source_url'];
        $description = array_key_exists('description', $input)
            ? (trim((string)$input['description']) !== '' ? trim((string)$input['description']) : null)
            : $existing['description'];
        $status      = trim((string)($input['status'] ?? $existing['status']));

        if ($title === '' || mb_strlen($title) > 255) {
            $this->json(422, null, ['code' => 'VALIDATION_ERROR', 'message' => 'ชื่อเนื้อหาต้องมีความยาว 1-255 ตัวอักษร']);
            return;
        }

        if (!in_array($contentType, LearningContentModel::ALLOWED_TYPES, true)) {
            $this->json(422, null, ['code' => 'INVALID_CONTENT_TYPE', 'message' => 'content_type ไม่ถูกต้อง']);
            return;
        }

        if (!in_array($status, LearningContentModel::ALLOWED_STATUSES, true)) {
            $this->json(422, null, ['code' => 'INVALID_STATUS', 'message' => 'status ไม่ถูกต้อง']);
            return;
        }

        $mainIds = array_key_exists('main_concept_ids', $input)
            ? $this->parseIdArray($input['main_concept_ids'])
            : null;
        $subIds = array_key_exists('sub_concept_ids', $input)
            ? $this->parseIdArray($input['sub_concept_ids'])
            : null;

        if ((array_key_exists('main_concept_ids', $input) && $mainIds === null)
            || (array_key_exists('sub_concept_ids', $input) && $subIds === null)
        ) {
            $this->json(422, null, ['code' => 'VALIDATION_ERROR', 'message' => 'รูปแบบ main_concept_ids หรือ sub_concept_ids ไม่ถูกต้อง']);
            return;
        }

        if ($mainIds !== null) {
            $invalidMain = $this->model->findInvalidMainConceptIds($courseId, $mainIds);
            if ($invalidMain !== []) {
                $this->json(422, null, ['code' => 'CONCEPT_COURSE_MISMATCH', 'message' => 'main_concepts_id บางรายการไม่ได้อยู่ในรายวิชานี้']);
                return;
            }
        }

        if ($subIds !== null) {
            $checkMain = $mainIds ?? $existing['main_concept_ids'];
            $invalidSub = $this->model->findInvalidSubConceptIds($courseId, $subIds, $checkMain);
            if ($invalidSub !== []) {
                $this->json(422, null, ['code' => 'CONCEPT_COURSE_MISMATCH', 'message' => 'sub_concepts_id บางรายการไม่อยู่ในรายวิชาหรือแนวคิดหลักเดียวกัน']);
                return;
            }
        }

        $this->model->updateWithConcepts($contentId, $courseId, [
            'title'        => $title,
            'content_type' => $contentType,
            'content_body' => $contentBody,
            'source_url'   => $sourceUrl,
            'description'  => $description,
            'status'       => $status,
        ], $mainIds, $subIds);

        $this->json(200, ['learning_content' => $this->model->findById($contentId)], null);
    }

    // POST /learning-contents/{id}/delete
    public function destroy(int|string $id): void
    {
        Security::requirePermission('course.manage');
        $this->verifyCsrfIfWeb();

        $contentId = $this->parsePositiveId($id);
        if ($contentId === null) {
            $this->json(400, null, ['code' => 'INVALID_ID', 'message' => 'รหัสเนื้อหาต้องเป็นจำนวนเต็มบวก']);
            return;
        }

        if (!$this->model->findById($contentId)) {
            $this->json(404, null, ['code' => 'NOT_FOUND', 'message' => 'ไม่พบข้อมูลเนื้อหาการเรียน']);
            return;
        }

        $this->model->deleteContent($contentId);
        $this->json(200, ['deleted_id' => $contentId], null);
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