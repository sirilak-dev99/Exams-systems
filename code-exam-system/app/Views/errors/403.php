<?php
/**
 * ============================================================
 *  หน้า        : ไม่มีสิทธิ์เข้าถึง (403)
 *  เรียกจาก    : core/Security.php — requireRole() / requirePermission() เมื่อเปิดผ่านเบราว์เซอร์
 *                (รอจอยเปลี่ยนจาก echo ข้อความ มาเป็น require ไฟล์นี้ · docs/api-check.md)
 *                คำขอ JSON ยังตอบ { error: { code: 'FORBIDDEN' } } เหมือนเดิม
 *  Layout      : เข้าสู่ระบบแล้ว = มีเมนูซ้าย · ยังไม่เข้าสู่ระบบ = กล่องกลางจอ + ปุ่มเข้าสู่ระบบ
 * ------------------------------------------------------------
 *  ข้อมูลที่ได้รับ: $message ข้อความเพิ่มเติม (ไม่บังคับ)
 * ============================================================
 */
$pageTitle = $pageTitle ?? 'ไม่มีสิทธิ์เข้าถึง';
require dirname(__DIR__) . '/includes/header.php';
?>
<section class="card error-card">
    <div class="error-card__code">403</div>
    <h1>ไม่มีสิทธิ์เข้าถึงหน้านี้</h1>
    <p class="muted"><?= Security::escape((string)($message ?? 'บัญชีของคุณไม่ได้รับอนุญาตให้ใช้งานส่วนนี้ หากคิดว่าเป็นข้อผิดพลาด กรุณาติดต่อผู้ดูแลระบบ')) ?></p>
    <div class="btn-row">
        <a class="btn btn--primary" href="<?= Security::escape((string)env('APP_URL', '')) ?>/"><?= ui_icon('back', 20) ?>กลับหน้าหลัก</a>
        <?php if (Security::currentUser() === null): ?>
            <a class="btn btn--secondary" href="<?= Security::escape((string)env('APP_URL', '')) ?>/login">เข้าสู่ระบบ</a>
        <?php endif; ?>
    </div>
</section>
<?php require dirname(__DIR__) . '/includes/footer.php'; ?>
