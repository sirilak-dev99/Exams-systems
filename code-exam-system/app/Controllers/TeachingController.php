<?php

declare(strict_types=1);

/**
 * ============================================================
 *  TeachingController — หน้าฝั่งอาจารย์ (อ่านอย่างเดียว)
 * ------------------------------------------------------------
 *  GET /my-courses        รายวิชาที่สอน              → views/courses/teaching.php  (Course.html)
 *  GET /my-courses/{id}   รายละเอียดรายวิชาที่สอน     → views/courses/show.php      (CourseView.html)
 *  GET /exams             จัดการชุดสอบ (รายการ)       → views/exams/index.php       (Exams.html)
 *
 *  สิทธิ์: อาจารย์ / แอดมิน (Security::requireRole)
 *  ส่ง Accept: application/json → ตอบ { data, error } แบบเดียวกับ Controller อื่นในโปรเจกต์
 *  SQL ทั้งหมดอยู่ใน Models/TeachingModel.php
 * ============================================================
 */
final class TeachingController extends Controller
{
    private TeachingModel $model;

    public function __construct()
    {
        $this->model = new TeachingModel();
    }

    /**
     * GET /my-courses — รายวิชาที่ผู้ใช้สอน (views/courses/teaching.php)
     * JSON: { offerings }
     */
    public function myCourses(): void
    {
        Security::requireRole(['instructor', 'admin']);
        $offerings = $this->model->getTeachingOfferings($this->userId());

        if (Security::wantsJson()) {
            $this->json(200, ['offerings' => $offerings], null);
            return;
        }
        $this->view('courses/teaching', [
            'pageTitle'    => 'จัดการรายวิชา',
            'offerings'    => $offerings,
            'detailRoutes' => true,
        ]);
    }

    /**
     * GET /my-courses/{id} — รายละเอียดรายวิชาที่สอน 9 แท็บ (views/courses/show.php)
     * id = id_course_offerings · ไม่ได้สอนวิชานี้ (และไม่ใช่ admin) → 404
     * JSON: { offering, stats, staff, sections, students, chapters, concepts, learningOutcomes, contents, questions, exams }
     */
    public function courseShow(int|string $id): void
    {
        Security::requireRole(['instructor', 'admin']);
        $offeringId = $this->parsePositiveId($id);
        $offering = $offeringId === null ? null
            : $this->model->findTeachingOffering($offeringId, $this->userId(), Security::hasRole('admin'));

        if ($offering === null) {
            if (Security::wantsJson()) {
                $this->json(404, null, ['code' => 'NOT_FOUND', 'message' => 'ไม่พบรายวิชา หรือท่านไม่ได้เป็นผู้สอนของรายวิชานี้']);
                return;
            }
            http_response_code(404);
            $this->view('courses/show', ['pageTitle' => 'ไม่พบรายวิชา', 'offering' => null]);
            return;
        }

        $detail = $this->model->getOfferingDetail((int)$offering['id_course_offerings'], (int)$offering['courses_id']);
        if (Security::wantsJson()) {
            $this->json(200, ['offering' => $offering] + $detail, null);
            return;
        }
        $this->view('courses/show', ['pageTitle' => $offering['code'] . ' ' . $offering['name'], 'offering' => $offering] + $detail);
    }

    /**
     * GET /exams — รายการชุดสอบ (views/exams/index.php)
     * อาจารย์เห็นชุดที่ตัวเองสร้าง · admin เห็นทั้งหมด
     * ตัวกรอง: courses_id, academic_terms_id, kind, status, q · JSON: { exams, status_counts }
     */
    public function examIndex(): void
    {
        Security::requireRole(['instructor', 'admin']);
        $uid = $this->userId();
        $isAdmin = Security::hasRole('admin');
        $filters = [
            'courses_id'        => $_GET['courses_id'] ?? null,
            'academic_terms_id' => $_GET['academic_terms_id'] ?? null,
            'kind'              => $_GET['kind'] ?? null,
            'status'            => $_GET['status'] ?? null,
            'q'                 => $_GET['q'] ?? null,
        ];
        $exams = $this->model->getExamList($uid, $isAdmin, $filters);
        $statusCounts = $this->model->getExamStatusCounts($uid, $isAdmin);

        if (Security::wantsJson()) {
            $this->json(200, ['exams' => $exams, 'status_counts' => $statusCounts], null);
            return;
        }
        [$courses, $terms] = $this->model->getExamFilterOptions($uid, $isAdmin);
        $this->view('exams/index', [
            'pageTitle'    => 'จัดการชุดสอบ',
            'exams'        => $exams,
            'courses'      => $courses,
            'terms'        => $terms,
            'statusCounts' => $statusCounts,
            'total'        => $statusCounts['all'],
            'detailRoutes' => false,   // ยังไม่มี /exams/{id}
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

    private function json(int $status, mixed $data, ?array $error): void
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['data' => $data, 'error' => $error], JSON_UNESCAPED_UNICODE);
    }
}
