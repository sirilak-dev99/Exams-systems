<?php
// ---------- ค่าพื้นฐาน ----------
$crumbTitle  = 'คลังข้อสอบ';
$pageScripts = ['/js/pages/question-create.js'];
require dirname(__DIR__) . '/includes/header.php';

// ---------- ข้อความ ----------
$typeLabels = [
    'multiple_choice' => ['ปรนัยหลายตัวเลือก', 'เลือกคำตอบที่ถูกจากตัวเลือก'],
    'true_false'      => ['ถูก / ผิด', 'ตอบว่าข้อความถูกหรือผิด'],
    'matching'        => ['จับคู่', 'จับคู่ข้อความให้ตรงกัน'],
    'short_answer'    => ['เติมคำตอบสั้น', 'พิมพ์คำตอบสั้น ๆ'],
    'essay'           => ['อัตนัย', 'เขียนตอบ อาจารย์ให้คะแนนเอง'],
];
$bloomLevels = [
    'remember'   => 'จำ',
    'understand' => 'เข้าใจ',
    'apply'      => 'นำไปใช้',
    'analyze'    => 'วิเคราะห์',
    'evaluate'   => 'ประเมินค่า',
    'create'     => 'สร้างสรรค์',
];
?>
<?php /* ---------- เส้นทาง ---------- */ ?>
<nav class="trail" aria-label="ตำแหน่งของหน้า">
    <a class="trail__back" href="<?= Security::escape($appUrl) ?>/questions">← กลับ</a>
    <a href="<?= Security::escape($appUrl) ?>/questions">คลังข้อสอบ</a>
    <span aria-hidden="true">›</span>
    <b aria-current="page">สร้างคำถามใหม่</b>
</nav>

<?php /* ---------- หัวข้อ ---------- */ ?>
<div class="page-title">
    <h1><?= Security::escape($pageTitle) ?></h1>
    <p>เขียนโจทย์ ตัวเลือก และเฉลย ระบบเก็บเป็นเวอร์ชันแรกของคำถาม</p>
</div>

