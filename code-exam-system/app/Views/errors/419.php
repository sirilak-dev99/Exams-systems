<?php
/**
 * ============================================================
 *  หน้า        : เซสชันฟอร์มหมดอายุ (419)
 *  เรียกจาก    : AuthController@logout เมื่อ CSRF token ไม่ผ่าน (เช่น เปิดหน้าค้างไว้นานแล้วกดออกจากระบบ)
 *  Layout      : ยังเข้าสู่ระบบอยู่ = มีเมนูซ้าย · ไม่ได้เข้าสู่ระบบ = กล่องกลางจอ
 * ------------------------------------------------------------
 *  ข้อมูลที่ได้รับ: $message ข้อความจาก Controller (ไม่บังคับ)
 *  ปุ่ม "ออกจากระบบอีกครั้ง" ส่งฟอร์มพร้อม CSRF token ใหม่ ผู้ใช้จึงออกได้ทันทีโดยไม่ต้องรีเฟรช
 * ============================================================
 */
$pageTitle = $pageTitle ?? 'เซสชันหมดอายุ';
require dirname(__DIR__) . '/includes/header.php';
$appUrl = rtrim((string)env('APP_URL', ''), '/');
?>
<section class="card error-card">
    <div class="error-card__code">419</div>
    <h1>เซสชันหมดอายุ</h1>
    <p class="muted"><?= Security::escape((string)($message ?? 'หน้านี้เปิดค้างไว้นานเกินไป กรุณาลองใหม่อีกครั้ง')) ?></p>
    <div class="btn-row">
        <?php if (Security::currentUser() !== null): ?>
            <?php /* ---------- ยังเข้าสู่ระบบอยู่: ให้ออกจากระบบอีกครั้งด้วย token ใหม่ ---------- */ ?>
            <form method="post" action="<?= Security::escape($appUrl) ?>/logout">
                <input type="hidden" name="_csrf" value="<?= Security::escape(Security::csrfToken()) ?>">
                <button class="btn btn--primary" type="submit"><?= ui_icon('logout', 20) ?>ออกจากระบบอีกครั้ง</button>
            </form>
            <a class="btn btn--secondary" href="<?= Security::escape($appUrl) ?>/"><?= ui_icon('back', 20) ?>กลับหน้าหลัก</a>
        <?php else: ?>
            <a class="btn btn--primary" href="<?= Security::escape($appUrl) ?>/login">ไปหน้าเข้าสู่ระบบ</a>
        <?php endif; ?>
    </div>
</section>
<?php require dirname(__DIR__) . '/includes/footer.php'; ?>
