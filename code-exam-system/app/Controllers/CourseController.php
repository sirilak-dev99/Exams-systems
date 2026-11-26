<?php

declare(strict_types=1);

final class CourseController extends Controller
{
    private CourseModel $courseModel;

    public function __construct()
    {
        $this->courseModel = new CourseModel();
    }

    // --- กลุ่มวิชา ---

    // GET /course-groups
    public function groupIndex(): void
    {
        Security::requirePermission('course.manage');

        $groups = $this->courseModel->getAllGroups($_GET['q'] ?? null);

        if (Security::wantsJson()) {
            $this->json(200, ['course_groups' => $groups], null);
            return;
        }

        $this->view('courses/groups', [
            'pageTitle'    => 'จัดการกลุ่มวิชา',
            'courseGroups' => $groups,
        ]);
    }

    // POST /course-groups
    public function groupStore(): void
    {
        Security::requirePermission('course.manage');
        $this->verifyCsrfIfWeb();

        $input = $this->readInput();
        $name  = trim((string)($input['name'] ?? ''));

        if ($name === '') {
            $this->json(422, null, ['code' => 'VALIDATION_ERROR', 'message' => 'กรุณากรอกชื่อกลุ่มวิชา']);
            return;
        }

        if ($this->courseModel->groupNameExists($name)) {
            $this->json(409, null, ['code' => 'DUPLICATE_GROUP', 'message' => 'ชื่อกลุ่มวิชานี้มีอยู่ในระบบแล้ว']);
            return;
        }

        $newId = $this->courseModel->createGroup($name);
        $this->json(201, ['course_group' => $this->courseModel->findGroupById($newId)], null);
    }

    // POST /course-groups/{id}
    public function groupUpdate(int|string $id): void
    {
        Security::requirePermission('course.manage');
        $this->verifyCsrfIfWeb();

        $groupId = $this->parsePositiveId($id);
        if ($groupId === null) {
            $this->json(400, null, ['code' => 'INVALID_ID', 'message' => 'รหัสกลุ่มวิชาต้องเป็นจำนวนเต็มบวก']);
            return;
        }

        $input  = $this->readInput();
        $method = strtoupper(trim((string)($input['_method'] ?? ($_SERVER['REQUEST_METHOD'] ?? 'POST'))));
        if ($method === 'DELETE') {
            $this->groupDestroy($groupId);
            return;
        }

        $existing = $this->courseModel->findGroupById($groupId);
        if (!$existing) {
            $this->json(404, null, ['code' => 'NOT_FOUND', 'message' => 'ไม่พบข้อมูลกลุ่มวิชา']);
            return;
        }

        $name = trim((string)($input['name'] ?? $existing['name']));
        if ($name === '') {
            $this->json(422, null, ['code' => 'VALIDATION_ERROR', 'message' => 'ชื่อกลุ่มวิชาห้ามว่าง']);
            return;
        }

        if ($this->courseModel->groupNameExists($name, $groupId)) {
            $this->json(409, null, ['code' => 'DUPLICATE_GROUP', 'message' => 'ชื่อกลุ่มวิชาซ้ำกับรายการอื่น']);
            return;
        }

        $this->courseModel->updateGroup($groupId, $name);
        $this->json(200, ['course_group' => $this->courseModel->findGroupById($groupId)], null);
    }

    // POST /course-groups/{id}/delete หรือ _method=DELETE
    public function groupDestroy(int|string $id): void
    {
        Security::requirePermission('course.manage');
        $this->verifyCsrfIfWeb();

        $groupId = $this->parsePositiveId($id);
        if ($groupId === null) {
            $this->json(400, null, ['code' => 'INVALID_ID', 'message' => 'รหัสกลุ่มวิชาต้องเป็นจำนวนเต็มบวก']);
            return;
        }

        if (!$this->courseModel->findGroupById($groupId)) {
            $this->json(404, null, ['code' => 'NOT_FOUND', 'message' => 'ไม่พบข้อมูลกลุ่มวิชา']);
            return;
        }

        if ($this->courseModel->groupHasCourses($groupId)) {
            $this->json(409, null, [
                'code'    => 'GROUP_IN_USE',
                'message' => 'ไม่สามารถลบกลุ่มวิชาได้เนื่องจากมีรายวิชาที่ใช้งานอยู่',
            ]);
            return;
        }

        $this->courseModel->deleteGroup($groupId);
        $this->json(200, ['deleted_id' => $groupId], null);
    }

    // --- รายวิชา ---

    // GET /courses
    public function index(): void
    {
        Security::requirePermission('course.view');

        $courses = $this->courseModel->getAllCourses([
            'course_groups_id' => $_GET['course_groups_id'] ?? null,
            'q'                => $_GET['q'] ?? null,
        ]);

        if (Security::wantsJson()) {
            $this->json(200, ['courses' => $courses], null);
            return;
        }

        $this->view('courses/index', [
            'pageTitle' => 'จัดการรายวิชา',
            'courses'   => $courses,
        ]);
    }

