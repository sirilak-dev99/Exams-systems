<?php

declare(strict_types=1);

/**
 * ============================================================
 *  QuestionController — คลังข้อสอบ
 * ------------------------------------------------------------
 *  GET  /questions             รายการ + ตัวกรอง      → views/questions/index.php  (QuestionBank.html)
 *  GET  /questions/create      ฟอร์มเพิ่ม            → views/questions/form.php   (QuestionForm.html)
 *  POST /questions             บันทึกข้อใหม่ (ฉบับที่ 1)
 *  GET  /questions/{id}        ดูข้อสอบ              → views/questions/show.php   (QuestionView.html)
 *  GET  /questions/{id}/edit   ฟอร์มแก้ไข            → views/questions/form.php
 *  POST /questions/{id}        บันทึกการแก้ไข → สร้างฉบับใหม่ (ฉบับเดิมยังดูย้อนหลังได้)
 *
 *  สิทธิ์: question.manage · POST จากฟอร์มตรวจ csrf_token · ส่ง JSON ได้แบบ Controller อื่น
 *  SQL ทั้งหมดอยู่ใน Models/QuestionBankModel.php
 * ============================================================
 */
final class QuestionController extends Controller
{
    private QuestionBankModel $model;

    public function __construct()
    {
        $this->model = new QuestionBankModel();
    }

    /**
     * GET /questions — รายการข้อสอบ + ตัวกรอง (views/questions/index.php)
     * JSON: { questions }
     */
    public function index(): void
    {
        Security::requirePermission('question.manage');
        $filters = array_map(fn ($k) => (string)($_GET[$k] ?? ''), ['q' => 'q', 'courses_id' => 'courses_id', 'chapters_id' => 'chapters_id', 'type' => 'type', 'bloom' => 'bloom', 'status' => 'status']);
        $questions = $this->model->getList($filters);

        if (Security::wantsJson()) {
            $this->json(200, ['questions' => $questions], null);
            return;
        }
        $courseId = ctype_digit($filters['courses_id']) ? (int)$filters['courses_id'] : null;
        $this->view('questions/index', [
            'pageTitle'    => 'คลังข้อสอบ',
            'questions'    => $questions,
            'courses'      => $this->model->getCourses(),
            'chapters'     => $courseId !== null ? $this->model->getChapters($courseId) : [],
            'bloomLevels'  => $this->model->getBloomLevels(),
            'statusCounts' => $this->model->getStatusCounts(),
        ]);
    }

    /**
     * GET /questions/{id} — ดูข้อสอบฉบับล่าสุด (views/questions/show.php)
     * JSON: { question, options, versions, learningOutcomes, concepts }
     */
    public function show(int|string $id): void
    {
        Security::requirePermission('question.manage');
        $qid = $this->parsePositiveId($id);
        $question = $qid === null ? null : $this->model->find($qid);

        if ($question === null) {
            if (Security::wantsJson()) {
                $this->json(404, null, ['code' => 'NOT_FOUND', 'message' => 'ไม่พบข้อสอบ']);
                return;
            }
            http_response_code(404);
            $this->view('questions/show', ['pageTitle' => 'ไม่พบข้อสอบ', 'question' => null]);
            return;
        }

        $versions = $this->model->getVersions($qid);
        $latestId = (int)($versions[0]['id_question_versions'] ?? 0);
        $data = [
            'question'         => $question,
            'options'          => $latestId ? $this->model->getOptions($latestId) : [],
            'versions'         => $versions,
            'learningOutcomes' => $latestId ? $this->model->getVersionLearningOutcomes($latestId) : [],
            'concepts'         => $latestId ? $this->model->getVersionConcepts($latestId) : [],
        ];
        if (Security::wantsJson()) {
            $this->json(200, $data, null);
            return;
        }
        $this->view('questions/show', ['pageTitle' => 'ข้อสอบ Q-' . str_pad((string)$qid, 4, '0', STR_PAD_LEFT)] + $data);
    }

    /**
     * GET /questions/create — ฟอร์มเพิ่มข้อสอบ
     * ?courses_id= (มาจากหน้ารายวิชา) → เลือกรายวิชาไว้ให้
     */
    public function create(): void
    {
        Security::requirePermission('question.manage');
        // เปิดจากหน้ารายวิชา (/questions/create?courses_id=) → เลือกรายวิชาไว้ให้
        $courseId = $this->parsePositiveId((string)($_GET['courses_id'] ?? ''));
        $this->renderForm(null, [], [], null, $courseId !== null ? ['courses_id' => (string)$courseId] : []);
    }

