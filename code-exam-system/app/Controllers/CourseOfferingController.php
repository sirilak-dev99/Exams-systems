<?php

declare(strict_types=1);

final class CourseOfferingController extends Controller
{
    private CourseOfferingModel $model;

    public function __construct()
    {
        $this->model = new CourseOfferingModel();
    }

    // --- การเปิดสอนรายวิชา (course_offerings) ---

    // GET /course-offerings
    public function index(): void
    {
        Security::requirePermission('course.view');

        $offerings = $this->model->getAllOfferings([
            'academic_terms_id' => $_GET['academic_terms_id'] ?? null,
            'courses_id'        => $_GET['courses_id'] ?? null,
            'status'            => $_GET['status'] ?? null,
        ]);

        if (Security::wantsJson()) {
            $this->json(200, ['course_offerings' => $offerings], null);
            return;
        }

        $this->view('course_offerings/index', [
            'pageTitle'       => 'เปิดสอนรายวิชา',
            'courseOfferings' => $offerings,
        ]);
    }

    // POST /course-offerings
    public function store(): void
    {
        Security::requirePermission('course.manage');
        $this->verifyCsrfIfWeb();

        $input  = $this->readInput();
        $pvcId  = $this->parsePositiveId($input['program_version_courses_id'] ?? '');
        $termId = $this->parsePositiveId($input['academic_terms_id'] ?? '');
        $status = trim((string)($input['status'] ?? 'open'));

        if ($pvcId === null || $termId === null) {
            $this->json(422, null, [
                'code'    => 'VALIDATION_ERROR',
                'message' => 'กรุณาระบุ program_version_courses_id และ academic_terms_id',
            ]);
            return;
        }

        $pvc = $this->model->findProgramVersionCourse($pvcId);
        if (!$pvc) {
            $this->json(404, null, [
                'code'    => 'PVC_NOT_FOUND',
                'message' => 'ไม่พบข้อมูลการผูกวิชากับรุ่นหลักสูตรที่ระบุ',
            ]);
            return;
        }

        if (!$this->model->academicTermExists($termId)) {
            $this->json(404, null, [
                'code'    => 'TERM_NOT_FOUND',
                'message' => 'ไม่พบภาคเรียนที่ระบุ',
            ]);
            return;
        }

        $passPercent = $this->parsePassPercent($input['pass_percent'] ?? null, $passValid);
        if (!$passValid) {
            $this->json(422, null, [
                'code'    => 'INVALID_PASS_PERCENT',
                'message' => 'pass_percent ต้องเป็นตัวเลขระหว่าง 0 ถึง 100 หรือเว้นว่าง',
            ]);
            return;
        }

        if (!in_array($status, CourseOfferingModel::ALLOWED_STATUSES, true)) {
            $this->json(422, null, [
                'code'    => 'INVALID_STATUS',
                'message' => 'status ต้องเป็น open, closed หรือ archived',
            ]);
            return;
        }

        if ($this->model->offeringExists($pvcId, $termId)) {
            $this->json(409, null, [
                'code'    => 'DUPLICATE_OFFERING',
                'message' => 'รายวิชานี้เปิดสอนในภาคเรียนที่ระบุแล้ว',
            ]);
            return;
        }

        $newId = $this->model->createOffering([
            'program_version_courses_id' => $pvcId,
            'program_versions_id'        => (int)$pvc['program_versions_id'],
            'courses_id'                 => (int)$pvc['courses_id'],
            'academic_terms_id'          => $termId,
            'pass_percent'               => $passPercent,
            'status'                     => $status,
        ]);

        $this->json(201, ['course_offering' => $this->model->findOfferingById($newId)], null);
    }

