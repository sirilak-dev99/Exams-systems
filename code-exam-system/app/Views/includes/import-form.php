<?php /* ---------- เส้นทาง ---------- */ ?>
<nav class="trail" aria-label="ตำแหน่งของหน้า">
    <a class="trail__back" href="<?= Security::escape($appUrl) ?>/import">← กลับ</a>
    <a href="<?= Security::escape($appUrl) ?>/import">นำเข้าข้อมูล</a>
    <span aria-hidden="true">›</span>
    <b aria-current="page"><?= Security::escape($pageTitle) ?></b>
</nav>

<?php /* ---------- หัวข้อ ---------- */ ?>
<div class="page-title">
    <h1><?= Security::escape($pageTitle) ?></h1>
    <p><?= Security::escape($importIntro) ?></p>
</div>

<?php /* ---------- อัปโหลด ---------- */ ?>
<section class="card">
    <div class="card__head"><h2>1. เลือกไฟล์</h2></div>
    <?php $pendingApi = $importPending; require __DIR__ . '/api-pending.php'; ?>
    <label class="dropzone" aria-disabled="true">
        <?= ui_icon('file-plus', 36) ?>
        <b>ลากไฟล์มาวาง หรือคลิกเพื่อเลือกไฟล์</b>
        <span>รองรับ .xlsx และ .csv</span>
        <input type="file" accept=".xlsx,.csv" disabled>
    </label>
</section>

<?php /* ---------- รูปแบบไฟล์ ---------- */ ?>
<section class="card card--flush">
    <div class="card__head"><h2>2. รูปแบบคอลัมน์ในไฟล์</h2><p>แถวแรกเป็นหัวคอลัมน์ตามชื่อด้านล่าง</p></div>
    <div class="table-wrap">
        <table class="table">
            <thead>
                <tr>
                    <th scope="col">คอลัมน์</th>
                    <th scope="col">ความหมาย</th>
                    <th scope="col">จำเป็น</th>
                    <th scope="col">ตัวอย่าง</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($importColumns as [$col, $label, $required, $example]): ?>
                    <tr>
                        <td class="code nowrap"><?= Security::escape($col) ?></td>
                        <td><?= Security::escape($label) ?></td>
                        <td><?= $required ? '<span class="badge badge--active">จำเป็น</span>' : '<span class="muted">ไม่บังคับ</span>' ?></td>
                        <td class="nowrap"><?= Security::escape($example) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>
