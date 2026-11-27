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

// แนวคิดหลักและแนวคิดย่อย (Main Concepts & Sub Concepts)
$router->get('/courses/{id}/main-concepts', [ConceptController::class, 'mainIndex']);
$router->post('/courses/{id}/main-concepts', [ConceptController::class, 'mainStore']);
$router->post('/main-concepts/{id}', [ConceptController::class, 'mainUpdate']);
$router->post('/main-concepts/{id}/delete', [ConceptController::class, 'mainDestroy']);

$router->get('/main-concepts/{id}/sub-concepts', [ConceptController::class, 'subIndex']);
$router->post('/main-concepts/{id}/sub-concepts', [ConceptController::class, 'subStore']);
$router->post('/sub-concepts/{id}', [ConceptController::class, 'subUpdate']);
$router->post('/sub-concepts/{id}/delete', [ConceptController::class, 'subDestroy']);

// เนื้อหาการเรียนและผูกแนวคิด (Learning Contents - ลำดับ 21)
$router->get('/courses/{id}/learning-contents', [LearningContentController::class, 'index']);
$router->post('/courses/{id}/learning-contents', [LearningContentController::class, 'store']);
$router->get('/learning-contents/{id}', [LearningContentController::class, 'show']);
$router->post('/learning-contents/{id}', [LearningContentController::class, 'update']);
$router->post('/learning-contents/{id}/delete', [LearningContentController::class, 'destroy']);

// คลังข้อสอบและระบบเวอร์ชันข้อสอบ (Questions & Versions - ลำดับ 22-23)
$router->get('/questions', [QuestionController::class, 'index']);
$router->post('/questions', [QuestionController::class, 'store']);
$router->get('/questions/{id}', [QuestionController::class, 'show']);
$router->post('/questions/{id}', [QuestionController::class, 'update']);
$router->post('/questions/{id}/delete', [QuestionController::class, 'destroy']);
$router->get('/questions/{id}/versions', [QuestionController::class, 'versions']);
$router->post('/questions/{id}/versions/{versionId}', [QuestionController::class, 'deleteVersion']);

// สถานการณ์ข้อสอบและการผูกแนวคิดกับเวอร์ชันข้อสอบ (ลำดับ 26)
$router->get('/question-scenarios', [QuestionScenarioController::class, 'index']);
$router->post('/question-scenarios', [QuestionScenarioController::class, 'store']);
$router->get('/question-scenarios/{id}', [QuestionScenarioController::class, 'show']);
$router->post('/question-scenarios/{id}', [QuestionScenarioController::class, 'update']);
$router->get('/questions/{id}/versions/{versionId}/concepts', [QuestionScenarioController::class, 'getVersionConcepts']);
$router->post('/questions/{id}/versions/{versionId}/concepts', [QuestionScenarioController::class, 'syncVersionConcepts']);
$router->post('/questions/{id}/scenario', [QuestionScenarioController::class, 'attachScenario']);

// ชุดข้อสอบ (Exams, Exam Sections, Exam Questions - ลำดับ 27)
$router->get('/exams', [ExamController::class, 'index']);
$router->post('/exams', [ExamController::class, 'store']);
$router->get('/exams/{id}', [ExamController::class, 'show']);
$router->post('/exams/{id}', [ExamController::class, 'update']);
$router->post('/exams/{id}/sections', [ExamController::class, 'syncSections']);
$router->post('/exams/{id}/questions', [ExamController::class, 'syncQuestions']);

// Blueprint และการสุ่มข้อสอบ (ลำดับ 29)
$router->get('/exams/{id}/blueprint', [ExamBlueprintController::class, 'show']);
$router->post('/exams/{id}/blueprint', [ExamBlueprintController::class, 'store']);
$router->post('/exams/{id}/blueprint/preview', [ExamBlueprintController::class, 'preview']);
$router->post('/exams/{id}/blueprint/generate', [ExamBlueprintController::class, 'generate']);

// ระบบเปลี่ยนเวอร์ชันข้อสอบระหว่างสอบ (ลำดับ 31)
$router->get('/exams/{id}/version-releases', [ExamVersionReleaseController::class, 'index']);
$router->post('/exams/{id}/version-releases', [ExamVersionReleaseController::class, 'store']);

// กำหนดเวลาและเผยแพร่ชุดสอบ (ลำดับ 32)
$router->post('/exams/{id}/schedule', [ExamScheduleController::class, 'updateSchedule']);
$router->get('/exams/{id}/publish-checklist', [ExamScheduleController::class, 'checklist']);
$router->post('/exams/{id}/publish', [ExamScheduleController::class, 'publish']);
$router->post('/exams/{id}/close', [ExamScheduleController::class, 'close']);

// สิทธิ์ผู้เข้าสอบและโควตา Grant (ลำดับ 34)
$router->get('/exams/{id}/eligibilities/candidates', [ExamEligibilityController::class, 'candidates']);
$router->get('/exams/{id}/eligibilities', [ExamEligibilityController::class, 'index']);
$router->post('/exams/{id}/eligibilities', [ExamEligibilityController::class, 'store']);
$router->post('/eligibilities/{id}/grants', [ExamEligibilityController::class, 'addGrant']);
$router->post('/eligibilities/{id}/revoke', [ExamEligibilityController::class, 'revoke']);
$router->post('/eligibility-grants/{id}/revoke', [ExamEligibilityController::class, 'revokeGrant']);

// รหัสเข้าสอบ 5 ตัวอักษร (ลำดับ 35)
$router->get('/exams/{id}/access-codes', [ExamAccessCodeController::class, 'index']);
$router->post('/exams/{id}/access-codes', [ExamAccessCodeController::class, 'store']);
$router->post('/access-codes/{id}/revoke', [ExamAccessCodeController::class, 'revoke']);

// การเข้าสอบ บันทึกคำตอบ และส่งข้อสอบของนิสิต (ลำดับ 37, 38, 39)
$router->post('/exams/{id}/attempts/start', [AttemptController::class, 'start']);
$router->get('/attempts/{id}', [AttemptController::class, 'show']);
$router->post('/attempts/{id}/resume', [AttemptController::class, 'resume']);
$router->post('/attempts/{id}/answers', [AttemptController::class, 'saveAnswer']);
$router->post('/attempts/{id}/submit', [AttemptController::class, 'submit']);

// กู้คืนเมื่อหลุดการเชื่อมต่อ (ลำดับ 41)
$router->get('/exams/{id}/attempts/active', [AttemptController::class, 'activeAttempt']);

// ตรวจปรนัย มอบหมายงานตรวจอัตนัย ให้คะแนน Rubric และประวัติแก้คะแนน (ลำดับ 42, 43, 45)
$router->post('/attempts/{id}/grade-objective', [GradingController::class, 'gradeObjective']);
$router->get('/exams/{id}/grading-assignments', [GradingController::class, 'indexAssignments']);
$router->post('/exams/{id}/grading-assignments', [GradingController::class, 'assignGrader']);
$router->post('/grading-assignments/{id}/status', [GradingController::class, 'updateAssignmentStatus']);
$router->post('/attempt-answers/{id}/rubric-grade', [GradingController::class, 'rubricGrade']);
$router->get('/attempt-answers/{id}/grading', [GradingController::class, 'showGrading']);