<form class="question-form" id="questionForm" novalidate>
    <div class="alert alert--danger" id="questionFormError" role="alert" hidden><b></b></div>

    <?php /* ---------- 1. รายวิชาและหมวด ---------- */ ?>
    <section class="card">
        <div class="sheet__section-head">
            <span class="form-section__num" aria-hidden="true">1</span>
            <h2 class="h3">รายวิชาและระดับการเรียนรู้</h2>
        </div>
        <div class="form-grid">
            <div class="field">
                <label class="field__label" for="qCourse">รายวิชา<span class="req">*</span></label>
                <select class="select" id="qCourse" required aria-describedby="qCourseError"><option value="">กำลังโหลด…</option></select>
                <span class="field__error" id="qCourseError" hidden></span>
            </div>
            <div class="field">
                <label class="field__label" for="qChapter">บทเรียน</label>
                <select class="select" id="qChapter" disabled><option value="">เลือกรายวิชาก่อน</option></select>
            </div>
        </div>
        <fieldset class="choices" id="qBloom" aria-describedby="qBloomError">
            <legend>ระดับ Bloom<span class="req">*</span></legend>
            <?php foreach ($bloomLevels as $code => $label): ?>
                <label class="choice"><input type="radio" name="bloom" value="<?= Security::escape($code) ?>"><span><?= Security::escape($label) ?></span></label>
            <?php endforeach; ?>
        </fieldset>
        <span class="field__error" id="qBloomError" hidden></span>
    </section>

    <?php /* ---------- 2. รูปแบบและโจทย์ ---------- */ ?>
    <section class="card">
        <div class="sheet__section-head">
            <span class="form-section__num" aria-hidden="true">2</span>
            <h2 class="h3">รูปแบบและโจทย์</h2>
        </div>
        <fieldset class="picks picks--5">
            <legend class="sr-only">รูปแบบคำถาม</legend>
            <?php foreach ($typeLabels as $value => [$label, $desc]): ?>
                <label class="pick">
                    <input type="radio" name="type" value="<?= Security::escape($value) ?>"<?= $value === 'multiple_choice' ? ' checked' : '' ?>>
                    <span class="pick__body"><b><?= Security::escape($label) ?></b><small><?= Security::escape($desc) ?></small></span>
                </label>
            <?php endforeach; ?>
        </fieldset>
        <div class="field">
            <label class="field__label" for="qText">โจทย์<span class="req">*</span></label>
            <textarea class="textarea textarea--lg" id="qText" required placeholder="พิมพ์คำถาม" aria-describedby="qTextError"></textarea>
            <span class="field__error" id="qTextError" hidden></span>
        </div>
    </section>

    <?php /* ---------- 3. ตัวเลือกและเฉลย ---------- */ ?>
    <section class="card" id="qOptionsCard">
        <div class="sheet__section-head">
            <span class="form-section__num" aria-hidden="true">3</span>
            <h2 class="h3">ตัวเลือกและเฉลย</h2>
            <p class="muted small grow" id="qOptionsHint">ทำเครื่องหมายที่คำตอบที่ถูก</p>
        </div>
        <div class="alert alert--danger" id="qOptionsError" role="alert" hidden><b></b></div>
        <ol class="option-list" id="qOptions"></ol>
        <div class="btn-row">
            <button class="btn btn--secondary btn--sm" type="button" id="qOptionAdd">+ เพิ่มตัวเลือก</button>
            <div class="field field--inline" id="qScoringField">
                <label class="field__label" for="qScoring">การให้คะแนน</label>
                <select class="select" id="qScoring">
                    <option value="all_or_nothing">ได้คะแนนเมื่อตอบถูกครบทุกข้อ</option>
                    <option value="partial_credit">ให้คะแนนตามสัดส่วนที่ตอบถูก</option>
                </select>
            </div>
        </div>
    </section>

    <section class="card" id="qManualCard" hidden>
        <div class="sheet__section-head">
            <span class="form-section__num" aria-hidden="true">3</span>
            <h2 class="h3">การให้คะแนน</h2>
        </div>
        <div class="field field--sm">
            <label class="field__label" for="qMaxScore">คะแนนเต็ม</label>
            <input class="input" id="qMaxScore" type="number" min="0" step="0.5" placeholder="เช่น 5" aria-describedby="qMaxScoreError">
            <span class="field__error" id="qMaxScoreError" hidden></span>
        </div>
    </section>

    <?php /* ---------- 4. คำอธิบายและการเผยแพร่ ---------- */ ?>
    <section class="card">
        <div class="sheet__section-head">
            <span class="form-section__num" aria-hidden="true">4</span>
            <h2 class="h3">คำอธิบายและการเผยแพร่</h2>
        </div>
        <div class="field">
            <label class="field__label" for="qExplain">คำอธิบายเฉลย</label>
            <textarea class="textarea" id="qExplain" placeholder="อธิบายเหตุผลของคำตอบที่ถูก"></textarea>
        </div>
        <div class="checks">
            <label class="check" id="qShuffleField"><input type="checkbox" id="qShuffle" checked><span>สลับลำดับตัวเลือก<small>นิสิตแต่ละคนเห็นตัวเลือกไม่เรียงกัน</small></span></label>
            <label class="check"><input type="checkbox" id="qVisible" checked><span>ให้นิสิตเห็นเฉลยและคำอธิบาย<small>เมื่อชุดสอบเปิดให้ดูเฉลย</small></span></label>
        </div>
        <fieldset class="choices">
            <legend>สถานะ</legend>
            <label class="choice"><input type="radio" name="status" value="draft" checked><span>ฉบับร่าง</span></label>
            <label class="choice"><input type="radio" name="status" value="published"><span>พร้อมใช้ในชุดสอบ</span></label>
        </fieldset>
    </section>

    <?php /* ---------- ปุ่ม ---------- */ ?>
    <div class="form-actions">
        <a class="btn btn--ghost" href="<?= Security::escape($appUrl) ?>/questions">ยกเลิก</a>
        <button class="btn btn--primary" type="submit">บันทึกคำถาม</button>
    </div>
</form>
<?php require dirname(__DIR__) . '/includes/footer.php'; ?>
