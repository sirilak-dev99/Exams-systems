<?php

declare(strict_types=1);

$basePath = dirname(__DIR__);
$envFile  = $basePath . '/config/env.php';
if (is_file($envFile)) {
    require_once $envFile;
}

require_once $basePath . '/core/Database.php';

$pdo = Database::getConnection();

$usersToSeed = [
    [
        'email'        => 'admin@buu.ac.th',
        'student_code' => null,
        'display_name' => 'ผู้ดูแลระบบทดสอบ',
        'password'     => '123456',
    ],
    [
        'email'        => 'prof.test@buu.ac.th',
        'student_code' => null,
        'display_name' => 'อาจารย์ทดสอบ',
        'password'     => '123456',
    ],
    [
        'email'        => 'stu.test@buu.ac.th',
        'student_code' => '66169999',
        'display_name' => 'นิสิตทดสอบ',
        'password'     => '123456',
    ],
];

$stmt = $pdo->prepare("
    INSERT INTO users (email, student_code, display_name, active, password_hash, created_at, updated_at)
    VALUES (:email, :student_code, :display_name, 1, :password_hash, NOW(), NOW())
");

foreach ($usersToSeed as $u) {
    $stmt->execute([
        ':email'         => $u['email'],
        ':student_code'  => $u['student_code'],
        ':display_name'  => $u['display_name'],
        ':password_hash' => password_hash($u['password'], PASSWORD_BCRYPT),
    ]);
}

echo "Seed บัญชีทดสอบเรียบร้อย (รหัสผ่านทุกบัญชีคือ 123456)\n";