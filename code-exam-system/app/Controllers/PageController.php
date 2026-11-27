<?php

declare(strict_types=1);

final class PageController extends Controller
{
    // --- กลุ่มหน้าแอดมิน ---
    public function adminUsers(): void
    {
        Security::requireRole(['admin']);
        $this->renderPage('admin/users', 'จัดการผู้ใช้');
    }

    public function adminRoles(): void
    {
        Security::requireRole(['admin']);
        $this->renderPage('admin/roles', 'จัดการบทบาทและสิทธิ์');
    }

    public function adminStudents(): void
    {
        Security::requireRole(['admin', 'academic_staff']);
        $this->renderPage('admin/students', 'จัดการนิสิต');
    }

    public function adminNews(): void
    {
        Security::requireRole(['admin', 'academic_staff']);
        $this->renderPage('admin/news', 'ข่าวประชาสัมพันธ์');
    }

    public function adminSettings(): void
    {
        Security::requireRole(['admin']);
        $this->renderPage('admin/settings', 'ตั้งค่าระบบ');
    }

    // --- กลุ่มหน้านิสิต ---
    public function studentCourses(): void
    {
        Security::requireRole(['student']);
        $this->renderPage('student/courses', 'รายวิชาของฉัน');
    }

    public function studentExams(): void
    {
        Security::requireRole(['student']);
        $this->renderPage('student/exams', 'การสอบของฉัน');
    }

    public function studentResults(): void
    {
        Security::requireRole(['student']);
        $this->renderPage('student/results', 'ผลการสอบ');
    }

    public function studentNews(): void
    {
        Security::requireRole(['student']);
        $this->renderPage('student/news', 'ข่าวประชาสัมพันธ์');
    }

    public function studentProfile(): void
    {
        Security::requireRole(['student']);
        $this->renderPage('student/profile', 'โปรไฟล์');
    }

    // --- กลุ่มหน้านำเข้า Excel ---
    public function importIndex(): void
    {
        Security::requireRole(['admin', 'academic_staff', 'instructor']);
        $this->renderPage('import/index', 'นำเข้าข้อมูลจาก Excel');
    }

    public function importCourses(): void
    {
        Security::requireRole(['admin', 'academic_staff']);
        $this->renderPage('import/courses', 'นำเข้ารายวิชาจาก Excel');
    }

    public function importStudents(): void
    {
        Security::requireRole(['admin', 'academic_staff']);
        $this->renderPage('import/students', 'นำเข้านิสิตจาก Excel');
    }

    private function renderPage(string $viewPath, string $pageTitle): void
    {
        if (Security::wantsJson()) {
            http_response_code(200);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode([
                'data'  => ['page' => $viewPath, 'title' => $pageTitle],
                'error' => null,
            ], JSON_UNESCAPED_UNICODE);
            return;
        }

        $this->view($viewPath, [
            'pageTitle' => $pageTitle,
            'user'      => Security::currentUser(),
        ]);
    }
}