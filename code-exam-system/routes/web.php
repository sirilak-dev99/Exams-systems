<?php

declare(strict_types=1);

/** @var Router $router */
$router->get('/', [HomeController::class, 'index']);
$router->get('/login', [AuthController::class, 'showLogin']);
$router->post('/login', [AuthController::class, 'login']);
$router->post('/logout', [AuthController::class, 'logout']);

// สร้างชุดข้อสอบ (สำหรับอาจารย์ / แอดมิน)
$router->get('/exams/create', [ExamController::class, 'create']);
$router->post('/exams/create', [ExamController::class, 'store']);

// หลักสูตรและรุ่นหลักสูตร (Programs & Versions)
$router->get('/programs', [ProgramController::class, 'index']);
$router->post('/programs', [ProgramController::class, 'store']);
$router->post('/programs/{id}', [ProgramController::class, 'update']); // ใช้ยิงอัปเดตหลักสูตร
$router->post('/programs/{id}/delete', [ProgramController::class, 'destroy']); // หรือเรียกผ่าน _method=DELETE ใน update/destroy

$router->get('/programs/{id}/versions', [ProgramController::class, 'versions']);
$router->post('/programs/{id}/versions', [ProgramController::class, 'storeVersion']);

// กลุ่มวิชาและรายวิชา (Course Groups & Courses)
$router->get('/course-groups', [CourseController::class, 'groupIndex']);
$router->post('/course-groups', [CourseController::class, 'groupStore']);
$router->post('/course-groups/{id}', [CourseController::class, 'groupUpdate']);
$router->post('/course-groups/{id}/delete', [CourseController::class, 'groupDestroy']);

$router->get('/courses', [CourseController::class, 'index']);
$router->post('/courses', [CourseController::class, 'store']);
$router->post('/courses/{id}', [CourseController::class, 'update']);
$router->post('/courses/{id}/delete', [CourseController::class, 'destroy']);

// ผูกวิชากับรุ่นหลักสูตร (Program Version Courses)
$router->get('/program-versions/{id}/courses', [ProgramVersionCourseController::class, 'index']);
$router->post('/program-versions/{id}/courses', [ProgramVersionCourseController::class, 'store']);
$router->post('/program-version-courses/{id}', [ProgramVersionCourseController::class, 'update']);
$router->post('/program-version-courses/{id}/delete', [ProgramVersionCourseController::class, 'destroy']);

// ภาคเรียน (Academic Terms)
$router->get('/academic-terms', [AcademicTermController::class, 'index']);
$router->post('/academic-terms', [AcademicTermController::class, 'store']);
$router->post('/academic-terms/{id}', [AcademicTermController::class, 'update']);
$router->post('/academic-terms/{id}/delete', [AcademicTermController::class, 'destroy']);

// เปิดสอนรายวิชา (Course Offerings) และกลุ่มเรียน (Sections)
$router->get('/course-offerings', [CourseOfferingController::class, 'index']);
$router->post('/course-offerings', [CourseOfferingController::class, 'store']);
$router->post('/course-offerings/{id}', [CourseOfferingController::class, 'update']);
$router->post('/course-offerings/{id}/delete', [CourseOfferingController::class, 'destroy']);

$router->get('/course-offerings/{id}/sections', [CourseOfferingController::class, 'sectionIndex']);
$router->post('/course-offerings/{id}/sections', [CourseOfferingController::class, 'sectionStore']);
$router->post('/sections/{id}', [CourseOfferingController::class, 'sectionUpdate']);
$router->post('/sections/{id}/delete', [CourseOfferingController::class, 'sectionDestroy']);

// สมาชิกนิสิตและผู้รับผิดชอบประจำกลุ่มเรียน (Course Members & Section Staff)
$router->get('/sections/{id}/members', [SectionMemberController::class, 'memberIndex']);
$router->post('/sections/{id}/members', [SectionMemberController::class, 'memberStore']);
$router->post('/course-members/{id}', [SectionMemberController::class, 'memberUpdate']);
$router->post('/course-members/{id}/drop', [SectionMemberController::class, 'memberDrop']);

$router->get('/sections/{id}/staff', [SectionMemberController::class, 'staffIndex']);
$router->post('/sections/{id}/staff', [SectionMemberController::class, 'staffStore']);
$router->post('/section-staff/{id}', [SectionMemberController::class, 'staffUpdate']);
$router->post('/section-staff/{id}/delete', [SectionMemberController::class, 'staffDestroy']);

// บทเรียน (Chapters)
$router->get('/courses/{id}/chapters', [ChapterController::class, 'index']);
$router->post('/courses/{id}/chapters', [ChapterController::class, 'store']);
$router->post('/chapters/{id}', [ChapterController::class, 'update']);
$router->post('/chapters/{id}/deactivate', [ChapterController::class, 'deactivate']);