    // POST /course-offerings/{id} (รองรับ _method=DELETE)
    public function update(int|string $id): void
    {
        Security::requirePermission('course.manage');
        $this->verifyCsrfIfWeb();

        $offeringId = $this->parsePositiveId($id);
        if ($offeringId === null) {
            $this->json(400, null, ['code' => 'INVALID_ID', 'message' => 'รหัสการเปิดสอนต้องเป็นจำนวนเต็มบวก']);
            return;
        }

        $input  = $this->readInput();
        $method = strtoupper(trim((string)($input['_method'] ?? ($_SERVER['REQUEST_METHOD'] ?? 'POST'))));
        if ($method === 'DELETE') {
            $this->destroy($offeringId);
            return;
        }

        $existing = $this->model->findOfferingById($offeringId);
        if (!$existing) {
            $this->json(404, null, ['code' => 'NOT_FOUND', 'message' => 'ไม่พบข้อมูลการเปิดสอน']);
            return;
        }

        $rawPass     = array_key_exists('pass_percent', $input) ? $input['pass_percent'] : $existing['pass_percent'];
        $passPercent = $this->parsePassPercent($rawPass, $passValid);
        if (!$passValid) {
            $this->json(422, null, [
                'code'    => 'INVALID_PASS_PERCENT',
                'message' => 'pass_percent ต้องเป็นตัวเลขระหว่าง 0 ถึง 100 หรือเป็น null',
            ]);
            return;
        }

        $status = trim((string)($input['status'] ?? $existing['status']));
        if (!in_array($status, CourseOfferingModel::ALLOWED_STATUSES, true)) {
            $this->json(422, null, [
                'code'    => 'INVALID_STATUS',
                'message' => 'status ต้องเป็น open, closed หรือ archived',
            ]);
            return;
        }

        $this->model->updateOffering($offeringId, [
            'pass_percent' => $passPercent,
            'status'       => $status,
        ]);

        $this->json(200, ['course_offering' => $this->model->findOfferingById($offeringId)], null);
    }

    // POST /course-offerings/{id}/delete
    public function destroy(int|string $id): void
    {
        Security::requirePermission('course.manage');
        $this->verifyCsrfIfWeb();

        $offeringId = $this->parsePositiveId($id);
        if ($offeringId === null) {
            $this->json(400, null, ['code' => 'INVALID_ID', 'message' => 'รหัสการเปิดสอนต้องเป็นจำนวนเต็มบวก']);
            return;
        }

        if (!$this->model->findOfferingById($offeringId)) {
            $this->json(404, null, ['code' => 'NOT_FOUND', 'message' => 'ไม่พบข้อมูลการเปิดสอน']);
            return;
        }

        if ($this->model->offeringHasSections($offeringId)) {
            $this->json(409, null, [
                'code'    => 'OFFERING_IN_USE',
                'message' => 'ไม่สามารถลบการเปิดสอนได้ เนื่องจากมีกลุ่มเรียน (sections) ผูกอยู่',
            ]);
            return;
        }

        try {
            $this->model->deleteOffering($offeringId);
        } catch (PDOException $e) {
            if ($e->getCode() === '23000') {
                $this->json(409, null, [
                    'code'    => 'OFFERING_IN_USE',
                    'message' => 'ไม่สามารถลบการเปิดสอนได้ เนื่องจากมีข้อมูลอื่นในระบบอ้างอิงอยู่',
                ]);
                return;
            }
            throw $e;
        }

        $this->json(200, ['deleted_id' => $offeringId], null);
    }

    // --- กลุ่มเรียน (sections) ---

    // GET /course-offerings/{id}/sections
    public function sectionIndex(int|string $id): void
    {
        Security::requirePermission('course.view');

        $offeringId = $this->parsePositiveId($id);
        if ($offeringId === null) {
            $this->json(400, null, ['code' => 'INVALID_ID', 'message' => 'รหัสการเปิดสอนต้องเป็นจำนวนเต็มบวก']);
            return;
        }

        if (!$this->model->findOfferingById($offeringId)) {
            $this->json(404, null, ['code' => 'OFFERING_NOT_FOUND', 'message' => 'ไม่พบข้อมูลการเปิดสอน']);
            return;
        }

        $sections = $this->model->getSectionsByOffering($offeringId);
        $this->json(200, ['course_offerings_id' => $offeringId, 'sections' => $sections], null);
    }

