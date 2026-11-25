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