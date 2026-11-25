<?php

declare(strict_types=1);

class ExamController extends Controller
{
    public function create(): void
    {
        Security::startSession();
        Security::requireRole(['instructor', 'admin']);

        $user = Security::currentUser();
        $createdBy = (int)($user['id_users'] ?? 0);

        $examModel = new ExamModel();
        $exams = $examModel->getExamsByCreator($createdBy);

        $this->view('exams/create', [
            'pageTitle' => 'สร้างชุดข้อสอบ',
            'old'       => [],
            'error'     => null,
            'exams'     => $exams,
        ]);
    }

    public function store(): void
    {
        Security::startSession();
        Security::requireRole(['instructor', 'admin']);

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('/exams/create');
            return;
        }

        // ย้ายมาไว้ด้านบนเพื่อให้ใช้ $createdBy ได้ทั้ง 2 จุด
        $user      = Security::currentUser();
        $createdBy = (int)($user['id_users'] ?? 0);
        $examModel = new ExamModel();

        $token = $_POST['csrf_token'] ?? null;
        if (!Security::verifyCsrf(is_string($token) ? $token : null)) {
            http_response_code(419);
            $this->view('exams/create', [
                'pageTitle' => 'สร้างชุดข้อสอบ',
                'error'     => 'เซสชันฟอร์มหมดอายุ กรุณาลองใหม่อีกครั้ง',
                'old'       => $_POST,
                'exams'     => $examModel->getExamsByCreator($createdBy), // 👈 จุดที่ 1
            ]);
            return;
        }

        $title            = trim((string)($_POST['title'] ?? ''));
        $durationMin      = (int)($_POST['duration_min'] ?? $_POST['duration_minutes'] ?? 60);
        $kind             = (string)($_POST['kind'] ?? 'quiz');
        $mode             = (string)($_POST['mode'] ?? 'fixed');
        $maxAttempts      = max(1, (int)($_POST['max_attempts'] ?? 1));
        $courseOfferingId = max(1, (int)($_POST['course_offerings_id'] ?? 1));
        $courseId         = max(1, (int)($_POST['courses_id'] ?? 1));
        $openAt           = trim((string)($_POST['open_at'] ?? ''));
        $closeAt          = trim((string)($_POST['close_at'] ?? ''));

        $openAtSql  = $openAt !== '' ? str_replace('T', ' ', $openAt) . (strlen($openAt) === 16 ? ':00' : '') : date('Y-m-d H:i:s');
        $closeAtSql = $closeAt !== '' ? str_replace('T', ' ', $closeAt) . (strlen($closeAt) === 16 ? ':00' : '') : date('Y-m-d H:i:s', strtotime('+7 days'));

        if ($title === '' || $durationMin <= 0 || $createdBy <= 0) {
            $this->view('exams/create', [
                'pageTitle' => 'สร้างชุดข้อสอบ',
                'error'     => 'กรุณากรอกชื่อชุดข้อสอบและระยะเวลาสอบให้ถูกต้อง',
                'old'       => $_POST,
                'exams'     => $examModel->getExamsByCreator($createdBy), // 👈 จุดที่ 2
            ]);
            return;
        }

        $examModel->createExam([
            'course_offerings_id' => $courseOfferingId,
            'courses_id'          => $courseId,
            'created_by'          => $createdBy,
            'kind'                => in_array($kind, ['midterm', 'final', 'quiz', 'pre_post'], true) ? $kind : 'quiz',
            'title'               => $title,
            'mode'                => in_array($mode, ['fixed', 'blueprint'], true) ? $mode : 'fixed',
            'duration_min'        => $durationMin,
            'max_attempts'        => $maxAttempts,
            'open_at'             => $openAtSql,
            'close_at'            => $closeAtSql,
        ]);

        $this->redirect('/exams/create');
    }
    
}
