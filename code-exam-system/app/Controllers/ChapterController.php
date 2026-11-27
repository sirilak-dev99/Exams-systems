<?php

declare(strict_types=1);

final class ChapterController extends Controller
{
    private ChapterModel $model;

    public function __construct()
    {
        $this->model = new ChapterModel();
    }

    // GET /courses/{id}/chapters
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

        $status   = isset($_GET['status']) ? trim((string)$_GET['status']) : null;
        $chapters = $this->model->getByCourse($courseId, $status !== '' ? $status : null);

        $this->json(200, ['courses_id' => $courseId, 'chapters' => $chapters], null);
    }

    // POST /courses/{id}/chapters
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
            $this->json(404, null, ['code' => 'COURSE_NOT_FOUND', 'message' => 'ไม่พบรายวิชาที่ระบุ หรือรายวิชาถูกลบไปแล้ว']);
            return;
        }

        $input     = $this->readInput();
        $chapterNo = $this->parsePositiveId($input['chapters_no'] ?? '');
        $name      = trim((string)($input['name'] ?? ''));
        $status    = trim((string)($input['status'] ?? 'active'));

        if ($chapterNo === null || $name === '' || mb_strlen($name) > 150) {
            $this->json(422, null, [
                'code'    => 'VALIDATION_ERROR',
                'message' => 'กรุณาระบุ chapters_no (>= 1) และชื่อบทเรียน (1-150 ตัวอักษร)',
            ]);
            return;
        }

        if (!in_array($status, ChapterModel::ALLOWED_STATUSES, true)) {
            $this->json(422, null, [
                'code'    => 'INVALID_STATUS',
                'message' => 'status ต้องเป็น active หรือ inactive เท่านั้น',
            ]);
            return;
        }

        if ($this->model->chapterNoExists($courseId, $chapterNo)) {
            $this->json(409, null, [
                'code'    => 'DUPLICATE_CHAPTER_NO',
                'message' => 'เลขบทเรียนนี้มีอยู่แล้วในรายวิชานี้',
            ]);
            return;
        }

        $newId = $this->model->create([
            'courses_id'  => $courseId,
            'chapters_no' => $chapterNo,
            'name'        => $name,
            'status'      => $status,
        ]);

        $this->json(201, ['chapter' => $this->model->findById($newId)], null);
    }

    // POST /chapters/{id} (รองรับ _method=DELETE ให้ปิดใช้งานแทนการลบ)
    public function update(int|string $id): void
    {
        Security::requirePermission('course.manage');
        $this->verifyCsrfIfWeb();

        $chapterId = $this->parsePositiveId($id);
        if ($chapterId === null) {
            $this->json(400, null, ['code' => 'INVALID_ID', 'message' => 'รหัสบทเรียนต้องเป็นจำนวนเต็มบวก']);
            return;
        }

        $input  = $this->readInput();
        $method = strtoupper(trim((string)($input['_method'] ?? ($_SERVER['REQUEST_METHOD'] ?? 'POST'))));
        if ($method === 'DELETE') {
            $this->deactivate($chapterId);
            return;
        }

        $existing = $this->model->findById($chapterId);
        if (!$existing) {
            $this->json(404, null, ['code' => 'NOT_FOUND', 'message' => 'ไม่พบข้อมูลบทเรียน']);
            return;
        }

        $chapterNo = array_key_exists('chapters_no', $input)
            ? $this->parsePositiveId($input['chapters_no'])
            : (int)$existing['chapters_no'];
        $name   = array_key_exists('name', $input) ? trim((string)$input['name']) : (string)$existing['name'];
        $status = trim((string)($input['status'] ?? $existing['status']));

        if ($chapterNo === null || $name === '' || mb_strlen($name) > 150) {
            $this->json(422, null, [
                'code'    => 'VALIDATION_ERROR',
                'message' => 'chapters_no ต้องเป็นจำนวนเต็มตั้งแต่ 1 ขึ้นไป และชื่อบทเรียนต้องมีความยาว 1-150 ตัวอักษร',
            ]);
            return;
        }

        if (!in_array($status, ChapterModel::ALLOWED_STATUSES, true)) {
            $this->json(422, null, [
                'code'    => 'INVALID_STATUS',
                'message' => 'status ต้องเป็น active หรือ inactive เท่านั้น',
            ]);
            return;
        }

        if ($this->model->chapterNoExists((int)$existing['courses_id'], $chapterNo, $chapterId)) {
            $this->json(409, null, [
                'code'    => 'DUPLICATE_CHAPTER_NO',
                'message' => 'เลขบทเรียนซ้ำกับบทอื่นในรายวิชาเดียวกัน',
            ]);
            return;
        }

        $this->model->update($chapterId, [
            'chapters_no' => $chapterNo,
            'name'        => $name,
            'status'      => $status,
        ]);

        $this->json(200, ['chapter' => $this->model->findById($chapterId)], null);
    }

    // POST /chapters/{id}/deactivate (ปิดใช้งานแทนการลบ)
    public function deactivate(int|string $id): void
    {
        Security::requirePermission('course.manage');
        $this->verifyCsrfIfWeb();

        $chapterId = $this->parsePositiveId($id);
        if ($chapterId === null) {
            $this->json(400, null, ['code' => 'INVALID_ID', 'message' => 'รหัสบทเรียนต้องเป็นจำนวนเต็มบวก']);
            return;
        }

        $existing = $this->model->findById($chapterId);
        if (!$existing) {
            $this->json(404, null, ['code' => 'NOT_FOUND', 'message' => 'ไม่พบข้อมูลบทเรียน']);
            return;
        }

        if ($existing['status'] === 'inactive') {
            $this->json(409, null, ['code' => 'ALREADY_INACTIVE', 'message' => 'บทเรียนนี้ถูกปิดใช้งานไปแล้ว']);
            return;
        }

        $this->model->deactivate($chapterId);
        $this->json(200, [
            'deactivated' => true,
            'chapter'     => $this->model->findById($chapterId),
        ], null);
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