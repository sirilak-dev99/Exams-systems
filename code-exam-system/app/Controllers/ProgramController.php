<?php

declare(strict_types=1);

final class ProgramController extends Controller
{
    private ProgramModel $programModel;

    public function __construct()
    {
        $this->programModel = new ProgramModel();
    }

    // GET /programs
    public function index(): void
    {
        Security::requirePermission('program.manage');

        $programs = $this->programModel->getAll([
            'status' => $_GET['status'] ?? null,
            'q'      => $_GET['q'] ?? null,
        ]);

        if (Security::wantsJson()) {
            $this->json(200, ['programs' => $programs], null);
            return;
        }

        $this->view('programs/index', [
            'pageTitle' => 'จัดการหลักสูตร',
            'programs'  => $programs,
        ]);
    }

    // POST /programs
    public function store(): void
    {
        Security::requirePermission('program.manage');
        $this->verifyCsrfIfWeb();

        $input  = $this->readInput();
        $code   = strtoupper(trim((string)($input['code'] ?? '')));
        $name   = trim((string)($input['name'] ?? ''));
        $status = trim((string)($input['status'] ?? 'active'));

        if ($code === '' || $name === '') {
            $this->json(422, null, ['code' => 'VALIDATION_ERROR', 'message' => 'กรุณากรอกรหัสหลักสูตรและชื่อหลักสูตร']);
            return;
        }

        if (!in_array($status, ProgramModel::ALLOWED_STATUSES, true)) {
            $this->json(422, null, ['code' => 'INVALID_STATUS', 'message' => 'สถานะต้องเป็น active, inactive หรือ archived']);
            return;
        }

        if ($this->programModel->codeExists($code)) {
            $this->json(409, null, ['code' => 'DUPLICATE_CODE', 'message' => 'รหัสหลักสูตรนี้มีอยู่ในระบบแล้ว']);
            return;
        }

        $newId   = $this->programModel->create([
            'code'   => $code,
            'name'   => $name,
            'status' => $status,
        ]);
        $program = $this->programModel->findById($newId);

        $this->json(201, ['program' => $program], null);
    }

    // POST /programs/{id} (รองรับ _method=DELETE ด้วย)
    public function update(int|string $id): void
    {
        Security::requirePermission('program.manage');
        $this->verifyCsrfIfWeb();

        $programId = $this->parsePositiveId($id);
        if ($programId === null) {
            $this->json(400, null, ['code' => 'INVALID_ID', 'message' => 'รหัสหลักสูตรต้องเป็นจำนวนเต็มบวก']);
            return;
        }

        $input  = $this->readInput();
        $method = strtoupper(trim((string)($input['_method'] ?? ($_SERVER['REQUEST_METHOD'] ?? 'POST'))));
        if ($method === 'DELETE') {
            $this->destroy($programId);
            return;
        }

        $existing = $this->programModel->findById($programId);
        if (!$existing) {
            $this->json(404, null, ['code' => 'NOT_FOUND', 'message' => 'ไม่พบข้อมูลหลักสูตรที่ต้องการแก้ไข']);
            return;
        }

        $code   = strtoupper(trim((string)($input['code'] ?? $existing['code'])));
        $name   = trim((string)($input['name'] ?? $existing['name']));
        $status = trim((string)($input['status'] ?? $existing['status']));

        if ($code === '' || $name === '') {
            $this->json(422, null, ['code' => 'VALIDATION_ERROR', 'message' => 'รหัสหลักสูตรและชื่อหลักสูตรห้ามว่าง']);
            return;
        }

        if (!in_array($status, ProgramModel::ALLOWED_STATUSES, true)) {
            $this->json(422, null, ['code' => 'INVALID_STATUS', 'message' => 'สถานะต้องเป็น active, inactive หรือ archived']);
            return;
        }

        if ($this->programModel->codeExists($code, $programId)) {
            $this->json(409, null, ['code' => 'DUPLICATE_CODE', 'message' => 'รหัสหลักสูตรซ้ำกับหลักสูตรอื่น']);
            return;
        }

        $this->programModel->update($programId, [
            'code'   => $code,
            'name'   => $name,
            'status' => $status,
        ]);

        $this->json(200, ['program' => $this->programModel->findById($programId)], null);
    }

