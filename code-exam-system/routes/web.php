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