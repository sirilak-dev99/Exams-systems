<?php
// ---------- ค่าพื้นฐาน ----------
$crumbTitle = 'ประชาสัมพันธ์';
require dirname(__DIR__) . '/includes/header.php';
?>
<?php /* ---------- หัวข้อ ---------- */ ?>
<div class="page-head">
    <div class="page-title">
        <h1><?= Security::escape($pageTitle) ?></h1>
        <p>สร้างและเผยแพร่ข่าวประชาสัมพันธ์ให้ผู้สอนและนิสิต</p>
    </div>
    <button class="btn btn--primary" type="button" disabled title="รอ API ข่าวประชาสัมพันธ์">+ สร้างข่าว <small>เร็วๆ นี้</small></button>
</div>

<?php /* ---------- รายการ ---------- */ ?>
<section class="card">
    <?php $pendingApi = 'ตาราง announcements มีในฐานข้อมูลแล้ว แต่ยังไม่มี endpoint สำหรับดู สร้าง และแก้ไขข่าว'; require dirname(__DIR__) . '/includes/api-pending.php'; ?>
    <div class="empty"><?= ui_icon('news', 40) ?><b>ยังไม่มีข่าวประชาสัมพันธ์</b></div>
</section>
<?php require dirname(__DIR__) . '/includes/footer.php'; ?>