    // POST /programs/{id}/delete หรือเรียกต่อจาก _method=DELETE
    public function destroy(int|string $id): void
    {
        Security::requirePermission('program.manage');
        $this->verifyCsrfIfWeb();

        $programId = $this->parsePositiveId($id);
        if ($programId === null) {
            $this->json(400, null, ['code' => 'INVALID_ID', 'message' => 'รหัสหลักสูตรต้องเป็นจำนวนเต็มบวก']);
            return;
        }

        $existing = $this->programModel->findById($programId);
        if (!$existing) {
            $this->json(404, null, ['code' => 'NOT_FOUND', 'message' => 'ไม่พบข้อมูลหลักสูตร']);
            return;
        }

        if ($this->programModel->hasDependencies($programId)) {
            $this->json(409, null, [
                'code'    => 'PROGRAM_IN_USE',
                'message' => 'ไม่สามารถลบหลักสูตรได้เนื่องจากมีรุ่นหลักสูตรผูกอยู่ แนะนำให้เปลี่ยนสถานะเป็น inactive หรือ archived แทน',
            ]);
            return;
        }

        $this->programModel->delete($programId);
        $this->json(200, ['deleted_id' => $programId], null);
    }

    // GET /programs/{id}/versions
    public function versions(int|string $id): void
    {
        Security::requirePermission('program.manage');

        $programId = $this->parsePositiveId($id);
        if ($programId === null) {
            $this->json(400, null, ['code' => 'INVALID_ID', 'message' => 'รหัสหลักสูตรต้องเป็นจำนวนเต็มบวก']);
            return;
        }

        $program = $this->programModel->findById($programId);
        if (!$program) {
            $this->json(404, null, ['code' => 'NOT_FOUND', 'message' => 'ไม่พบข้อมูลหลักสูตร']);
            return;
        }

        $versions = $this->programModel->getVersionsByProgramId($programId);
        $this->json(200, [
            'program'  => $program,
            'versions' => $versions,
        ], null);
    }

    // POST /programs/{id}/versions
    public function storeVersion(int|string $id): void
    {
        Security::requirePermission('program.manage');
        $this->verifyCsrfIfWeb();

        $programId = $this->parsePositiveId($id);
        if ($programId === null) {
            $this->json(400, null, ['code' => 'INVALID_ID', 'message' => 'รหัสหลักสูตรต้องเป็นจำนวนเต็มบวก']);
            return;
        }

        $program = $this->programModel->findById($programId);
        if (!$program) {
            $this->json(404, null, ['code' => 'NOT_FOUND', 'message' => 'ไม่พบข้อมูลหลักสูตร']);
            return;
        }

        $input         = $this->readInput();
        $versionNo     = (int)($input['version_no'] ?? 0);
        $effectiveFrom = !empty($input['effective_from']) ? trim((string)$input['effective_from']) : null;
        $effectiveTo   = !empty($input['effective_to']) ? trim((string)$input['effective_to']) : null;
        $status        = trim((string)($input['status'] ?? 'active'));

        if ($versionNo < 1) {
            $this->json(422, null, ['code' => 'VALIDATION_ERROR', 'message' => 'เลขรุ่นหลักสูตร (version_no) ต้องมากกว่าหรือเท่ากับ 1']);
            return;
        }

        if ($effectiveFrom !== null && $effectiveTo !== null && $effectiveTo < $effectiveFrom) {
            $this->json(422, null, ['code' => 'INVALID_DATE_RANGE', 'message' => 'effective_to ต้องไม่น้อยกว่า effective_from']);
            return;
        }

        if (!in_array($status, ProgramModel::ALLOWED_STATUSES, true)) {
            $this->json(422, null, ['code' => 'INVALID_STATUS', 'message' => 'สถานะต้องเป็น active, inactive หรือ archived']);
            return;
        }

        if ($this->programModel->versionNoExists($programId, $versionNo)) {
            $this->json(409, null, ['code' => 'DUPLICATE_VERSION', 'message' => 'รุ่นหลักสูตรนี้มีอยู่แล้วในหลักสูตรที่เลือก']);
            return;
        }

        $versionId = $this->programModel->createVersion($programId, [
            'version_no'     => $versionNo,
            'effective_from' => $effectiveFrom,
            'effective_to'   => $effectiveTo,
            'status'         => $status,
        ]);

        $this->json(201, [
            'id_program_versions' => $versionId,
            'programs_id'         => $programId,
            'version_no'          => $versionNo,
            'effective_from'      => $effectiveFrom,
            'effective_to'        => $effectiveTo,
            'status'              => $status,
        ], null);
    }

    private function parsePositiveId(int|string $rawId): ?int
    {
        if (is_int($rawId)) {
            return $rawId > 0 ? $rawId : null;
        }
        $trimmed = trim($rawId);
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