<?php
// ---------- ค่าพื้นฐาน ----------
$pageTitle = $pageTitle ?? 'ไม่มีสิทธิ์เข้าถึง';
$message   = $message ?? 'คุณไม่มีสิทธิ์เข้าถึงหน้านี้';
require dirname(__DIR__) . '/includes/header.php';
?>
<?php /* ---------- ข้อความ ---------- */ ?>
<section class="card error-card">
    <span class="error-card__code">403</span>
    <h1>ไม่มีสิทธิ์เข้าถึง</h1>
    <p class="muted"><?= Security::escape((string)$message) ?></p>
    <a class="btn btn--primary" href="<?= Security::escape($appUrl) ?>/">กลับหน้าหลัก</a>
</section>
<?php require dirname(__DIR__) . '/includes/footer.php'; ?>
