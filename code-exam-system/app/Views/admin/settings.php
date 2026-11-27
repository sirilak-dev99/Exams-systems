<?php
// ---------- ค่าพื้นฐาน ----------
$crumbTitle = 'ตั้งค่าระบบ';
require dirname(__DIR__) . '/includes/header.php';

// ---------- ค่าที่ระบบใช้อยู่ ----------
$idleMinutes = intdiv(Security::IDLE_TIMEOUT_SECONDS, 60);
$bloomLevels = ['remember' => 'จำ', 'understand' => 'เข้าใจ', 'apply' => 'นำไปใช้', 'analyze' => 'วิเคราะห์', 'evaluate' => 'ประเมินค่า', 'create' => 'สร้างสรรค์'];
?>
<?php /* ---------- หัวข้อ ---------- */ ?>
<div class="page-title">
    <h1><?= Security::escape($pageTitle) ?></h1>
    <p>ค่าการทำงานที่ระบบใช้อยู่ในขณะนี้</p>
</div>

<?php $pendingApi = 'การแก้ไขค่าเหล่านี้ต้องมี endpoint ตั้งค่าระบบ (สิทธิ์ system.settings) ตอนนี้แสดงเพื่ออ่านอย่างเดียว'; require dirname(__DIR__) . '/includes/api-pending.php'; ?>

<?php /* ---------- ระบบ ---------- */ ?>
<section class="card">
    <div class="card__head"><h2>ระบบ</h2></div>
    <dl class="info-list">
        <dt>ชื่อระบบ</dt>
        <dd><?= Security::escape((string)env('APP_NAME', 'Exam System')) ?></dd>
        <dt>ที่อยู่ระบบ</dt>
        <dd class="code"><?= Security::escape($appUrl) ?></dd>
        <dt>เขตเวลา</dt>
        <dd><?= Security::escape(date_default_timezone_get()) ?></dd>
        <dt>ออกจากระบบอัตโนมัติ</dt>
        <dd>เมื่อไม่มีการใช้งาน <?= $idleMinutes ?> นาที</dd>
    </dl>
</section>

<?php /* ---------- ระดับ Bloom ---------- */ ?>
<section class="card card--flush">
    <div class="card__head"><h2>ระดับการเรียนรู้ (Bloom)</h2><p>ใช้จัดหมวดข้อสอบและวิเคราะห์ผลสอบ</p></div>
    <div class="table-wrap">
        <table class="table">
            <thead>
                <tr>
                    <th scope="col" class="num">ลำดับ</th>
                    <th scope="col">ระดับ</th>
                    <th scope="col">รหัส</th>
                </tr>
            </thead>
            <tbody>
                <?php $i = 0; foreach ($bloomLevels as $code => $name): ?>
                    <tr>
                        <td class="num"><?= ++$i ?></td>
                        <td><?= Security::escape($name) ?></td>
                        <td class="code"><?= Security::escape($code) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>
<?php require dirname(__DIR__) . '/includes/footer.php'; ?>
