<?php
/**
 * ============================================================
 *  หน้า        : ไม่พบหน้าที่ต้องการ (404)
 *  เรียกจาก    : core/Router.php เมื่อไม่มี route ที่ตรงกับ URL
 *  Layout      : เข้าสู่ระบบแล้ว = มีเมนูซ้าย · ยังไม่เข้าสู่ระบบ = กล่องกลางจอ (header.php เลือกให้)
 * ============================================================
 */
$pageTitle = $pageTitle ?? 'ไม่พบหน้าที่ต้องการ';
?>
<?php require dirname(__DIR__).'/includes/header.php'; ?>
<section class="card error-card">
    <div class="error-card__code">404</div>
    <h1>ไม่พบหน้าที่ต้องการ</h1>
    <p class="muted">ลิงก์อาจไม่ถูกต้อง หรือหน้านี้ถูกย้ายไปแล้ว</p>
    <a class="btn btn--primary" href="<?= Security::escape((string)env('APP_URL','')) ?>/"><?= ui_icon('back', 20) ?>กลับหน้าหลัก</a>
</section>
<?php require dirname(__DIR__).'/includes/footer.php'; ?>
