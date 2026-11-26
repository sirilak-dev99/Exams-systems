<?php

declare(strict_types=1);

final class SectionMemberController extends Controller
{
    private SectionMemberModel $model;

    public function __construct()
    {
        $this->model = new SectionMemberModel();
    }

    // --- สมาชิกนิสิตในกลุ่มเรียน (course_members) ---

    // GET /sections/{id}/members
    public function memberIndex(int|string $id): void
    {
        Security::requirePermission('course.view');

        $sectionId = $this->parsePositiveId($id);
        if ($sectionId === null) {
            $this->json(400, null, ['code' => 'INVALID_ID', 'message' => 'รหัสกลุ่มเรียนต้องเป็นจำนวนเต็มบวก']);
            return;
        }

        if (!$this->model->findSectionContext($sectionId)) {
            $this->json(404, null, ['code' => 'SECTION_NOT_FOUND', 'message' => 'ไม่พบข้อมูลกลุ่มเรียน']);
            return;
        }

        $members = $this->model->getMembersBySection($sectionId, $_GET['enrollment_status'] ?? null);
        $this->json(200, ['section_id' => $sectionId, 'members' => $members], null);
    }

    // POST /sections/{id}/members
    public function memberStore(int|string $id): void
    {
        Security::requirePermission('course.manage');
        $this->verifyCsrfIfWeb();

        $sectionId = $this->parsePositiveId($id);
        if ($sectionId === null) {
            $this->json(400, null, ['code' => 'INVALID_ID', 'message' => 'รหัสกลุ่มเรียนต้องเป็นจำนวนเต็มบวก']);
            return;
        }

        $section = $this->model->findSectionContext($sectionId);
        if (!$section) {
            $this->json(404, null, ['code' => 'SECTION_NOT_FOUND', 'message' => 'ไม่พบข้อมูลกลุ่มเรียน']);
            return;
        }

        $input        = $this->readInput();
        $userId       = $this->parsePositiveId($input['user_id'] ?? '');
        $enrollmentId = isset($input['enrollments_id']) && $input['enrollments_id'] !== ''
            ? $this->parsePositiveId($input['enrollments_id'])
            : null;

        if ($userId === null) {
            $this->json(422, null, ['code' => 'VALIDATION_ERROR', 'message' => 'กรุณาระบุ user_id ของนิสิต']);
            return;
        }

        $user = $this->model->findActiveUser($userId);
        if (!$user || (int)$user['active'] !== 1) {
            $this->json(404, null, ['code' => 'USER_NOT_FOUND', 'message' => 'ไม่พบบัญชีนิสิต หรือบัญชีถูกระงับการใช้งาน']);
            return;
        }

        $pvId       = (int)$section['program_versions_id'];
        $offeringId = (int)$section['course_offerings_id'];

        $enrollment = $this->model->findStudentEnrollment($userId, $pvId, $enrollmentId);
        if (!$enrollment) {
            $this->json(422, null, [
                'code'    => 'ENROLLMENT_REQUIRED',
                'message' => 'นิสิตยังไม่มีข้อมูล student_program_enrollments ในรุ่นหลักสูตรของรายวิชานี้',
            ]);
            return;
        }

        if ($enrollment['status'] !== 'studying') {
            $this->json(422, null, [
                'code'    => 'ENROLLMENT_NOT_STUDYING',
                'message' => 'สถานะการลงทะเบียนหลักสูตรของนิสิตไม่ใช่ studying',
            ]);
            return;
        }

        $existingMember = $this->model->findMemberByUserOffering($userId, $offeringId);
        if ($existingMember) {
            $this->json(409, null, [
                'code'    => 'DUPLICATE_MEMBER',
                'message' => 'นิสิตคนนี้มีประวัติอยู่ในรายวิชาที่เปิดสอนนี้แล้ว (หากเคยถอนออกให้ใช้อัปเดตสถานะเป็น enrolled แทน)',
            ]);
            return;
        }

        $newId = $this->model->addMember([
            'user_id'             => $userId,
            'enrollments_id'      => (int)$enrollment['id_enrollments'],
            'program_versions_id' => $pvId,
            'section_id'          => $sectionId,
            'course_offerings_id' => $offeringId,
        ]);

        $this->json(201, ['member' => $this->model->findMemberById($newId)], null);
    }

