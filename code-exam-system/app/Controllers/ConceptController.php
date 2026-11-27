<?php

declare(strict_types=1);

final class ConceptController extends Controller
{
    private ConceptModel $model;

    public function __construct()
    {
        $this->model = new ConceptModel();
    }

    // --- แนวคิดหลัก (main_concepts) ---

    // GET /courses/{id}/main-concepts
    public function mainIndex(int|string $id): void
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

        $items = $this->model->getMainByCourse($courseId);
        $this->json(200, ['courses_id' => $courseId, 'main_concepts' => $items], null);
    }

    // POST /courses/{id}/main-concepts
    public function mainStore(int|string $id): void
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

        $input       = $this->readInput();
        $code        = trim((string)($input['code'] ?? ''));
        $name        = trim((string)($input['name'] ?? ''));
        $description = isset($input['description']) && trim((string)$input['description']) !== ''
            ? trim((string)$input['description'])
            : null;

        if ($code === '' || mb_strlen($code) > 20 || $name === '' || mb_strlen($name) > 150) {
            $this->json(422, null, [
                'code'    => 'VALIDATION_ERROR',
                'message' => 'กรุณาระบุ code (1-20 ตัวอักษร) และ name (1-150 ตัวอักษร)',
            ]);
            return;
        }

        if ($this->model->mainCodeExists($courseId, $code)) {
            $this->json(409, null, [
                'code'    => 'DUPLICATE_MAIN_CONCEPT_CODE',
                'message' => 'รหัสแนวคิดหลัก (code) ซ้ำภายในรายวิชานี้',
            ]);
            return;
        }

        if ($this->model->mainNameExists($courseId, $name)) {
            $this->json(409, null, [
                'code'    => 'DUPLICATE_MAIN_CONCEPT_NAME',
                'message' => 'ชื่อแนวคิดหลักซ้ำภายในรายวิชานี้',
            ]);
            return;
        }

        $newId = $this->model->createMain([
            'courses_id'  => $courseId,
            'code'        => $code,
            'name'        => $name,
            'description' => $description,
        ]);

        $this->json(201, ['main_concept' => $this->model->findMainById($newId)], null);
    }

    // POST /main-concepts/{id} (รองรับ _method=DELETE)
    public function mainUpdate(int|string $id): void
    {
        Security::requirePermission('course.manage');
        $this->verifyCsrfIfWeb();

        $mainId = $this->parsePositiveId($id);
        if ($mainId === null) {
            $this->json(400, null, ['code' => 'INVALID_ID', 'message' => 'รหัสแนวคิดหลักต้องเป็นจำนวนเต็มบวก']);
            return;
        }

        $input  = $this->readInput();
        $method = strtoupper(trim((string)($input['_method'] ?? ($_SERVER['REQUEST_METHOD'] ?? 'POST'))));
        if ($method === 'DELETE') {
            $this->mainDestroy($mainId);
            return;
        }

        $existing = $this->model->findMainById($mainId);
        if (!$existing) {
            $this->json(404, null, ['code' => 'NOT_FOUND', 'message' => 'ไม่พบข้อมูลแนวคิดหลัก']);
            return;
        }

        $code = array_key_exists('code', $input) ? trim((string)$input['code']) : (string)$existing['code'];
        $name = array_key_exists('name', $input) ? trim((string)$input['name']) : (string)$existing['name'];
        $description = array_key_exists('description', $input)
            ? (trim((string)$input['description']) !== '' ? trim((string)$input['description']) : null)
            : $existing['description'];

        if ($code === '' || mb_strlen($code) > 20 || $name === '' || mb_strlen($name) > 150) {
            $this->json(422, null, [
                'code'    => 'VALIDATION_ERROR',
                'message' => 'code ต้องมีความยาว 1-20 ตัวอักษร และ name ต้องมีความยาว 1-150 ตัวอักษร',
            ]);
            return;
        }

        $courseId = (int)$existing['courses_id'];
        if ($this->model->mainCodeExists($courseId, $code, $mainId)) {
            $this->json(409, null, [
                'code'    => 'DUPLICATE_MAIN_CONCEPT_CODE',
                'message' => 'รหัสแนวคิดหลัก (code) ซ้ำภายในรายวิชานี้',
            ]);
            return;
        }

        if ($this->model->mainNameExists($courseId, $name, $mainId)) {
            $this->json(409, null, [
                'code'    => 'DUPLICATE_MAIN_CONCEPT_NAME',
                'message' => 'ชื่อแนวคิดหลักซ้ำภายในรายวิชานี้',
            ]);
            return;
        }

        $this->model->updateMain($mainId, [
            'code'        => $code,
            'name'        => $name,
            'description' => $description,
        ]);

        $this->json(200, ['main_concept' => $this->model->findMainById($mainId)], null);
    }

    // POST /main-concepts/{id}/delete
    public function mainDestroy(int|string $id): void
    {
        Security::requirePermission('course.manage');
        $this->verifyCsrfIfWeb();

        $mainId = $this->parsePositiveId($id);
        if ($mainId === null) {
            $this->json(400, null, ['code' => 'INVALID_ID', 'message' => 'รหัสแนวคิดหลักต้องเป็นจำนวนเต็มบวก']);
            return;
        }

        if (!$this->model->findMainById($mainId)) {
            $this->json(404, null, ['code' => 'NOT_FOUND', 'message' => 'ไม่พบข้อมูลแนวคิดหลัก']);
            return;
        }

        if ($this->model->mainHasSubConcepts($mainId)) {
            $this->json(409, null, [
                'code'    => 'MAIN_CONCEPT_IN_USE',
                'message' => 'ไม่สามารถลบแนวคิดหลักได้ เนื่องจากมีแนวคิดย่อย (sub_concepts) ผูกอยู่',
            ]);
            return;
        }

        try {
            $this->model->deleteMain($mainId);
        } catch (PDOException $e) {
            if ($e->getCode() === '23000') {
                $this->json(409, null, [
                    'code'    => 'MAIN_CONCEPT_IN_USE',
                    'message' => 'ไม่สามารถลบแนวคิดหลักได้ เนื่องจากมีข้อมูลอื่นในระบบอ้างอิงอยู่',
                ]);
                return;
            }
            throw $e;
        }

        $this->json(200, ['deleted_id' => $mainId], null);
    }

    // --- แนวคิดย่อย (sub_concepts) ---

    // GET /main-concepts/{id}/sub-concepts
    public function subIndex(int|string $id): void
    {
        Security::requirePermission('course.view');

        $mainId = $this->parsePositiveId($id);
        if ($mainId === null) {
            $this->json(400, null, ['code' => 'INVALID_ID', 'message' => 'รหัสแนวคิดหลักต้องเป็นจำนวนเต็มบวก']);
            return;
        }

        $main = $this->model->findMainById($mainId);
        if (!$main) {
            $this->json(404, null, ['code' => 'MAIN_CONCEPT_NOT_FOUND', 'message' => 'ไม่พบข้อมูลแนวคิดหลัก']);
            return;
        }

        $items = $this->model->getSubByMain($mainId);
        $this->json(200, [
            'main_concepts_id' => $mainId,
            'courses_id'       => (int)$main['courses_id'],
            'sub_concepts'     => $items,
        ], null);
    }

    // POST /main-concepts/{id}/sub-concepts
    public function subStore(int|string $id): void
    {
        Security::requirePermission('course.manage');
        $this->verifyCsrfIfWeb();

        $mainId = $this->parsePositiveId($id);
        if ($mainId === null) {
            $this->json(400, null, ['code' => 'INVALID_ID', 'message' => 'รหัสแนวคิดหลักต้องเป็นจำนวนเต็มบวก']);
            return;
        }

        $main = $this->model->findMainById($mainId);
        if (!$main) {
            $this->json(404, null, ['code' => 'MAIN_CONCEPT_NOT_FOUND', 'message' => 'ไม่พบข้อมูลแนวคิดหลักที่ระบุ']);
            return;
        }

        $input       = $this->readInput();
        $code        = trim((string)($input['code'] ?? ''));
        $name        = trim((string)($input['name'] ?? ''));
        $description = isset($input['description']) && trim((string)$input['description']) !== ''
            ? trim((string)$input['description'])
            : null;

        if ($code === '' || mb_strlen($code) > 20 || $name === '' || mb_strlen($name) > 150) {
            $this->json(422, null, [
                'code'    => 'VALIDATION_ERROR',
                'message' => 'กรุณาระบุ code (1-20 ตัวอักษร) และ name (1-150 ตัวอักษร)',
            ]);
            return;
        }

        $courseId = (int)$main['courses_id'];

        if ($this->model->subCodeExistsInCourse($courseId, $code)) {
            $this->json(409, null, [
                'code'    => 'DUPLICATE_SUB_CONCEPT_CODE',
                'message' => 'รหัสแนวคิดย่อย (code) ซ้ำภายในรายวิชานี้',
            ]);
            return;
        }

        if ($this->model->subNameExistsInMain($mainId, $name)) {
            $this->json(409, null, [
                'code'    => 'DUPLICATE_SUB_CONCEPT_NAME',
                'message' => 'ชื่อแนวคิดย่อยซ้ำภายใต้แนวคิดหลักเดียวกัน',
            ]);
            return;
        }

        $newId = $this->model->createSub([
            'main_concepts_id' => $mainId,
            'courses_id'       => $courseId,
            'code'             => $code,
            'name'             => $name,
            'description'      => $description,
        ]);

        $this->json(201, ['sub_concept' => $this->model->findSubById($newId)], null);
    }

    // POST /sub-concepts/{id} (รองรับ _method=DELETE และย้าย main_concepts_id ภายในวิชาเดียวกัน)
    public function subUpdate(int|string $id): void
    {
        Security::requirePermission('course.manage');
        $this->verifyCsrfIfWeb();

        $subId = $this->parsePositiveId($id);
        if ($subId === null) {
            $this->json(400, null, ['code' => 'INVALID_ID', 'message' => 'รหัสแนวคิดย่อยต้องเป็นจำนวนเต็มบวก']);
            return;
        }

        $input  = $this->readInput();
        $method = strtoupper(trim((string)($input['_method'] ?? ($_SERVER['REQUEST_METHOD'] ?? 'POST'))));
        if ($method === 'DELETE') {
            $this->subDestroy($subId);
            return;
        }

        $existing = $this->model->findSubById($subId);
        if (!$existing) {
            $this->json(404, null, ['code' => 'NOT_FOUND', 'message' => 'ไม่พบข้อมูลแนวคิดย่อย']);
            return;
        }

        $courseId  = (int)$existing['courses_id'];
        $newMainId = array_key_exists('main_concepts_id', $input)
            ? $this->parsePositiveId($input['main_concepts_id'])
            : (int)$existing['main_concepts_id'];

        if ($newMainId === null) {
            $this->json(422, null, ['code' => 'INVALID_MAIN_CONCEPT', 'message' => 'main_concepts_id ไม่ถูกต้อง']);
            return;
        }

        if ($newMainId !== (int)$existing['main_concepts_id']) {
            $targetMain = $this->model->findMainById($newMainId);
            if (!$targetMain || (int)$targetMain['courses_id'] !== $courseId) {
                $this->json(422, null, [
                    'code'    => 'COURSE_MISMATCH',
                    'message' => 'แนวคิดหลักปลายทางต้องอยู่ในรายวิชาเดียวกันเท่านั้น',
                ]);
                return;
            }
        }

        $code = array_key_exists('code', $input) ? trim((string)$input['code']) : (string)$existing['code'];
        $name = array_key_exists('name', $input) ? trim((string)$input['name']) : (string)$existing['name'];
        $description = array_key_exists('description', $input)
            ? (trim((string)$input['description']) !== '' ? trim((string)$input['description']) : null)
            : $existing['description'];

        if ($code === '' || mb_strlen($code) > 20 || $name === '' || mb_strlen($name) > 150) {
            $this->json(422, null, [
                'code'    => 'VALIDATION_ERROR',
                'message' => 'code ต้องมีความยาว 1-20 ตัวอักษร และ name ต้องมีความยาว 1-150 ตัวอักษร',
            ]);
            return;
        }

        if ($this->model->subCodeExistsInCourse($courseId, $code, $subId)) {
            $this->json(409, null, [
                'code'    => 'DUPLICATE_SUB_CONCEPT_CODE',
                'message' => 'รหัสแนวคิดย่อย (code) ซ้ำภายในรายวิชานี้',
            ]);
            return;
        }

        if ($this->model->subNameExistsInMain($newMainId, $name, $subId)) {
            $this->json(409, null, [
                'code'    => 'DUPLICATE_SUB_CONCEPT_NAME',
                'message' => 'ชื่อแนวคิดย่อยซ้ำภายใต้แนวคิดหลักเดียวกัน',
            ]);
            return;
        }

        $this->model->updateSub($subId, [
            'main_concepts_id' => $newMainId,
            'code'             => $code,
            'name'             => $name,
            'description'      => $description,
        ]);

        $this->json(200, ['sub_concept' => $this->model->findSubById($subId)], null);
    }

    // POST /sub-concepts/{id}/delete
    public function subDestroy(int|string $id): void
    {
        Security::requirePermission('course.manage');
        $this->verifyCsrfIfWeb();

        $subId = $this->parsePositiveId($id);
        if ($subId === null) {
            $this->json(400, null, ['code' => 'INVALID_ID', 'message' => 'รหัสแนวคิดย่อยต้องเป็นจำนวนเต็มบวก']);
            return;
        }

        if (!$this->model->findSubById($subId)) {
            $this->json(404, null, ['code' => 'NOT_FOUND', 'message' => 'ไม่พบข้อมูลแนวคิดย่อย']);
            return;
        }

        try {
            $this->model->deleteSub($subId);
        } catch (PDOException $e) {
            if ($e->getCode() === '23000') {
                $this->json(409, null, [
                    'code'    => 'SUB_CONCEPT_IN_USE',
                    'message' => 'ไม่สามารถลบแนวคิดย่อยได้ เนื่องจากมีข้อมูลอื่นในระบบอ้างอิงอยู่',
                ]);
                return;
            }
            throw $e;
        }

        $this->json(200, ['deleted_id' => $subId], null);
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