    // POST /courses
    public function store(): void
    {
        Security::requirePermission('course.manage');
        $this->verifyCsrfIfWeb();

        $input   = $this->readInput();
        $groupId = $this->parsePositiveId($input['course_groups_id'] ?? '');
        $code    = strtoupper(trim((string)($input['code'] ?? '')));
        $name    = trim((string)($input['name'] ?? ''));

        if ($groupId === null || $code === '' || $name === '') {
            $this->json(422, null, [
                'code'    => 'VALIDATION_ERROR',
                'message' => 'กรุณาระบุกลุ่มวิชา (course_groups_id), รหัสวิชา (code) และชื่อวิชา (name)',
            ]);
            return;
        }

        if (!$this->courseModel->findGroupById($groupId)) {
            $this->json(404, null, ['code' => 'GROUP_NOT_FOUND', 'message' => 'ไม่พบกลุ่มวิชาที่ระบุ']);
            return;
        }

        if ($this->courseModel->courseCodeExists($code)) {
            $this->json(409, null, ['code' => 'DUPLICATE_COURSE_CODE', 'message' => 'รหัสวิชานี้มีอยู่ในระบบแล้ว']);
            return;
        }

        $creatorId = $this->resolveCreatorId($input);
        if ($creatorId === null || !$this->courseModel->userExists($creatorId)) {
            $this->json(422, null, ['code' => 'INVALID_CREATOR', 'message' => 'ไม่พบข้อมูลผู้สร้างรายวิชา (created_by) ในระบบ']);
            return;
        }

        $newId = $this->courseModel->createCourse([
            'course_groups_id' => $groupId,
            'created_by'       => $creatorId,
            'code'             => $code,
            'name'             => $name,
        ]);

        $this->json(201, ['course' => $this->courseModel->findCourseById($newId)], null);
    }

    // POST /courses/{id}
    public function update(int|string $id): void
    {
        Security::requirePermission('course.manage');
        $this->verifyCsrfIfWeb();

        $courseId = $this->parsePositiveId($id);
        if ($courseId === null) {
            $this->json(400, null, ['code' => 'INVALID_ID', 'message' => 'รหัสรายวิชาต้องเป็นจำนวนเต็มบวก']);
            return;
        }

        $input  = $this->readInput();
        $method = strtoupper(trim((string)($input['_method'] ?? ($_SERVER['REQUEST_METHOD'] ?? 'POST'))));
        if ($method === 'DELETE') {
            $this->destroy($courseId);
            return;
        }

        $existing = $this->courseModel->findCourseById($courseId);
        if (!$existing) {
            $this->json(404, null, ['code' => 'NOT_FOUND', 'message' => 'ไม่พบข้อมูลรายวิชา']);
            return;
        }

        $groupId = isset($input['course_groups_id'])
            ? $this->parsePositiveId($input['course_groups_id'])
            : (int)$existing['course_groups_id'];
        $code    = strtoupper(trim((string)($input['code'] ?? $existing['code'])));
        $name    = trim((string)($input['name'] ?? $existing['name']));

        if ($groupId === null || $code === '' || $name === '') {
            $this->json(422, null, ['code' => 'VALIDATION_ERROR', 'message' => 'ข้อมูลกลุ่มวิชา รหัสวิชา และชื่อวิชาต้องครบถ้วน']);
            return;
        }

        if (!$this->courseModel->findGroupById($groupId)) {
            $this->json(404, null, ['code' => 'GROUP_NOT_FOUND', 'message' => 'ไม่พบกลุ่มวิชาที่ระบุ']);
            return;
        }

        if ($this->courseModel->courseCodeExists($code, $courseId)) {
            $this->json(409, null, ['code' => 'DUPLICATE_COURSE_CODE', 'message' => 'รหัสวิชาซ้ำกับรายวิชาอื่น']);
            return;
        }

        $this->courseModel->updateCourse($courseId, [
            'course_groups_id' => $groupId,
            'code'             => $code,
            'name'             => $name,
        ]);

        $this->json(200, ['course' => $this->courseModel->findCourseById($courseId)], null);
    }

    // POST /courses/{id}/delete หรือ _method=DELETE (Soft Delete)
    public function destroy(int|string $id): void
    {
        Security::requirePermission('course.manage');
        $this->verifyCsrfIfWeb();

        $courseId = $this->parsePositiveId($id);
        if ($courseId === null) {
            $this->json(400, null, ['code' => 'INVALID_ID', 'message' => 'รหัสรายวิชาต้องเป็นจำนวนเต็มบวก']);
            return;
        }

        if (!$this->courseModel->findCourseById($courseId)) {
            $this->json(404, null, ['code' => 'NOT_FOUND', 'message' => 'ไม่พบข้อมูลรายวิชา']);
            return;
        }

        $this->courseModel->softDeleteCourse($courseId);
        $this->json(200, ['deleted_id' => $courseId, 'soft_deleted' => true], null);
    }

    private function resolveCreatorId(array $input): ?int
    {
        $sessionUserId = $_SESSION['user']['id_users']
            ?? $_SESSION['user']['id']
            ?? $_SESSION['user_id']
            ?? null;

        if ($sessionUserId !== null) {
            return $this->parsePositiveId($sessionUserId);
        }

        if (isset($input['created_by'])) {
            return $this->parsePositiveId($input['created_by']);
        }

        return null;
    }

    private function parsePositiveId(mixed $rawId): ?int
    {
        if (is_int($rawId)) {
            return $rawId > 0 ? $rawId : null;
        }
        if (!is_string($rawId) && !is_numeric($rawId)) {
            return null;
        }
        $trimmed = trim((string)$rawId);
        if ($trimmed === '' || !ctype_digit($trimmed)) {
            return null;
        }
        $value = (int)$trimmed;
        return $value > 0 ? $value : null;
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