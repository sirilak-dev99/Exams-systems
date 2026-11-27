<?php
// ---------- ค่าพื้นฐาน ----------
$crumbTitle = 'นำเข้าข้อมูล';
require dirname(__DIR__) . '/includes/header.php';

// ---------- รูปแบบไฟล์ ----------
$importIntro   = 'เพิ่มรายวิชาหลายรายการพร้อมกันจากไฟล์ Excel';
$importPending = 'ตอนนี้เพิ่มรายวิชาได้ทีละรายการผ่าน POST /courses ยังไม่มี endpoint นำเข้าทั้งไฟล์';
$importColumns = [
    ['code',         'รหัสวิชา (ไม่ซ้ำ ไม่เกิน 20 ตัวอักษร)',  true, '100105'],
    ['name',         'ชื่อวิชา (ไม่เกิน 150 ตัวอักษร)',        true, 'การพยาบาลพื้นฐาน'],
    ['course_group', 'ชื่อกลุ่มวิชาที่มีอยู่ในระบบ',            true, 'วิชาชีพ'],
];
require dirname(__DIR__) . '/includes/import-form.php';
require dirname(__DIR__) . '/includes/footer.php';
