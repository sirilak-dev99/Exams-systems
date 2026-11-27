<?php

declare(strict_types=1);

final class ReportStatisticsController extends Controller
{
    private ReportStatisticsModel $model;
    private AuditLogModel $auditModel;

    public function __construct()
    {
        $this->model = new ReportStatisticsModel();
        $this->auditModel = new AuditLogModel();
    }

    // GET /reports/exams/{id}/scores (Task 46)
    public function examScores(int|string $id): void
    {
        Security::requirePermission('exam.manage');

        $examId = $this->parsePositiveId($id);
        if ($examId === null) {
            $this->json(400, null, ['code' => 'INVALID_ID', 'message' => 'รหัสชุดข้อสอบต้องเป็นจำนวนเต็มบวก']);
            return;
        }

        $sectionId = isset($_GET['sections_id']) && $_GET['sections_id'] !== ''
            ? $this->parsePositiveId($_GET['sections_id'])
            : null;
        $statusFilter = isset($_GET['status']) && trim((string)$_GET['status']) !== ''
            ? trim((string)$_GET['status'])
            : null;

        $result = $this->model->getExamScoreReport($examId, $sectionId, $statusFilter);
        if (isset($result['error'])) {
            [$status, $code, $message] = $result['error'];
            $this->json($status, null, ['code' => $code, 'message' => $message]);
            return;
        }

        $this->json(200, $result['data'], null);
    }

    // GET /reports/courses/{id}/scores (Task 46)
    public function courseScores(int|string $id): void
    {
        Security::requirePermission('exam.manage');

        $courseId = $this->parsePositiveId($id);
        if ($courseId === null) {
            $this->json(400, null, ['code' => 'INVALID_ID', 'message' => 'รหัสรายวิชาต้องเป็นจำนวนเต็มบวก']);
            return;
        }

        $result = $this->model->getCourseScoreReport($courseId);
        if (isset($result['error'])) {
            [$status, $code, $message] = $result['error'];
            $this->json($status, null, ['code' => $code, 'message' => $message]);
            return;
        }

        $this->json(200, $result['data'], null);
    }

    // POST /exams/{id}/statistics/calculate (Task 47 + Audit Log Task 50)
    public function calculateStatistics(int|string $id): void
    {
        Security::requirePermission('exam.manage');
        $this->verifyCsrfIfWeb();

        $examId = $this->parsePositiveId($id);
        if ($examId === null) {
            $this->json(400, null, ['code' => 'INVALID_ID', 'message' => 'รหัสชุดข้อสอบต้องเป็นจำนวนเต็มบวก']);
            return;
        }

        $input = $this->readInput();
        $sectionId = array_key_exists('sections_id', $input) && $input['sections_id'] !== null && $input['sections_id'] !== ''
            ? $this->parsePositiveId($input['sections_id'])
            : null;

        $selectionMode = strtolower(trim((string)($input['attempt_selection_mode'] ?? 'latest')));
        if (!in_array($selectionMode, ReportStatisticsModel::ALLOWED_SELECTION_MODES, true)) {
            $this->json(422, null, ['code' => 'INVALID_SELECTION_MODE', 'message' => 'attempt_selection_mode ไม่ถูกต้อง']);
            return;
        }

        $rMethod = strtolower(trim((string)($input['r_method'] ?? 'both')));
        if (!in_array($rMethod, ReportStatisticsModel::ALLOWED_R_METHODS, true)) {
            $this->json(422, null, ['code' => 'INVALID_R_METHOD', 'message' => 'r_method ต้องเป็น upper_lower, point_biserial หรือ both']);
            return;
        }

        $passPercent = null;
        if (array_key_exists('pass_percent', $input) && $input['pass_percent'] !== null && $input['pass_percent'] !== '') {
            if (!is_numeric($input['pass_percent']) || (float)$input['pass_percent'] < 0 || (float)$input['pass_percent'] > 100) {
                $this->json(422, null, ['code' => 'INVALID_PASS_PERCENT', 'message' => 'pass_percent ต้องอยู่ระหว่าง 0 ถึง 100']);
                return;
            }
            $passPercent = round((float)$input['pass_percent'], 2);
        }

        $actorId = $this->currentUserId();
        $result = $this->model->calculateAndStoreStatistics(
            $examId,
            $sectionId,
            $selectionMode,
            $rMethod,
            $passPercent,
            $actorId
        );

        if (isset($result['error'])) {
            [$status, $code, $message] = $result['error'];
            $this->json($status, null, ['code' => $code, 'message' => $message]);
            return;
        }

        $this->auditModel->record(
            $actorId,
            'CALCULATE_EXAM_STATISTICS',
            'exam_statistics',
            $result['data']['current']['id_exam_statistics'] ?? $examId,
            null,
            [
                'exams_id'        => $examId,
                'run_no'          => $result['data']['current']['run_no'] ?? null,
                'sample_size'     => $result['data']['current']['sample_size'] ?? 0,
                'selection_mode'  => $selectionMode,
            ],
            'คำนวณสถิติชุดข้อสอบและตั้งเป็นรอบปัจจุบัน',
            $this->clientIp(),
            $this->clientUserAgent()
        );

        $this->json(201, $result['data'], null);
    }