    // POST /course-members/{id} (รองรับย้ายกลุ่ม, เปลี่ยนสถานะ และ _method=DELETE เพื่อถอนออกโดยเก็บประวัติ)
    public function memberUpdate(int|string $id): void
    {
        Security::requirePermission('course.manage');
        $this->verifyCsrfIfWeb();

        $memberId = $this->parsePositiveId($id);
        if ($memberId === null) {
            $this->json(400, null, ['code' => 'INVALID_ID', 'message' => 'รหัสสมาชิกต้องเป็นจำนวนเต็มบวก']);
            return;
        }

        $input  = $this->readInput();
        $method = strtoupper(trim((string)($input['_method'] ?? ($_SERVER['REQUEST_METHOD'] ?? 'POST'))));
        if ($method === 'DELETE') {
            $this->memberDrop($memberId);
            return;
        }

        $existing = $this->model->findMemberById($memberId);
        if (!$existing) {
            $this->json(404, null, ['code' => 'NOT_FOUND', 'message' => 'ไม่พบข้อมูลสมาชิกในกลุ่มเรียน']);
            return;
        }

        $newSectionId = isset($input['section_id'])
            ? $this->parsePositiveId($input['section_id'])
            : (int)$existing['section_id'];

        if ($newSectionId === null) {
            $this->json(422, null, ['code' => 'INVALID_SECTION', 'message' => 'section_id ไม่ถูกต้อง']);
            return;
        }

        if ($newSectionId !== (int)$existing['section_id']) {
            $targetSection = $this->model->findSectionContext($newSectionId);
            if (!$targetSection || (int)$targetSection['course_offerings_id'] !== (int)$existing['course_offerings_id']) {
                $this->json(422, null, [
                    'code'    => 'SECTION_OFFERING_MISMATCH',
                    'message' => 'กลุ่มเรียนปลายทางต้องอยู่ในการเปิดสอน (course_offerings) เดียวกัน',
                ]);
                return;
            }
        }

        $status = trim((string)($input['enrollment_status'] ?? $existing['enrollment_status']));
        if (!in_array($status, SectionMemberModel::ALLOWED_MEMBER_STATUSES, true)) {
            $this->json(422, null, [
                'code'    => 'INVALID_STATUS',
                'message' => 'enrollment_status ต้องเป็น enrolled, dropped หรือ completed',
            ]);
            return;
        }

        $this->model->updateMember($memberId, $newSectionId, $status);
        $this->json(200, ['member' => $this->model->findMemberById($memberId)], null);
    }

    // POST /course-members/{id}/drop (นำนิสิตออกจากกลุ่มโดยไม่ลบประวัติ)
    public function memberDrop(int|string $id): void
    {
        Security::requirePermission('course.manage');
        $this->verifyCsrfIfWeb();

        $memberId = $this->parsePositiveId($id);
        if ($memberId === null) {
            $this->json(400, null, ['code' => 'INVALID_ID', 'message' => 'รหัสสมาชิกต้องเป็นจำนวนเต็มบวก']);
            return;
        }

        $existing = $this->model->findMemberById($memberId);
        if (!$existing) {
            $this->json(404, null, ['code' => 'NOT_FOUND', 'message' => 'ไม่พบข้อมูลสมาชิกในกลุ่มเรียน']);
            return;
        }

        if ($existing['enrollment_status'] === 'dropped') {
            $this->json(409, null, ['code' => 'ALREADY_DROPPED', 'message' => 'นิสิตคนนี้ถูกนำออกจากกลุ่มเรียนไปแล้ว']);
            return;
        }

        $this->model->dropMember($memberId);
        $this->json(200, ['member' => $this->model->findMemberById($memberId)], null);
    }

    // --- ผู้รับผิดชอบ / ผู้สอนประจำกลุ่มเรียน (section_staff) ---

    // GET /sections/{id}/staff
    public function staffIndex(int|string $id): void
    {
        Security::requirePermission('course.view');

        $sectionId = $this->parsePositiveId($id);
        if ($sectionId === null) {
            $this->json(400, null, ['code' => 'INVALID_ID', 'message' => 'รหัสกลุ่มเรียนต้องเป็นจำนวนเต็มบวก']);
            return;
        }

        if (!$this->model->findSectionContext($sectionId)) {
            $this->json(404, null, ['code' => 'SECTION_NOT_FOUND', 'message' => 'ไม่พบข้อมูลกลุ่มเรียน']);
            return;
        }

        $staff = $this->model->getStaffBySection($sectionId);
        $this->json(200, ['section_id' => $sectionId, 'staff' => $staff], null);
    }