    // POST /course-offerings/{id}/sections
    public function sectionStore(int|string $id): void
    {
        Security::requirePermission('course.manage');
        $this->verifyCsrfIfWeb();

        $offeringId = $this->parsePositiveId($id);
        if ($offeringId === null) {
            $this->json(400, null, ['code' => 'INVALID_ID', 'message' => 'รหัสการเปิดสอนต้องเป็นจำนวนเต็มบวก']);
            return;
        }

        if (!$this->model->findOfferingById($offeringId)) {
            $this->json(404, null, ['code' => 'OFFERING_NOT_FOUND', 'message' => 'ไม่พบข้อมูลการเปิดสอน']);
            return;
        }

        $input     = $this->readInput();
        $sectionNo = trim((string)($input['section_no'] ?? ''));

        if ($sectionNo === '' || mb_strlen($sectionNo) > 5) {
            $this->json(422, null, [
                'code'    => 'VALIDATION_ERROR',
                'message' => 'กรุณาระบุเลขกลุ่มเรียน (section_no) ความยาว 1-5 ตัวอักษร',
            ]);
            return;
        }

        if ($this->model->sectionNoExists($offeringId, $sectionNo)) {
            $this->json(409, null, [
                'code'    => 'DUPLICATE_SECTION',
                'message' => 'เลขกลุ่มเรียนนี้มีอยู่แล้วในการเปิดสอนนี้',
            ]);
            return;
        }

        $newId = $this->model->createSection($offeringId, $sectionNo);
        $this->json(201, ['section' => $this->model->findSectionById($newId)], null);
    }

    // POST /sections/{id} (รองรับ _method=DELETE)
    public function sectionUpdate(int|string $id): void
    {
        Security::requirePermission('course.manage');
        $this->verifyCsrfIfWeb();

        $sectionId = $this->parsePositiveId($id);
        if ($sectionId === null) {
            $this->json(400, null, ['code' => 'INVALID_ID', 'message' => 'รหัสกลุ่มเรียนต้องเป็นจำนวนเต็มบวก']);
            return;
        }

        $input  = $this->readInput();
        $method = strtoupper(trim((string)($input['_method'] ?? ($_SERVER['REQUEST_METHOD'] ?? 'POST'))));
        if ($method === 'DELETE') {
            $this->sectionDestroy($sectionId);
            return;
        }

        $existing = $this->model->findSectionById($sectionId);
        if (!$existing) {
            $this->json(404, null, ['code' => 'NOT_FOUND', 'message' => 'ไม่พบข้อมูลกลุ่มเรียน']);
            return;
        }

        $sectionNo = trim((string)($input['section_no'] ?? $existing['section_no']));
        if ($sectionNo === '' || mb_strlen($sectionNo) > 5) {
            $this->json(422, null, [
                'code'    => 'VALIDATION_ERROR',
                'message' => 'เลขกลุ่มเรียน (section_no) ต้องมีความยาว 1-5 ตัวอักษร',
            ]);
            return;
        }

        if ($this->model->sectionNoExists((int)$existing['course_offerings_id'], $sectionNo, $sectionId)) {
            $this->json(409, null, [
                'code'    => 'DUPLICATE_SECTION',
                'message' => 'เลขกลุ่มเรียนซ้ำกับกลุ่มอื่นในการเปิดสอนเดียวกัน',
            ]);
            return;
        }

        $this->model->updateSection($sectionId, $sectionNo);
        $this->json(200, ['section' => $this->model->findSectionById($sectionId)], null);
    }

    // POST /sections/{id}/delete
    public function sectionDestroy(int|string $id): void
    {
        Security::requirePermission('course.manage');
        $this->verifyCsrfIfWeb();

        $sectionId = $this->parsePositiveId($id);
        if ($sectionId === null) {
            $this->json(400, null, ['code' => 'INVALID_ID', 'message' => 'รหัสกลุ่มเรียนต้องเป็นจำนวนเต็มบวก']);
            return;
        }

        if (!$this->model->findSectionById($sectionId)) {
            $this->json(404, null, ['code' => 'NOT_FOUND', 'message' => 'ไม่พบข้อมูลกลุ่มเรียน']);
            return;
        }

        try {
            $this->model->deleteSection($sectionId);
        } catch (PDOException $e) {
            if ($e->getCode() === '23000') {
                $this->json(409, null, [
                    'code'    => 'SECTION_IN_USE',
                    'message' => 'ไม่สามารถลบกลุ่มเรียนได้ เนื่องจากมีสมาชิกหรือผู้สอนผูกอยู่',
                ]);
                return;
            }
            throw $e;
        }

        $this->json(200, ['deleted_id' => $sectionId], null);
    }

    private function parsePassPercent(mixed $raw, ?bool &$isValid): ?float
    {
        if ($raw === null || $raw === '') {
            $isValid = true;
            return null;
        }
        if (!is_numeric($raw)) {
            $isValid = false;
            return null;
        }
        $val = round((float)$raw, 2);
        if ($val < 0.0 || $val > 100.0) {
            $isValid = false;
            return null;
        }
        $isValid = true;
        return $val;
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