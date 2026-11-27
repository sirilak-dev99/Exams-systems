<?php
// ---------- ค่าพื้นฐาน ----------
$pageTitle = $pageTitle ?? 'ไม่มีสิทธิ์เข้าถึง';
$message   = $message ?? 'คุณไม่มีสิทธิ์เข้าถึงหน้านี้';
require dirname(__DIR__) . '/includes/header.php';
?>
<?php /* ---------- ข้อความ ---------- */ ?>
<section class="panel">
    <h1>ไม่มีสิทธิ์เข้าถึง (403)</h1>
    <p><?= Security::escape((string)$message) ?></p>
    <p><a href="<?= Security::escape($appUrl) ?>/">กลับหน้าหลัก</a></p>
</section>
<?php require dirname(__DIR__) . '/includes/footer.php'; ?>
