<?php

declare(strict_types=1);

final class AttemptController extends Controller
{
    private AttemptModel $model;

    public function __construct()
    {
        $this->model = new AttemptModel();
    }

    // POST /exams/{id}/attempts/start (Task 37)
    public function start(int|string $id): void
    {
        $userId = $this->requireLoggedInUserId();
        if ($userId === null) {
            return;
        }
        $this->verifyCsrfIfWeb();

        $examId = $this->parsePositiveId($id);
        if ($examId === null) {
            $this->json(400, null, ['code' => 'INVALID_ID', 'message' => 'รหัสชุดข้อสอบต้องเป็นจำนวนเต็มบวก']);
            return;
        }

        $input = $this->readInput();
        $accessCode = isset($input['access_code']) ? (string)$input['access_code'] : null;

        $result = $this->model->startAttempt(
            $examId,
            $userId,
            $accessCode,
            $this->clientIp(),
            $this->clientUserAgent()
        );

        if (isset($result['error'])) {
            [$status, $code, $message] = $result['error'];
            $this->json($status, null, ['code' => $code, 'message' => $message]);
            return;
        }

        $this->json(201, $result['data'], null);
    }

    // POST /attempts/{id}/resume (Task 37)
    public function resume(int|string $id): void
    {
        $userId = $this->requireLoggedInUserId();
        if ($userId === null) {
            return;
        }
        $this->verifyCsrfIfWeb();

        $attemptId = $this->parsePositiveId($id);
        if ($attemptId === null) {
            $this->json(400, null, ['code' => 'INVALID_ID', 'message' => 'รหัสรอบการสอบต้องเป็นจำนวนเต็มบวก']);
            return;
        }

        $input = $this->readInput();
        $accessCode = isset($input['access_code']) ? (string)$input['access_code'] : null;

        $result = $this->model->resumeAttempt(
            $attemptId,
            $userId,
            $accessCode,
            $this->clientIp(),
            $this->clientUserAgent()
        );

        if (isset($result['error'])) {
            [$status, $code, $message] = $result['error'];
            $this->json($status, null, ['code' => $code, 'message' => $message]);
            return;
        }

        $this->json(200, $result['data'], null);
    }

    // GET /attempts/{id}
    public function show(int|string $id): void
    {
        $userId = $this->requireLoggedInUserId();
        if ($userId === null) {
            return;
        }

        $attemptId = $this->parsePositiveId($id);
        if ($attemptId === null) {
            $this->json(400, null, ['code' => 'INVALID_ID', 'message' => 'รหัสรอบการสอบต้องเป็นจำนวนเต็มบวก']);
            return;
        }

        $attempt = $this->model->findAttemptForUser($attemptId, $userId);
        if (!$attempt) {
            $this->json(404, null, ['code' => 'ATTEMPT_NOT_FOUND', 'message' => 'ไม่พบข้อมูลรอบการสอบ']);
            return;
        }

        $this->json(200, [
            'attempt'       => $attempt,
            'questions'     => $this->model->getExamQuestionsForPlayer((int)$attempt['exams_id']),
            'saved_answers' => $this->model->getSavedAnswers($attemptId),
        ], null);
    }

