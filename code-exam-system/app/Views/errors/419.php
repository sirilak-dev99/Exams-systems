<?php
// ---------- ค่าพื้นฐาน ----------
$pageTitle = $pageTitle ?? 'หน้านี้หมดอายุ';
$message   = $message ?? 'เซสชันหมดอายุ กรุณาลองใหม่อีกครั้ง';
require dirname(__DIR__) . '/includes/header.php';
?>
<?php /* ---------- ข้อความ ---------- */ ?>
<section class="card error-card">
    <span class="error-card__code">419</span>
    <h1>หน้านี้หมดอายุ</h1>
    <p class="muted"><?= Security::escape((string)$message) ?></p>

    <?php /* ---------- ปุ่ม ---------- */ ?>
    <div class="btn-row">
        <?php if (Security::currentUser() !== null): ?>
            <form method="post" action="<?= Security::escape($appUrl) ?>/logout">
                <input type="hidden" name="_csrf" value="<?= Security::escape(Security::csrfToken()) ?>">
                <button class="btn btn--secondary" type="submit">ออกจากระบบอีกครั้ง</button>
            </form>
        <?php endif; ?>
        <a class="btn btn--primary" href="<?= Security::escape($appUrl) ?>/">กลับหน้าหลัก</a>
    </div>
</section>
<?php require dirname(__DIR__) . '/includes/footer.php'; ?>
