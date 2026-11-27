<?php
// ---------- ค่าพื้นฐาน ----------
$crumbTitle = 'ข่าวประชาสัมพันธ์';
require dirname(__DIR__) . '/includes/header.php';
?>
<?php /* ---------- หัวข้อ ---------- */ ?>
<div class="page-title">
    <h1><?= Security::escape($pageTitle) ?></h1>
    <p>ข่าวและประกาศจากคณะ</p>
</div>

<?php /* ---------- รายการ ---------- */ ?>
<section class="card">
    <?php $pendingApi = 'ข่าว (announcements) ยังไม่มี endpoint สำหรับอ่านรายการ'; require dirname(__DIR__) . '/includes/api-pending.php'; ?>
    <div class="empty"><?= ui_icon('news', 40) ?><b>ยังไม่มีข่าวประชาสัมพันธ์</b></div>
</section>
<?php require dirname(__DIR__) . '/includes/footer.php'; ?>
