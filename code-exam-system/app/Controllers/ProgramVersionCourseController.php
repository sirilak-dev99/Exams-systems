<?php

declare(strict_types=1);

final class ProgramVersionCourseController extends Controller
{
    private ProgramVersionCourseModel $model;

    public function __construct()
    {
        $this->model = new ProgramVersionCourseModel();
    }

    // GET /program-versions/{id}/courses
    public function index(int|string $id): void
    {
        Security::requirePermission('course.view');

        $pvId = $this->parsePositiveId($id);
        if ($pvId === null) {
            $this->json(400, null, ['code' => 'INVALID_ID', 'message' => 'รหัสรุ่นหลักสูตรต้องเป็นจำนวนเต็มบวก']);
            return;
        }

        if (!$this->model->programVersionExists($pvId)) {
            $this->json(404, null, ['code' => 'PROGRAM_VERSION_NOT_FOUND', 'message' => 'ไม่พบรุ่นหลักสูตรที่ระบุ']);
            return;
        }

        $items = $this->model->getByProgramVersion($pvId);
        $this->json(200, ['program_versions_id' => $pvId, 'courses' => $items], null);
    }

    // POST /program-versions/{id}/courses
    public function store(int|string $id): void
    {
        Security::requirePermission('program.manage');
        $this->verifyCsrfIfWeb();

        $pvId = $this->parsePositiveId($id);
        if ($pvId === null) {
            $this->json(400, null, ['code' => 'INVALID_ID', 'message' => 'รหัสรุ่นหลักสูตรต้องเป็นจำนวนเต็มบวก']);
            return;
        }

        if (!$this->model->programVersionExists($pvId)) {
            $this->json(404, null, ['code' => 'PROGRAM_VERSION_NOT_FOUND', 'message' => 'ไม่พบรุ่นหลักสูตรที่ระบุ']);
            return;
        }

        $input    = $this->readInput();
        $courseId = $this->parsePositiveId($input['courses_id'] ?? '');

        if ($courseId === null) {
            $this->json(422, null, ['code' => 'VALIDATION_ERROR', 'message' => 'กรุณาระบุรหัสรายวิชา (courses_id)']);
            return;
        }

        if (!$this->model->activeCourseExists($courseId)) {
            $this->json(404, null, ['code' => 'COURSE_NOT_FOUND', 'message' => 'ไม่พบรายวิชาที่ระบุ หรือรายวิชาถูกลบไปแล้ว']);
            return;
        }

        $semesterNo = $this->parseNullablePositiveInt($input['semester_no'] ?? null, $semValid);
        $yearLevel  = $this->parseNullablePositiveInt($input['year_level'] ?? null, $yearValid);

        if (!$semValid || !$yearValid) {
            $this->json(422, null, [
                'code'    => 'VALIDATION_ERROR',
                'message' => 'semester_no และ year_level ต้องเป็นจำนวนเต็มตั้งแต่ 1 ขึ้นไป หรือเว้นว่าง',
            ]);
            return;
        }

        if ($this->model->bindingExists($pvId, $courseId)) {
            $this->json(409, null, ['code' => 'DUPLICATE_BINDING', 'message' => 'รายวิชานี้ถูกผูกกับรุ่นหลักสูตรนี้แล้ว']);
            return;
        }

        $newId = $this->model->create([
            'program_versions_id' => $pvId,
            'courses_id'          => $courseId,
            'semester_no'         => $semesterNo,
            'year_level'          => $yearLevel,
        ]);

        $this->json(201, ['program_version_course' => $this->model->findById($newId)], null);
    }

    // POST /program-version-courses/{id} (รองรับ _method=DELETE)
    public function update(int|string $id): void
    {
        Security::requirePermission('program.manage');
        $this->verifyCsrfIfWeb();

        $pvcId = $this->parsePositiveId($id);
        if ($pvcId === null) {
            $this->json(400, null, ['code' => 'INVALID_ID', 'message' => 'รหัสการผูกวิชาต้องเป็นจำนวนเต็มบวก']);
            return;
        }

        $input  = $this->readInput();
        $method = strtoupper(trim((string)($input['_method'] ?? ($_SERVER['REQUEST_METHOD'] ?? 'POST'))));
        if ($method === 'DELETE') {
            $this->destroy($pvcId);
            return;
        }

        $existing = $this->model->findById($pvcId);
        if (!$existing) {
            $this->json(404, null, ['code' => 'NOT_FOUND', 'message' => 'ไม่พบข้อมูลการผูกวิชากับรุ่นหลักสูตร']);
            return;
        }

        $rawSem  = array_key_exists('semester_no', $input) ? $input['semester_no'] : $existing['semester_no'];
        $rawYear = array_key_exists('year_level', $input) ? $input['year_level'] : $existing['year_level'];

        $semesterNo = $this->parseNullablePositiveInt($rawSem, $semValid);
        $yearLevel  = $this->parseNullablePositiveInt($rawYear, $yearValid);

        if (!$semValid || !$yearValid) {
            $this->json(422, null, [
                'code'    => 'VALIDATION_ERROR',
                'message' => 'semester_no และ year_level ต้องเป็นจำนวนเต็มตั้งแต่ 1 ขึ้นไป หรือเป็น null',
            ]);
            return;
        }

        $this->model->update($pvcId, [
            'semester_no' => $semesterNo,
            'year_level'  => $yearLevel,
        ]);

        $this->json(200, ['program_version_course' => $this->model->findById($pvcId)], null);
    }

    // POST /program-version-courses/{id}/delete
    public function destroy(int|string $id): void
    {
        Security::requirePermission('program.manage');
        $this->verifyCsrfIfWeb();

        $pvcId = $this->parsePositiveId($id);
        if ($pvcId === null) {
            $this->json(400, null, ['code' => 'INVALID_ID', 'message' => 'รหัสการผูกวิชาต้องเป็นจำนวนเต็มบวก']);
            return;
        }

        if (!$this->model->findById($pvcId)) {
            $this->json(404, null, ['code' => 'NOT_FOUND', 'message' => 'ไม่พบข้อมูลการผูกวิชากับรุ่นหลักสูตร']);
            return;
        }

        if ($this->model->hasOfferings($pvcId)) {
            $this->json(409, null, [
                'code'    => 'BINDING_IN_USE',
                'message' => 'ไม่สามารถยกเลิกการผูกวิชาได้ เนื่องจากมีการเปิดสอน (course_offerings) ผูกอยู่แล้ว',
            ]);
            return;
        }

        $this->model->delete($pvcId);
        $this->json(200, ['deleted_id' => $pvcId], null);
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

    private function parseNullablePositiveInt(mixed $raw, ?bool &$isValid): ?int
    {
        if ($raw === null || $raw === '') {
            $isValid = true;
            return null;
        }
        $parsed  = $this->parsePositiveId($raw);
        $isValid = ($parsed !== null);
        return $parsed;
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