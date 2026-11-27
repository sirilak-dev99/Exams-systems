<?php
// ---------- ค่าพื้นฐาน ----------
$pageTitle = $pageTitle ?? 'ไม่พบหน้าที่ต้องการ';
require dirname(__DIR__) . '/includes/header.php';
?>
<?php /* ---------- ข้อความ ---------- */ ?>
<section class="card error-card">
    <span class="error-card__code">404</span>
    <h1>ไม่พบหน้าที่ต้องการ</h1>
    <p class="muted">ลิงก์อาจไม่ถูกต้อง หรือหน้านี้ถูกย้ายไปแล้ว</p>
    <a class="btn btn--primary" href="<?= Security::escape($appUrl) ?>/">กลับหน้าหลัก</a>
</section>
<?php require dirname(__DIR__) . '/includes/footer.php'; ?>
