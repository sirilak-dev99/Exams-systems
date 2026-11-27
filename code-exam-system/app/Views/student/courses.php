<?php
// ---------- ค่าพื้นฐาน ----------
$crumbTitle = 'รายวิชาของฉัน';
require dirname(__DIR__) . '/includes/header.php';
?>
<?php /* ---------- หัวข้อ ---------- */ ?>
<div class="page-title">
    <h1><?= Security::escape($pageTitle) ?></h1>
    <p>รายวิชาที่ลงทะเบียนในแต่ละภาคการศึกษา</p>
</div>

<?php /* ---------- รายการ ---------- */ ?>
<section class="card">
    <?php $pendingApi = 'รายวิชาที่นิสิตลงทะเบียน (course_members) ต้องมี endpoint ที่คืนรายวิชาของผู้ใช้ที่เข้าสู่ระบบ'; require dirname(__DIR__) . '/includes/api-pending.php'; ?>
    <div class="empty"><?= ui_icon('book', 40) ?><b>ยังไม่มีรายวิชาที่แสดง</b></div>
</section>
<?php require dirname(__DIR__) . '/includes/footer.php'; ?>
