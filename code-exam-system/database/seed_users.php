<?php

declare(strict_types=1);

$basePath = dirname(__DIR__);
$envFile  = $basePath . '/config/env.php';
if (is_file($envFile)) {
    require_once $envFile;
}

require_once $basePath . '/core/Database.php';

$pdo = Database::getConnection();

// 1. Seed บัญชีทดสอบ (ใช้ INSERT IGNORE เพื่อรันซ้ำได้ไม่ Error)
$usersToSeed = [
    [
        'email'        => 'admin@buu.ac.th',
        'student_code' => null,
        'display_name' => 'ผู้ดูแลระบบทดสอบ',
        'password'     => '123456',
        'role'         => 'admin',
    ],
    [
        'email'        => 'prof.test@buu.ac.th',
        'student_code' => null,
        'display_name' => 'อาจารย์ทดสอบ',
        'password'     => '123456',
        'role'         => 'instructor',
    ],
    [
        'email'        => 'stu.test@buu.ac.th',
        'student_code' => '66169999',
        'display_name' => 'นิสิตทดสอบ',
        'password'     => '123456',
        'role'         => 'student',
    ],
];

$stmtUser = $pdo->prepare("
    INSERT IGNORE INTO users (email, student_code, display_name, active, password_hash, created_at, updated_at)
    VALUES (:email, :student_code, :display_name, 1, :password_hash, NOW(), NOW())
");

$stmtRole = $pdo->prepare("
    INSERT IGNORE INTO user_roles (users_id, roles_id)
    SELECT u.id_users, r.id_roles
    FROM users u
    JOIN roles r ON r.name = :role_name
    WHERE u.email = :email
");

foreach ($usersToSeed as $u) {
    $stmtUser->execute([
        ':email'         => $u['email'],
        ':student_code'  => $u['student_code'],
        ':display_name'  => $u['display_name'],
        ':password_hash' => password_hash($u['password'], PASSWORD_BCRYPT),
    ]);

    $stmtRole->execute([
        ':email'     => $u['email'],
        ':role_name' => $u['role'],
    ]);
}

// 2. Seed Permissions และผูกสิทธิ์ให้แต่ละ Role
$pdo->exec("
    INSERT IGNORE INTO permissions (code, description) VALUES
    ('user.manage', 'จัดการบัญชีผู้ใช้และบทบาท'),
    ('system.settings', 'ตั้งค่าระบบและดูบันทึกการทำงาน'),
    ('program.manage', 'จัดการหลักสูตรและรุ่นหลักสูตร'),
    ('course.manage', 'จัดการรายวิชาและรายวิชาที่เปิดสอน'),
    ('course.view', 'ดูรายการรายวิชาที่เปิดสอน'),
    ('question.manage', 'สร้าง แก้ไข และลบคำถามในคลังข้อสอบ'),
    ('exam.manage', 'สร้าง แก้ไข เปิด-ปิด และจัดการชุดข้อสอบ'),
    ('exam.grade', 'ตรวจให้คะแนนและประกาศผลสอบ'),
    ('attempt.take', 'เข้าทำข้อสอบและบันทึกคำตอบ'),
    ('attempt.view_own', 'ดูประวัติและผลสอบของตนเอง'),
    ('report.view', 'ดูรายงานสรุปผลสอบและสถิติ')
");

$pdo->exec("
    INSERT IGNORE INTO role_permissions (roles_id, permissions_id)
    SELECT r.id_roles, p.id_permissions
    FROM roles r
    CROSS JOIN permissions p
    WHERE r.name = 'admin'
");

$pdo->exec("
    INSERT IGNORE INTO role_permissions (roles_id, permissions_id)
    SELECT r.id_roles, p.id_permissions
    FROM roles r
    JOIN permissions p ON p.code IN ('program.manage', 'course.manage', 'course.view', 'report.view')
    WHERE r.name = 'academic_staff'
");

$pdo->exec("
    INSERT IGNORE INTO role_permissions (roles_id, permissions_id)
    SELECT r.id_roles, p.id_permissions
    FROM roles r
    JOIN permissions p ON p.code IN ('course.view', 'question.manage', 'exam.manage', 'exam.grade', 'report.view')
    WHERE r.name = 'instructor'
");

$pdo->exec("
    INSERT IGNORE INTO role_permissions (roles_id, permissions_id)
    SELECT r.id_roles, p.id_permissions
    FROM roles r
    JOIN permissions p ON p.code IN ('attempt.take', 'attempt.view_own')
    WHERE r.name = 'student'
");

echo "Seed บัญชีทดสอบ, Roles และ Permissions เรียบร้อยแล้ว (รหัสผ่านทุกบัญชีคือ 123456)\n";