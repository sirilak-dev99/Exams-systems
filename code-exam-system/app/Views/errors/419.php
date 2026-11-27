<?php
// ---------- ค่าพื้นฐาน ----------
$pageTitle = $pageTitle ?? 'หน้านี้หมดอายุ';
$message   = $message ?? 'เซสชันหมดอายุ กรุณาลองใหม่อีกครั้ง';
require dirname(__DIR__) . '/includes/header.php';
?>
<?php /* ---------- ข้อความ ---------- */ ?>
<section class="panel">
    <h1>หน้านี้หมดอายุ (419)</h1>
    <p><?= Security::escape((string)$message) ?></p>

    <?php /* ---------- ปุ่ม ---------- */ ?>
    <?php if (Security::currentUser() !== null): ?>
        <form method="post" action="<?= Security::escape($appUrl) ?>/logout">
            <input type="hidden" name="_csrf" value="<?= Security::escape(Security::csrfToken()) ?>">
            <button type="submit">ออกจากระบบอีกครั้ง</button>
        </form>
    <?php endif; ?>
    <p><a href="<?= Security::escape($appUrl) ?>/">กลับหน้าหลัก</a></p>
</section>
<?php require dirname(__DIR__) . '/includes/footer.php'; ?>
