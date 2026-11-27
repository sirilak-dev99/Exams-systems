<?php
// ---------- ค่าพื้นฐาน ----------
$crumbTitle = 'ผลการสอบ';
require dirname(__DIR__) . '/includes/header.php';
?>
<?php /* ---------- หัวข้อ ---------- */ ?>
<div class="page-title">
    <h1><?= Security::escape($pageTitle) ?></h1>
    <p>คะแนนและผลการสอบของแต่ละรายวิชา</p>
</div>

<?php /* ---------- รายการ ---------- */ ?>
<section class="card">
    <?php $pendingApi = 'รายงานคะแนนปัจจุบัน (/reports/...) ใช้สิทธิ์ report.view ของอาจารย์ ต้องมี endpoint ผลสอบของนิสิตเอง (attempt.view_own)'; require dirname(__DIR__) . '/includes/api-pending.php'; ?>
    <div class="empty"><?= ui_icon('chart', 40) ?><b>ยังไม่มีผลการสอบที่ประกาศ</b></div>
</section>
<?php require dirname(__DIR__) . '/includes/footer.php'; ?>