    // GET /exams/{id}/statistics (Task 47)
    public function showStatistics(int|string $id): void
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

        $statId = isset($_GET['statistics_id']) && $_GET['statistics_id'] !== ''
            ? $this->parsePositiveId($_GET['statistics_id'])
            : null;

        $this->json(200, $this->model->getStatisticsBundle($examId, $statId), null);
    }

    // POST /exam-statistics/{id}/set-current (Task 47 + Audit Log Task 50)
    public function setCurrentRun(int|string $id): void
    {
        Security::requirePermission('exam.manage');
        $this->verifyCsrfIfWeb();

        $statId = $this->parsePositiveId($id);
        if ($statId === null) {
            $this->json(400, null, ['code' => 'INVALID_ID', 'message' => 'รหัสรอบสถิติต้องเป็นจำนวนเต็มบวก']);
            return;
        }

        $actorId = $this->currentUserId();
        $result = $this->model->setCurrentRun($statId);
        if (isset($result['error'])) {
            [$status, $code, $message] = $result['error'];
            $this->json($status, null, ['code' => $code, 'message' => $message]);
            return;
        }

        $this->auditModel->record(
            $actorId,
            'SET_CURRENT_STATISTICS_RUN',
            'exam_statistics',
            $statId,
            ['is_current' => 0],
            ['is_current' => 1],
            'เปลี่ยนรอบสถิติหลัก (is_current)',
            $this->clientIp(),
            $this->clientUserAgent()
        );

        $this->json(200, $result['data'], null);
    }

    // GET /reports/exams/{id}/export (Task 49: ส่งออก CSV และ Excel ตามตัวกรอง)
    public function exportExamScores(int|string $id): void
    {
        Security::requirePermission('exam.manage');

        $examId = $this->parsePositiveId($id);
        if ($examId === null) {
            $this->json(400, null, ['code' => 'INVALID_ID', 'message' => 'รหัสชุดข้อสอบต้องเป็นจำนวนเต็มบวก']);
            return;
        }

        $sectionId = isset($_GET['sections_id']) && $_GET['sections_id'] !== ''
            ? $this->parsePositiveId($_GET['sections_id'])
            : null;
        $statusFilter = isset($_GET['status']) && trim((string)$_GET['status']) !== ''
            ? trim((string)$_GET['status'])
            : null;

        $report = $this->model->getExamScoreReport($examId, $sectionId, $statusFilter);
        if (isset($report['error'])) {
            [$status, $code, $message] = $report['error'];
            $this->json($status, null, ['code' => $code, 'message' => $message]);
            return;
        }

        $format = strtolower(trim((string)($_GET['format'] ?? 'csv')));
        $rows   = $report['data']['students'];
        $filenameBase = 'exam_' . $examId . '_scores_' . date('Ymd_His');

        $this->auditModel->record(
            $this->currentUserId(),
            'EXPORT_EXAM_SCORES',
            'exams',
            $examId,
            null,
            [
                'format'       => $format,
                'sections_id'  => $sectionId,
                'status'       => $statusFilter,
                'exported_row' => count($rows),
            ],
            'ส่งออกรายงานคะแนนชุดข้อสอบ',
            $this->clientIp(),
            $this->clientUserAgent()
        );

        if ($format === 'excel' || $format === 'xls') {
            header('Content-Type: application/vnd.ms-excel; charset=utf-8');
            header('Content-Disposition: attachment; filename="' . $filenameBase . '.xls"');
            echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
            echo '<Workbook xmlns="urn:schemas-microsoft-com:office:spreadsheet" xmlns:ss="urn:schemas-microsoft-com:office:spreadsheet">' . "\n";
            echo '<Worksheet ss:Name="Scores"><Table>' . "\n";

            $headers = ['student_code', 'display_name', 'email', 'section_no', 'participation_status', 'score', 'max_score', 'percent'];
            echo '<Row>';
            foreach ($headers as $h) {
                echo '<Cell><Data ss:Type="String">' . htmlspecialchars($h, ENT_XML1, 'UTF-8') . '</Data></Cell>';
            }
            echo "</Row>\n";

            foreach ($rows as $r) {
                $cells = [
                    (string)($r['student_code'] ?? ''),
                    (string)($r['display_name'] ?? ''),
                    (string)($r['email'] ?? ''),
                    (string)($r['section_no'] ?? ''),
                    (string)($r['participation_status'] ?? ''),
                    $r['score'] !== null ? (string)$r['score'] : '',
                    $r['max_score'] !== null ? (string)$r['max_score'] : '',
                    $r['percent'] !== null ? (string)$r['percent'] : '',
                ];
                echo '<Row>';
                foreach ($cells as $idx => $val) {
                    $type = ($idx >= 5 && $val !== '' && is_numeric($val)) ? 'Number' : 'String';
                    echo '<Cell><Data ss:Type="' . $type . '">' . htmlspecialchars($val, ENT_XML1, 'UTF-8') . '</Data></Cell>';
                }
                echo "</Row>\n";
            }

            echo "</Table></Worksheet></Workbook>\n";
            return;
        }

        // ค่าเริ่มต้น: CSV พร้อม UTF-8 BOM
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filenameBase . '.csv"');
        echo "\xEF\xBB\xBF";

        $out = fopen('php://output', 'w');
        fputcsv($out, ['student_code', 'display_name', 'email', 'section_no', 'participation_status', 'score', 'max_score', 'percent']);
        foreach ($rows as $r) {
            fputcsv($out, [
                $r['student_code'] ?? '',
                $r['display_name'] ?? '',
                $r['email'] ?? '',
                $r['section_no'] ?? '',
                $r['participation_status'] ?? '',
                $r['score'] ?? '',
                $r['max_score'] ?? '',
                $r['percent'] ?? '',
            ]);
        }
        fclose($out);
    }

    // GET /audit-logs (Task 50: หน้า/API ดูประวัติการทำงานสำหรับ Admin)
    public function indexAuditLogs(): void
    {
        Security::requirePermission('exam.manage');

        $entityTable = isset($_GET['entity_table']) && trim((string)$_GET['entity_table']) !== ''
            ? trim((string)$_GET['entity_table'])
            : null;
        $userId = isset($_GET['users_id']) && $_GET['users_id'] !== ''
            ? $this->parsePositiveId($_GET['users_id'])
            : null;
        $action = isset($_GET['action']) && trim((string)$_GET['action']) !== ''
            ? trim((string)$_GET['action'])
            : null;
        $limit = isset($_GET['limit']) && is_numeric($_GET['limit'])
            ? (int)$_GET['limit']
            : 50;

        $this->json(200, [
            'logs' => $this->auditModel->listLogs($entityTable, $userId, $action, $limit),
        ], null);
    }

    private function currentUserId(): int
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            @session_start();
        }
        $uid = $_SESSION['user_id'] ?? $_SESSION['id_users'] ?? $_SESSION['user']['id_users'] ?? 1;
        return max(1, (int)$uid);
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