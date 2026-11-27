<?php
// ---------- ค่าพื้นฐาน ----------
$crumbTitle  = 'คลังข้อสอบ';
$pageScripts = ['/js/pages/questions.js'];
require dirname(__DIR__) . '/includes/header.php';

// ---------- ข้อความ ----------
$typeLabels = [
    'multiple_choice' => 'ปรนัย',
    'true_false'      => 'ถูก/ผิด',
    'short_answer'    => 'ตอบสั้น',
    'essay'           => 'อัตนัย',
    'matching'        => 'จับคู่',
];
$bloomLevels = [
    'remember'   => 'จำ',
    'understand' => 'เข้าใจ',
    'apply'      => 'นำไปใช้',
    'analyze'    => 'วิเคราะห์',
    'evaluate'   => 'ประเมินค่า',
    'create'     => 'สร้างสรรค์',
];
$statusLabels = ['draft' => 'ฉบับร่าง', 'published' => 'เผยแพร่', 'archived' => 'เก็บถาวร'];
$filters = [
    ['qbCourse',  'school', 'รายวิชา',   []],
    ['qbChapter', 'book',   'บท',        []],
    ['qbType',    'list',   'ประเภท',    $typeLabels],
    ['qbBloom',   'chart',  'ระดับ Bloom', $bloomLevels],
    ['qbStatus',  'tag',    'สถานะ',     $statusLabels],
];
$stats = [
    ['all',       'bank',    'ข้อสอบทั้งหมด', ''],
    ['draft',     'pencil',  'ฉบับร่าง',      ''],
    ['published', 'check',   'เผยแพร่',       'qb-stat--ok'],
    ['archived',  'archive', 'เก็บถาวร',      ''],
];
?>
<?php /* ---------- แบนเนอร์ ---------- */ ?>
<section class="greet hub-hero" aria-label="คลังข้อสอบ">
    <svg class="hub-hero__deco" aria-hidden="true" width="240" height="150" viewBox="180 20 280 160"><path d="M418 34h10v12h12v10h-12v12h-10V56h-12V46h12z" fill="#CDB9F0"/><path d="M120 40h7v9h9v7h-9v9h-7v-9h-9v-7h9z" fill="#D8C8F4"/></svg>
    <span class="dash-hero__icon"><?= ui_icon('bank', 64) ?></span>
    <div class="greet__text">
        <h1>คลังข้อสอบ</h1>
        <p>จัดเก็บ ค้นหา และจัดการข้อสอบแยกตามรายวิชาและบท</p>
    </div>
    <div class="hub-hero__actions">
        <a class="btn btn--primary" href="<?= Security::escape($appUrl) ?>/questions/create">+ เพิ่มข้อสอบ</a>
        <button class="btn btn--secondary" type="button" disabled title="รอ API นำเข้าข้อสอบ"><?= ui_icon('upload', 20) ?> นำเข้าข้อสอบ <small>เร็วๆ นี้</small></button>
    </div>
</section>

<?php /* ---------- ตัวกรอง ---------- */ ?>
<section class="card qb-filters" aria-label="ตัวกรองข้อสอบ">
    <?php foreach ($filters as [$id, $icon, $label, $options]): ?>
        <label class="qb-filter" for="<?= $id ?>">
            <span class="qb-filter__icon" aria-hidden="true"><?= ui_icon($icon, 22) ?></span>
            <span class="qb-filter__text">
                <b><?= Security::escape($label) ?></b>
                <select id="<?= $id ?>"<?= $id === 'qbChapter' ? ' disabled' : '' ?>>
                    <option value="">ทั้งหมด</option>
                    <?php foreach ($options as $value => $text): ?>
                        <option value="<?= Security::escape($value) ?>"><?= Security::escape($text) ?></option>
                    <?php endforeach; ?>
                </select>
            </span>
        </label>
    <?php endforeach; ?>
    <label class="qb-search">
        <?= ui_icon('search', 20) ?>
        <span class="sr-only">ค้นหาข้อสอบ</span>
        <input type="search" id="qbSearch" placeholder="ค้นหาด้วยข้อความหรือรหัส" autocomplete="off">
    </label>
</section>

<?php /* ---------- สรุป ---------- */ ?>
<section class="card qb-stats" aria-label="สรุปจำนวนข้อสอบ">
    <?php foreach ($stats as [$key, $icon, $label, $mod]): ?>
        <div class="qb-stat <?= $mod ?>">
            <span class="qb-stat__icon" aria-hidden="true"><?= ui_icon($icon, 24) ?></span>
            <span><small><?= Security::escape($label) ?></small><b><span data-stat="<?= $key ?>">—</span> ข้อ</b></span>
        </div>
    <?php endforeach; ?>
</section>

<?php /* ---------- รายการ ---------- */ ?>
<section class="card card--flush">
    <div class="table-wrap">
        <table class="table qb-table">
            <thead>
                <tr>
                    <th scope="col" class="qb-table__check"><input type="checkbox" id="qbAll" aria-label="เลือกทั้งหมดในหน้านี้"></th>
                    <th scope="col">รหัสข้อสอบ</th>
                    <th scope="col">ตัวอย่างข้อความโจทย์</th>
                    <th scope="col">รายวิชา / บท</th>
                    <th scope="col">ประเภท</th>
                    <th scope="col">ระดับ Bloom</th>
                    <th scope="col" class="num">คะแนน</th>
                    <th scope="col">สถานะ</th>
                    <th scope="col" class="center">จัดการ</th>
                </tr>
            </thead>
            <tbody id="qbRows">
                <tr><td colspan="9"><div class="empty"><b>กำลังโหลด…</b></div></td></tr>
            </tbody>
        </table>
    </div>
    <div class="table-foot" id="qbFoot" aria-live="polite"></div>
</section>

<?php /* ---------- ดูข้อสอบ ---------- */ ?>
<div class="modal" id="qbModal" role="dialog" aria-modal="true" aria-labelledby="qbModalTitle">
    <div class="modal__box sheet">
        <div class="sheet__head">
            <span class="sheet__icon" aria-hidden="true"><?= ui_icon('bank', 28) ?></span>
            <div class="grow">
                <h2 id="qbModalTitle">ข้อสอบ</h2>
                <p id="qbModalSub"></p>
            </div>
            <button class="modal__close" type="button" data-modal-close aria-label="ปิด">✕</button>
        </div>
        <div class="sheet__body" id="qbModalBody" aria-live="polite"></div>
        <div class="sheet__foot">
            <span class="sheet__req"></span>
            <button class="btn btn--ghost" type="button" data-modal-close>ปิด</button>
        </div>
    </div>
</div>
<?php require dirname(__DIR__) . '/includes/footer.php'; ?>
