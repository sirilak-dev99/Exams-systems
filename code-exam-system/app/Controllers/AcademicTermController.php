<?php

declare(strict_types=1);

final class AcademicTermController extends Controller
{
    private AcademicTermModel $model;

    public function __construct()
    {
        $this->model = new AcademicTermModel();
    }

    // GET /academic-terms
    public function index(): void
    {
        Security::requirePermission('course.view');

        $terms = $this->model->getAll([
            'status'        => $_GET['status'] ?? null,
            'academic_year' => $_GET['academic_year'] ?? null,
        ]);

        if (Security::wantsJson()) {
            $this->json(200, ['academic_terms' => $terms], null);
            return;
        }

        $this->view('academic_terms/index', [
            'pageTitle'     => 'จัดการภาคเรียน',
            'academicTerms' => $terms,
        ]);
    }

    // POST /academic-terms
    public function store(): void
    {
        Security::requirePermission('program.manage');
        $this->verifyCsrfIfWeb();

        $input = $this->readInput();
        $validated = $this->validateTermPayload($input);
        if (isset($validated['error'])) {
            $this->json(422, null, $validated['error']);
            return;
        }

        if ($this->model->termExists($validated['academic_year'], $validated['term_no'])) {
            $this->json(409, null, ['code' => 'DUPLICATE_TERM', 'message' => 'ปีการศึกษาและภาคเรียนนี้มีอยู่ในระบบแล้ว']);
            return;
        }

        $newId = $this->model->create($validated);
        $this->json(201, ['academic_term' => $this->model->findById($newId)], null);
    }

    // POST /academic-terms/{id} (รองรับ _method=DELETE)
    public function update(int|string $id): void
    {
        Security::requirePermission('program.manage');
        $this->verifyCsrfIfWeb();

        $termId = $this->parsePositiveId($id);
        if ($termId === null) {
            $this->json(400, null, ['code' => 'INVALID_ID', 'message' => 'รหัสภาคเรียนต้องเป็นจำนวนเต็มบวก']);
            return;
        }

        $input  = $this->readInput();
        $method = strtoupper(trim((string)($input['_method'] ?? ($_SERVER['REQUEST_METHOD'] ?? 'POST'))));
        if ($method === 'DELETE') {
            $this->destroy($termId);
            return;
        }

        $existing = $this->model->findById($termId);
        if (!$existing) {
            $this->json(404, null, ['code' => 'NOT_FOUND', 'message' => 'ไม่พบข้อมูลภาคเรียน']);
            return;
        }

        $merged = [
            'academic_year' => $input['academic_year'] ?? $existing['academic_year'],
            'term_no'       => $input['term_no'] ?? $existing['term_no'],
            'start_date'    => array_key_exists('start_date', $input) ? $input['start_date'] : $existing['start_date'],
            'end_date'      => array_key_exists('end_date', $input) ? $input['end_date'] : $existing['end_date'],
            'status'        => $input['status'] ?? $existing['status'],
        ];

        $validated = $this->validateTermPayload($merged);
        if (isset($validated['error'])) {
            $this->json(422, null, $validated['error']);
            return;
        }

        if ($this->model->termExists($validated['academic_year'], $validated['term_no'], $termId)) {
            $this->json(409, null, ['code' => 'DUPLICATE_TERM', 'message' => 'ปีการศึกษาและภาคเรียนซ้ำกับรายการอื่น']);
            return;
        }

        $this->model->update($termId, $validated);
        $this->json(200, ['academic_term' => $this->model->findById($termId)], null);
    }

    // POST /academic-terms/{id}/delete
    public function destroy(int|string $id): void
    {
        Security::requirePermission('program.manage');
        $this->verifyCsrfIfWeb();

        $termId = $this->parsePositiveId($id);
        if ($termId === null) {
            $this->json(400, null, ['code' => 'INVALID_ID', 'message' => 'รหัสภาคเรียนต้องเป็นจำนวนเต็มบวก']);
            return;
        }

        if (!$this->model->findById($termId)) {
            $this->json(404, null, ['code' => 'NOT_FOUND', 'message' => 'ไม่พบข้อมูลภาคเรียน']);
            return;
        }

        if ($this->model->hasDependencies($termId)) {
            $this->json(409, null, [
                'code'    => 'TERM_IN_USE',
                'message' => 'ไม่สามารถลบภาคเรียนได้เนื่องจากมีข้อมูลการเปิดสอนหรือประวัติชั้นปีผูกอยู่ แนะนำให้เปลี่ยนสถานะเป็น archived แทน',
            ]);
            return;
        }

        $this->model->delete($termId);
        $this->json(200, ['deleted_id' => $termId], null);
    }

    private function validateTermPayload(array $input): array
    {
        $academicYear = $this->parsePositiveId($input['academic_year'] ?? '');
        $termNo       = $this->parsePositiveId($input['term_no'] ?? '');
        $status       = trim((string)($input['status'] ?? 'active'));

        $startDate = isset($input['start_date']) && trim((string)$input['start_date']) !== ''
            ? trim((string)$input['start_date'])
            : null;
        $endDate   = isset($input['end_date']) && trim((string)$input['end_date']) !== ''
            ? trim((string)$input['end_date'])
            : null;

        if ($academicYear === null || $academicYear < 2500) {
            return ['error' => ['code' => 'INVALID_YEAR', 'message' => 'ปีการศึกษา (academic_year) ต้องเป็น พ.ศ. ตั้งแต่ 2500 ขึ้นไป']];
        }

        if ($termNo === null || !in_array($termNo, AcademicTermModel::ALLOWED_TERM_NOS, true)) {
            return ['error' => ['code' => 'INVALID_TERM_NO', 'message' => 'ภาคเรียน (term_no) ต้องเป็น 1, 2 หรือ 3 เท่านั้น']];
        }

        if ($startDate !== null && !$this->isValidDate($startDate)) {
            return ['error' => ['code' => 'INVALID_DATE', 'message' => 'รูปแบบ start_date ต้องเป็น YYYY-MM-DD']];
        }

        if ($endDate !== null && !$this->isValidDate($endDate)) {
            return ['error' => ['code' => 'INVALID_DATE', 'message' => 'รูปแบบ end_date ต้องเป็น YYYY-MM-DD']];
        }

        if ($startDate !== null && $endDate !== null && $endDate < $startDate) {
            return ['error' => ['code' => 'INVALID_DATE_RANGE', 'message' => 'end_date ต้องไม่น้อยกว่า start_date']];
        }

        if (!in_array($status, AcademicTermModel::ALLOWED_STATUSES, true)) {
            return ['error' => ['code' => 'INVALID_STATUS', 'message' => 'สถานะต้องเป็น active, inactive, upcoming หรือ archived']];
        }

        return [
            'academic_year' => $academicYear,
            'term_no'       => $termNo,
            'start_date'    => $startDate,
            'end_date'      => $endDate,
            'status'        => $status,
        ];
    }

    private function isValidDate(string $date): bool
    {
        $dt = DateTimeImmutable::createFromFormat('Y-m-d', $date);
        return $dt !== false && $dt->format('Y-m-d') === $date;
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