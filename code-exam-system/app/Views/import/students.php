<?php
// ---------- ค่าพื้นฐาน ----------
$crumbTitle = 'นำเข้าข้อมูล';
require dirname(__DIR__) . '/includes/header.php';

// ---------- รูปแบบไฟล์ ----------
$importIntro   = 'เพิ่มบัญชีนิสิตและลงทะเบียนเข้าหลักสูตรจากไฟล์ Excel';
$importPending = 'ยังไม่มี endpoint สร้างบัญชีนิสิต (users) และการลงทะเบียนหลักสูตร (student_program_enrollments) จากไฟล์';
$importColumns = [
    ['student_code',   'รหัสนิสิต 8 หลัก (ไม่ซ้ำ)',                 true,  '66160001'],
    ['display_name',   'ชื่อ-นามสกุล (ไม่เกิน 100 ตัวอักษร)',        true,  'นางสาวกมลวรรณ ศรีสุข'],
    ['email',          'อีเมลมหาวิทยาลัย (ไม่ซ้ำ)',                  true,  '66160001@buu.ac.th'],
    ['program_code',   'รหัสหลักสูตร',                              true,  'BNS'],
    ['version_no',     'ฉบับหลักสูตร (ฉบับที่)',                     true,  '1'],
    ['admission_year', 'ปีที่เข้าศึกษา (พ.ศ.)',                       true,  '2566'],
    ['year_level',     'ชั้นปีปัจจุบัน',                               false, '1'],
];
require dirname(__DIR__) . '/includes/import-form.php';
require dirname(__DIR__) . '/includes/footer.php';