    /**
     * GET /questions/{id}/edit — ฟอร์มแก้ไข (ใส่ค่าจากฉบับล่าสุด)
     */
    public function edit(int|string $id): void
    {
        Security::requirePermission('question.manage');
        $qid = $this->parsePositiveId($id);
        $question = $qid === null ? null : $this->model->find($qid);
        if ($question === null) {
            http_response_code(404);
            $this->view('questions/show', ['pageTitle' => 'ไม่พบข้อสอบ', 'question' => null]);
            return;
        }
        $versions = $this->model->getVersions($qid);
        $latestId = (int)($versions[0]['id_question_versions'] ?? 0);
        $this->renderForm(
            $question,
            $latestId ? $this->model->getOptions($latestId) : [],
            $latestId ? array_column($this->model->getVersionLearningOutcomes($latestId), 'id_learning_outcomes') : [],
            $versions[0]['version_no'] ?? null
        );
    }

    /**
     * POST /questions — บันทึกข้อใหม่ (ฉบับที่ 1)
     * สำเร็จ: ฟอร์ม → ไปหน้าดูข้อสอบ (save_action = draft_add → กลับฟอร์มเปล่า) · JSON → 201 { question }
     * ไม่ผ่าน: ฟอร์ม → แสดงฟอร์มพร้อมข้อความใต้ช่อง (422) · JSON → 422 { error.details }
     */
    public function store(): void
    {
        Security::requirePermission('question.manage');
        $this->verifyCsrfIfWeb();
        $input = $this->readInput();
        [$data, $errors] = $this->validate($input);

        if ($errors) {
            $this->respondInvalid($input, $errors, null);
            return;
        }
        $id = $this->model->create($data, $this->userId());

        if (Security::wantsJson()) {
            $this->json(201, ['question' => $this->model->find($id)], null);
            return;
        }
        $this->redirect(($input['save_action'] ?? '') === 'draft_add' ? '/questions/create' : '/questions/' . $id);
    }

    /**
     * POST /questions/{id} — บันทึกการแก้ไข = อัปเดตข้อหลัก + สร้างฉบับใหม่ใน question_versions
     * JSON → { question, version_no }
     */
    public function update(int|string $id): void
    {
        Security::requirePermission('question.manage');
        $this->verifyCsrfIfWeb();
        $qid = $this->parsePositiveId($id);
        $existing = $qid === null ? null : $this->model->find($qid);
        if ($existing === null) {
            $this->json(404, null, ['code' => 'NOT_FOUND', 'message' => 'ไม่พบข้อสอบ']);
            return;
        }
        $input = $this->readInput();
        [$data, $errors] = $this->validate($input);
        if ($errors) {
            $this->respondInvalid($input, $errors, $existing);
            return;
        }
        $versionNo = $this->model->update($qid, $data, $this->userId());

        if (Security::wantsJson()) {
            $this->json(200, ['question' => $this->model->find($qid), 'version_no' => $versionNo], null);
            return;
        }
        $this->redirect('/questions/' . $qid);
    }

    // ---------- ตรวจข้อมูลจากฟอร์ม → แปลงเป็นข้อมูลที่ Model บันทึกได้ ----------

