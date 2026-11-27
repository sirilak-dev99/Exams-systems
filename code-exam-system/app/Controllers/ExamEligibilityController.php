<?php

declare(strict_types=1);

final class ExamEligibilityController extends Controller
{
    private ExamEligibilityModel $model;

    public function __construct()
    {
        $this->model = new ExamEligibilityModel();
    }

    // GET /exams/{id}/eligibilities/candidates
    public function candidates(int|string $id): void
    {
        Security::requirePermission('exam.manage');

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

        $this->json(200, [
            'exams_id'            => $examId,
            'course_offerings_id' => (int)$exam['course_offerings_id'],
            'candidates'          => $this->model->getOfferingCandidates((int)$exam['course_offerings_id']),
        ], null);
    }

    // GET /exams/{id}/eligibilities
    public function index(int|string $id): void
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

        $this->json(200, [
            'exams_id'      => $examId,
            'eligibilities' => $this->model->listEligibilitiesByExam($examId),
        ], null);
    }

    // POST /exams/{id}/eligibilities
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

        $input     = $this->readInput();
        $userId    = $this->parsePositiveId($input['users_id'] ?? $input['user_id'] ?? '');
        $sectionId = $this->parsePositiveId($input['sections_id'] ?? $input['section_id'] ?? '');
        if ($userId === null || $sectionId === null) {
            $this->json(422, null, ['code' => 'VALIDATION_ERROR', 'message' => 'กรุณาระบุ users_id และ sections_id']);
            return;
        }

        $offeringId = (int)$exam['course_offerings_id'];
        if (!$this->model->examSectionExists($examId, $sectionId, $offeringId)) {
            $this->json(422, null, [
                'code'    => 'SECTION_NOT_ASSIGNED_TO_EXAM',
                'message' => 'กลุ่มเรียนนี้ยังไม่ได้ถูกผูกเข้ากับชุดข้อสอบ (exam_sections)',
            ]);
            return;
        }

        if (!$this->model->isEnrolledCourseMember($userId, $sectionId, $offeringId)) {
            $this->json(422, null, [
                'code'    => 'NOT_COURSE_MEMBER',
                'message' => 'นิสิตไม่ได้อยู่ในรายชื่อ course_members (enrolled) ของกลุ่มเรียนนี้',
            ]);
            return;
        }

        $customOpen  = isset($input['custom_open_at']) && trim((string)$input['custom_open_at']) !== '' ? trim((string)$input['custom_open_at']) : null;
        $customClose = isset($input['custom_close_at']) && trim((string)$input['custom_close_at']) !== '' ? trim((string)$input['custom_close_at']) : null;
        if ($customOpen !== null && $customClose !== null && strtotime($customClose) <= strtotime($customOpen)) {
            $this->json(422, null, ['code' => 'INVALID_CUSTOM_DATES', 'message' => 'custom_close_at ต้องมากกว่า custom_open_at']);
            return;
        }

        $createDefaultGrant = array_key_exists('create_default_grant', $input) ? (bool)$input['create_default_grant'] : true;
        $maxAttempts = array_key_exists('max_attempts', $input) && $input['max_attempts'] !== null && $input['max_attempts'] !== ''
            ? $this->parsePositiveId($input['max_attempts'])
            : (int)$exam['max_attempts'];
        if ($maxAttempts !== null && $maxAttempts < 1) {
            $this->json(422, null, ['code' => 'INVALID_MAX_ATTEMPTS', 'message' => 'max_attempts ต้องไม่น้อยกว่า 1']);
            return;
        }

        $note = isset($input['note']) && trim((string)$input['note']) !== '' ? trim((string)$input['note']) : null;

        $result = $this->model->upsertEligibilityWithGrant(
            $examId,
            $userId,
            $sectionId,
            $offeringId,
            $customOpen,
            $customClose,
            $note,
            $createDefaultGrant,
            $maxAttempts,
            $this->currentUserId()
        );

        $this->json(201, $result, null);
    }

    // POST /eligibilities/{id}/grants
    public function addGrant(int|string $id): void
    {
        Security::requirePermission('exam.manage');
        $this->verifyCsrfIfWeb();

        $eligibilityId = $this->parsePositiveId($id);
        if ($eligibilityId === null) {
            $this->json(400, null, ['code' => 'INVALID_ID', 'message' => 'รหัสสิทธิ์ผู้เข้าสอบต้องเป็นจำนวนเต็มบวก']);
            return;
        }

        $elig = $this->model->findEligibilityById($eligibilityId);
        if (!$elig) {
            $this->json(404, null, ['code' => 'ELIGIBILITY_NOT_FOUND', 'message' => 'ไม่พบสิทธิ์ผู้เข้าสอบที่ระบุ']);
            return;
        }

        if ($elig['status'] !== 'eligible') {
            $this->json(409, null, ['code' => 'ELIGIBILITY_REVOKED', 'message' => 'ไม่สามารถเพิ่ม Grant ให้สิทธิ์ที่ถูกยกเลิกแล้วได้']);
            return;
        }

        $input = $this->readInput();
        $grantType   = trim((string)($input['grant_type'] ?? 'make_up'));
        $grantSource = trim((string)($input['grant_source'] ?? 'direct'));
        $openAt      = isset($input['open_at']) && trim((string)$input['open_at']) !== '' ? trim((string)$input['open_at']) : null;
        $closeAt     = isset($input['close_at']) && trim((string)$input['close_at']) !== '' ? trim((string)$input['close_at']) : null;
        $maxAttempts = array_key_exists('max_attempts', $input) && $input['max_attempts'] !== null && $input['max_attempts'] !== ''
            ? $this->parsePositiveId($input['max_attempts'])
            : 1;
        $graceMin = array_key_exists('grace_period_min', $input) && $input['grace_period_min'] !== null && $input['grace_period_min'] !== ''
            ? max(0, (int)$input['grace_period_min'])
            : null;
        $reason = isset($input['reason']) && trim((string)$input['reason']) !== '' ? trim((string)$input['reason']) : null;

        if (!in_array($grantType, ExamEligibilityModel::ALLOWED_GRANT_TYPES, true)
            || !in_array($grantSource, ExamEligibilityModel::ALLOWED_GRANT_SOURCES, true)
        ) {
            $this->json(422, null, ['code' => 'VALIDATION_ERROR', 'message' => 'grant_type หรือ grant_source ไม่ถูกต้อง']);
            return;
        }

        if ($openAt !== null && $closeAt !== null && strtotime($closeAt) <= strtotime($openAt)) {
            $this->json(422, null, ['code' => 'INVALID_TIME_WINDOW', 'message' => 'close_at ต้องมากกว่า open_at']);
            return;
        }

        if ($maxAttempts === null || $maxAttempts < 1) {
            $this->json(422, null, ['code' => 'INVALID_MAX_ATTEMPTS', 'message' => 'max_attempts ต้องไม่น้อยกว่า 1']);
            return;
        }

        $grant = $this->model->createGrant($eligibilityId, [
            'grant_type'       => $grantType,
            'open_at'          => $openAt,
            'close_at'         => $closeAt,
            'max_attempts'     => $maxAttempts,
            'grace_period_min' => $graceMin,
            'grant_source'     => $grantSource,
            'reason'           => $reason,
            'approved_by'      => $this->currentUserId(),
        ]);

        $this->json(201, ['grant' => $grant], null);
    }

    // POST /eligibilities/{id}/revoke
    public function revoke(int|string $id): void
    {
        Security::requirePermission('exam.manage');
        $this->verifyCsrfIfWeb();

        $eligibilityId = $this->parsePositiveId($id);
        if ($eligibilityId === null) {
            $this->json(400, null, ['code' => 'INVALID_ID', 'message' => 'รหัสสิทธิ์ผู้เข้าสอบต้องเป็นจำนวนเต็มบวก']);
            return;
        }

        if (!$this->model->findEligibilityById($eligibilityId)) {
            $this->json(404, null, ['code' => 'ELIGIBILITY_NOT_FOUND', 'message' => 'ไม่พบสิทธิ์ผู้เข้าสอบที่ระบุ']);
            return;
        }

        $input  = $this->readInput();
        $reason = isset($input['reason']) && trim((string)$input['reason']) !== '' ? trim((string)$input['reason']) : null;

        $result = $this->model->revokeEligibility($eligibilityId, $this->currentUserId(), $reason);
        $this->json(200, $result, null);
    }

    // POST /eligibility-grants/{id}/revoke
    public function revokeGrant(int|string $id): void
    {
        Security::requirePermission('exam.manage');
        $this->verifyCsrfIfWeb();

        $grantId = $this->parsePositiveId($id);
        if ($grantId === null) {
            $this->json(400, null, ['code' => 'INVALID_ID', 'message' => 'รหัส Grant ต้องเป็นจำนวนเต็มบวก']);
            return;
        }

        if (!$this->model->findGrantById($grantId)) {
            $this->json(404, null, ['code' => 'GRANT_NOT_FOUND', 'message' => 'ไม่พบ Grant ที่ระบุ']);
            return;
        }

        $input  = $this->readInput();
        $reason = isset($input['reason']) && trim((string)$input['reason']) !== '' ? trim((string)$input['reason']) : null;

        $grant = $this->model->revokeGrant($grantId, $this->currentUserId(), $reason);
        $this->json(200, ['grant' => $grant], null);
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