    // POST /sections/{id}/staff
    public function staffStore(int|string $id): void
    {
        Security::requirePermission('course.manage');
        $this->verifyCsrfIfWeb();

        $sectionId = $this->parsePositiveId($id);
        if ($sectionId === null) {
            $this->json(400, null, ['code' => 'INVALID_ID', 'message' => 'รหัสกลุ่มเรียนต้องเป็นจำนวนเต็มบวก']);
            return;
        }

        if (!$this->model->findSectionContext($sectionId)) {
            $this->json(404, null, ['code' => 'SECTION_NOT_FOUND', 'message' => 'ไม่พบข้อมูลกลุ่มเรียน']);
            return;
        }

        $input  = $this->readInput();
        $userId = $this->parsePositiveId($input['user_id'] ?? '');
        $role   = trim((string)($input['staff_role'] ?? 'teacher'));

        if ($userId === null) {
            $this->json(422, null, ['code' => 'VALIDATION_ERROR', 'message' => 'กรุณาระบุ user_id ของผู้สอน/ผู้ช่วยสอน']);
            return;
        }

        if (!in_array($role, SectionMemberModel::ALLOWED_STAFF_ROLES, true)) {
            $this->json(422, null, [
                'code'    => 'INVALID_STAFF_ROLE',
                'message' => 'staff_role ต้องเป็น teacher, assistant หรือ coordinator',
            ]);
            return;
        }

        $user = $this->model->findActiveUser($userId);
        if (!$user || (int)$user['active'] !== 1) {
            $this->json(404, null, ['code' => 'USER_NOT_FOUND', 'message' => 'ไม่พบบัญชีผู้ใช้ หรือบัญชีถูกระงับการใช้งาน']);
            return;
        }

        if ($this->model->staffExists($userId, $sectionId)) {
            $this->json(409, null, ['code' => 'DUPLICATE_STAFF', 'message' => 'ผู้ใช้นี้เป็นผู้รับผิดชอบในกลุ่มเรียนนี้อยู่แล้ว']);
            return;
        }

        $newId = $this->model->addStaff($userId, $sectionId, $role);
        $this->json(201, ['staff' => $this->model->findStaffById($newId)], null);
    }

    // POST /section-staff/{id} (รองรับ _method=DELETE)
    public function staffUpdate(int|string $id): void
    {
        Security::requirePermission('course.manage');
        $this->verifyCsrfIfWeb();

        $staffId = $this->parsePositiveId($id);
        if ($staffId === null) {
            $this->json(400, null, ['code' => 'INVALID_ID', 'message' => 'รหัสผู้รับผิดชอบต้องเป็นจำนวนเต็มบวก']);
            return;
        }

        $input  = $this->readInput();
        $method = strtoupper(trim((string)($input['_method'] ?? ($_SERVER['REQUEST_METHOD'] ?? 'POST'))));
        if ($method === 'DELETE') {
            $this->staffDestroy($staffId);
            return;
        }

        $existing = $this->model->findStaffById($staffId);
        if (!$existing) {
            $this->json(404, null, ['code' => 'NOT_FOUND', 'message' => 'ไม่พบข้อมูลผู้รับผิดชอบในกลุ่มเรียน']);
            return;
        }

        $role = trim((string)($input['staff_role'] ?? $existing['staff_role']));
        if (!in_array($role, SectionMemberModel::ALLOWED_STAFF_ROLES, true)) {
            $this->json(422, null, [
                'code'    => 'INVALID_STAFF_ROLE',
                'message' => 'staff_role ต้องเป็น teacher, assistant หรือ coordinator',
            ]);
            return;
        }

        $this->model->updateStaffRole($staffId, $role);
        $this->json(200, ['staff' => $this->model->findStaffById($staffId)], null);
    }

    // POST /section-staff/{id}/delete
    public function staffDestroy(int|string $id): void
    {
        Security::requirePermission('course.manage');
        $this->verifyCsrfIfWeb();

        $staffId = $this->parsePositiveId($id);
        if ($staffId === null) {
            $this->json(400, null, ['code' => 'INVALID_ID', 'message' => 'รหัสผู้รับผิดชอบต้องเป็นจำนวนเต็มบวก']);
            return;
        }

        if (!$this->model->findStaffById($staffId)) {
            $this->json(404, null, ['code' => 'NOT_FOUND', 'message' => 'ไม่พบข้อมูลผู้รับผิดชอบในกลุ่มเรียน']);
            return;
        }

        $this->model->deleteStaff($staffId);
        $this->json(200, ['deleted_id' => $staffId], null);
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