    /** @return array{0: array, 1: array<string,string>} */
    private function validate(array $in): array
    {
        $errors = [];
        $type = (string)($in['type'] ?? '');
        if (!in_array($type, QuestionBankModel::TYPES, true)) {
            $errors['type'] = 'กรุณาเลือกชนิดข้อสอบ';
        } elseif ($type === 'matching') {
            $errors['type'] = 'ข้อสอบจับคู่ยังบันทึกไม่ได้ (รอตกลงรูปแบบข้อมูล)';
        }

        $text = trim((string)($in['text'] ?? ''));
        if ($text === '') {
            $errors['text'] = 'กรุณากรอกโจทย์';
        }

        $bloom = (string)($in['bloom_levels_code'] ?? '');
        if ($bloom === '' || !$this->model->bloomExists($bloom)) {
            $errors['bloom_levels_code'] = 'กรุณาเลือกระดับ Bloom';
        }

        $courseId = $this->parsePositiveId((string)($in['courses_id'] ?? ''));
        $chapterId = $this->parsePositiveId((string)($in['chapters_id'] ?? ''));
        if ($chapterId !== null && ($courseId === null || !$this->model->chapterBelongsToCourse($chapterId, $courseId))) {
            $errors['chapters_id'] = 'บทที่เลือกไม่อยู่ในรายวิชานี้';
        }

        // คำตอบตามชนิด
        $options = [];
        if ($type === 'multiple_choice' || $type === 'short_answer') {
            foreach ((array)($in['options'] ?? []) as $o) {
                $t = trim((string)($o['text'] ?? ''));
                if ($t === '') {
                    continue;
                }
                $options[] = [
                    'label'      => mb_substr(trim((string)($o['label'] ?? '')) ?: (string)(count($options) + 1), 0, 20),
                    'text'       => $t,
                    'is_correct' => $type === 'short_answer' ? 1 : (int)!empty($o['is_correct']),
                ];
            }
            if ($type === 'multiple_choice' && count($options) < 2) {
                $errors['options'] = 'ข้อสอบปรนัยต้องมีตัวเลือกอย่างน้อย 2 ข้อ';
            } elseif ($type === 'multiple_choice' && !array_filter(array_column($options, 'is_correct'))) {
                $errors['options'] = 'กรุณาติ๊กคำตอบที่ถูกอย่างน้อย 1 ข้อ';
            } elseif ($type === 'short_answer' && !$options) {
                $errors['options'] = 'กรุณาใส่คำตอบที่ยอมรับอย่างน้อย 1 คำตอบ';
            }
            $labels = array_column($options, 'label');
            if (count($labels) !== count(array_unique($labels))) {
                $errors['options'] = 'ลำดับตัวเลือก (ก ข ค ...) ซ้ำกัน';
            }
        } elseif ($type === 'true_false') {
            $tf = (string)($in['tf_answer'] ?? '');
            if (!in_array($tf, ['true', 'false'], true)) {
                $errors['tf_answer'] = 'กรุณาเลือกคำตอบที่ถูก';
            }
            $options = [['label' => 'ถูก', 'text' => 'ถูก', 'is_correct' => (int)($tf === 'true')],
                        ['label' => 'ผิด', 'text' => 'ผิด', 'is_correct' => (int)($tf === 'false')]];
        }

        $score = null;
        if ($type === 'essay') {
            $raw = trim((string)($in['manual_max_score'] ?? ''));
            if ($raw === '' || !is_numeric($raw) || (float)$raw < 0) {
                $errors['manual_max_score'] = 'กรุณาใส่คะแนนเต็มเป็นตัวเลข 0 ขึ้นไป';
            } else {
                $score = round((float)$raw, 2);
            }
        }

        $data = [
            'type'              => $type,
            'courses_id'        => $courseId,
            'chapters_id'       => $chapterId,
            'scenario_id'       => $this->parsePositiveId((string)($in['scenario_id'] ?? '')),
            'bloom_levels_code' => $bloom,
            'text'              => $text,
            'explanation'       => trim((string)($in['explanation'] ?? '')) ?: null,
            'manual_max_score'  => $score,
            'shuffle_options'   => (int)(($in['shuffle_options'] ?? '') === '1' && $type === 'multiple_choice'),
            'student_visible'   => (int)(($in['student_visible'] ?? '') === '1'),
            'status'            => ($in['save_action'] ?? '') === 'publish' ? 'published' : 'draft',
            'options'           => $options,
            'lo_ids'            => array_values(array_filter(array_map('intval', (array)($in['learning_outcome_ids'] ?? [])))),
        ];
        return [$data, $errors];
    }

    // ---------- ตอบกลับเมื่อข้อมูลไม่ผ่าน และวาดฟอร์ม ----------
    private function respondInvalid(array $input, array $errors, ?array $existing): void
    {
        if (Security::wantsJson()) {
            $this->json(422, null, ['code' => 'VALIDATION_ERROR', 'message' => 'กรุณากรอกข้อมูลให้ครบถ้วนและถูกต้อง', 'details' => $errors]);
            return;
        }
        http_response_code(422);
        $versions = $existing ? $this->model->getVersions((int)$existing['id_questions']) : [];
        $this->renderForm($existing, [], [], $versions[0]['version_no'] ?? null, $input, 'กรุณาตรวจสอบช่องที่มีข้อความสีแดง', $errors);
    }

    private function renderForm(?array $question, array $options, array $selectedLoIds, mixed $latestVersionNo,
                                array $old = [], ?string $error = null, array $errors = []): void
    {
        $this->view('questions/form', [
            'pageTitle'        => $question ? 'แก้ไขข้อสอบ' : 'เพิ่มข้อสอบ',
            'question'         => $question,
            'options'          => $options,
            'selectedLoIds'    => $selectedLoIds,
            'latestVersionNo'  => $latestVersionNo,
            'courses'          => $this->model->getCourses(),
            'chapters'         => $this->model->getChapters(),
            'learningOutcomes' => $this->model->getLearningOutcomes(),
            'bloomLevels'      => $this->model->getBloomLevels(),
            'scenarios'        => $this->model->getScenarios(),
            'old'              => $old,
            'error'            => $error,
            'errors'           => $errors,
        ]);
    }

    // ---------- ตัวช่วย (แบบเดียวกับ Controller อื่นในโปรเจกต์) ----------

    private function userId(): int
    {
        return (int)(Security::currentUser()['id_users'] ?? 0);
    }

    private function parsePositiveId(int|string $raw): ?int
    {
        $s = trim((string)$raw);
        return ($s !== '' && ctype_digit($s) && (int)$s > 0) ? (int)$s : null;
    }

    private function readInput(): array
    {
        $raw = file_get_contents('php://input');
        if (is_string($raw) && $raw !== '' && Security::wantsJson()) {
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

    private function json(int $status, mixed $data, ?array $error): void
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['data' => $data, 'error' => $error], JSON_UNESCAPED_UNICODE);
    }
}