    // POST /attempts/{id}/answers (Task 38: Upsert + Autosave + Draft + Event)
    public function saveAnswer(int|string $id): void
    {
        $userId = $this->requireLoggedInUserId();
        if ($userId === null) {
            return;
        }
        $this->verifyCsrfIfWeb();

        $attemptId = $this->parsePositiveId($id);
        if ($attemptId === null) {
            $this->json(400, null, ['code' => 'INVALID_ID', 'message' => 'รหัสรอบการสอบต้องเป็นจำนวนเต็มบวก']);
            return;
        }

        $input = $this->readInput();
        $examQuestionId = $this->parsePositiveId($input['exam_question_id'] ?? '');
        if ($examQuestionId === null) {
            $this->json(422, null, ['code' => 'VALIDATION_ERROR', 'message' => 'กรุณาระบุ exam_question_id']);
            return;
        }

        $answerText = array_key_exists('answer_text', $input) && $input['answer_text'] !== null
            ? (string)$input['answer_text']
            : null;

        $selectedOptionIds = [];
        if (array_key_exists('selected_option_ids', $input) && $input['selected_option_ids'] !== null) {
            if (!is_array($input['selected_option_ids'])) {
                $this->json(422, null, ['code' => 'VALIDATION_ERROR', 'message' => 'selected_option_ids ต้องเป็นอาร์เรย์']);
                return;
            }
            foreach ($input['selected_option_ids'] as $oid) {
                $pid = $this->parsePositiveId($oid);
                if ($pid === null) {
                    $this->json(422, null, ['code' => 'VALIDATION_ERROR', 'message' => 'รหัสตัวเลือกต้องเป็นจำนวนเต็มบวก']);
                    return;
                }
                $selectedOptionIds[] = $pid;
            }
        }

        $saveMode = trim((string)($input['save_mode'] ?? 'autosave'));
        if (!in_array($saveMode, ['autosave', 'manual_save', 'save'], true)) {
            $saveMode = 'autosave';
        }

        $displayedOrder = isset($input['displayed_order']) && $input['displayed_order'] !== ''
            ? $this->parsePositiveId($input['displayed_order'])
            : null;

        $result = $this->model->upsertAnswer(
            $attemptId,
            $userId,
            $examQuestionId,
            $answerText,
            $selectedOptionIds,
            $saveMode,
            $displayedOrder,
            $this->clientIp(),
            $this->clientUserAgent()
        );

        if (isset($result['error'])) {
            [$status, $code, $message] = $result['error'];
            $this->json($status, null, ['code' => $code, 'message' => $message]);
            return;
        }

        $this->json(200, $result['data'], null);
    }

    // POST /attempts/{id}/submit (Task 39: Lock & Auto-Grade Objective)
    public function submit(int|string $id): void
    {
        $userId = $this->requireLoggedInUserId();
        if ($userId === null) {
            return;
        }
        $this->verifyCsrfIfWeb();

        $attemptId = $this->parsePositiveId($id);
        if ($attemptId === null) {
            $this->json(400, null, ['code' => 'INVALID_ID', 'message' => 'รหัสรอบการสอบต้องเป็นจำนวนเต็มบวก']);
            return;
        }

        $result = $this->model->submitAttempt(
            $attemptId,
            $userId,
            $this->clientIp(),
            $this->clientUserAgent()
        );

        if (isset($result['error'])) {
            [$status, $code, $message] = $result['error'];
            $this->json($status, null, ['code' => $code, 'message' => $message]);
            return;
        }

        $this->json(200, $result['data'], null);
    }

    // GET /exams/{id}/attempts/active (Task 41: ตรวจ attempt ค้างอยู่ก่อนแสดงหน้าเข้าสอบ)
    public function activeAttempt(int|string $id): void
    {
        $userId = $this->requireLoggedInUserId();
        if ($userId === null) {
            return;
        }

        $examId = $this->parsePositiveId($id);
        if ($examId === null) {
            $this->json(400, null, ['code' => 'INVALID_ID', 'message' => 'รหัสชุดข้อสอบต้องเป็นจำนวนเต็มบวก']);
            return;
        }

        $result = $this->model->findActiveAttemptForExam($examId, $userId);
        if (isset($result['error'])) {
            [$status, $code, $message] = $result['error'];
            $this->json($status, null, ['code' => $code, 'message' => $message]);
            return;
        }

        $this->json(200, $result['data'], null);
    }

    private function requireLoggedInUserId(): ?int
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            @session_start();
        }
        $uid = $_SESSION['user_id'] ?? $_SESSION['id_users'] ?? $_SESSION['user']['id_users'] ?? null;
        if ($uid === null || (int)$uid <= 0) {
            $this->json(401, null, ['code' => 'UNAUTHORIZED', 'message' => 'กรุณาเข้าสู่ระบบก่อนใช้งาน']);
            return null;
        }
        return (int)$uid;
    }

    private function clientIp(): ?string
    {
        $ip = $_SERVER['REMOTE_ADDR'] ?? null;
        return is_string($ip) ? substr($ip, 0, 45) : null;
    }

    private function clientUserAgent(): ?string
    {
        $ua = $_SERVER['HTTP_USER_AGENT'] ?? null;
        return is_string($ua) ? substr($ua, 0, 255) : null;
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