<?php
/**
 * ============================================================
 *  หน้า        : สร้างชุดสอบ
 *  Route       : GET /exams/create → ExamController@create  ·  POST /exams/create → ExamController@store
 *  สิทธิ์       : อาจารย์, แอดมิน
 *  แบบหน้าจอ   : prototype ExamCreate.html
 * ------------------------------------------------------------
 *  ข้อมูลที่ได้รับ
 *    $error  ข้อความเมื่อบันทึกไม่สำเร็จ · $old  ค่าที่กรอกไว้ (ExamController@store ส่งกลับมา)
 *
 *  บันทึกไม่ผ่าน → แสดงฟอร์มพร้อมข้อความและค่าที่กรอกไว้
 *  เมื่อ backend เพิ่ม GET /exams (รายการชุดสอบ · ข้อ 27) ให้เปลี่ยนลิงก์ "กลับ" และ "ยกเลิก" ไปที่ /exams
 * ============================================================
 */

$pageTitle = 'สร้างชุดสอบ';
// ---------- ข้อความไทยของประเภทและรูปแบบการสอบ (ชุดเดิมของ ExamController) ----------
$kindLabels = [
    'quiz'     => 'สอบย่อย (Quiz)',
    'midterm'  => 'สอบกลางภาค',
    'final'    => 'สอบปลายภาค',
    'pre_post' => 'สอบก่อนเรียน / หลังเรียน',
];

$modeLabels = [
    'fixed'     => 'กำหนดข้อสอบด้วยตนเอง',
    'blueprint' => 'สุ่มข้อสอบตามสัดส่วนที่กำหนด',
];

?>

<?php require dirname(__DIR__) . '/includes/header.php'; ?>
<?php /* โครงตาม prototype ExamCreate.html (แบ่งเป็นส่วน, ประเภท/รูปแบบเป็นตัวเลือกแบบปุ่ม) — ฟิลด์และชื่อ name เดิมทั้งหมด */ ?>

<?php /* ==================== หัวหน้า ==================== */ ?>
<div class="page-head">
    <div class="page-head__text">
        <p><a href="<?= Security::escape((string)env('APP_URL', '')) ?>/">← หน้าหลัก</a></p>
        <h1>สร้างชุดสอบ</h1>
        <p>กรอกข้อมูลชุดข้อสอบ ระบบจะบันทึกเป็นฉบับร่างก่อนเผยแพร่</p>
    </div>
</div>

<?php /* ==================== ข้อความเมื่อบันทึกไม่สำเร็จ ==================== */ ?>
<?php if (!empty($error)): ?>
    <div class="alert alert--danger" role="alert">
        <?= ui_icon('alert') ?>
        <div><b>บันทึกไม่สำเร็จ</b><?= Security::escape((string)$error) ?></div>
    </div>
<?php endif; ?>

<?php /* ==================== ฟอร์ม 3 ส่วน: ข้อมูลชุดข้อสอบ · วันและเวลาสอบ · การเข้าสอบ (ชื่อฟิลด์ตรงกับ ExamController@store) ==================== */ ?>
<form class="card" method="POST" action="<?= Security::escape((string)env('APP_URL', '')) ?>/exams/create">
    <input type="hidden" name="csrf_token" value="<?= Security::escape(Security::csrfToken()) ?>">

    <section class="form-section" aria-labelledby="sec-1">
        <div class="form-section__head"><span class="form-section__num">1</span><h3 id="sec-1">ข้อมูลชุดข้อสอบ</h3></div>
        <div class="field">
            <label class="field__label" for="title">ชื่อชุดข้อสอบ <span class="req">*</span></label>
            <input class="input" type="text" id="title" name="title" required value="<?= Security::escape((string)($old['title'] ?? '')) ?>" placeholder="เช่น สอบกลางภาค 88734465">
            <span class="field__hint">นิสิตจะเห็นชื่อนี้ในหน้าการสอบของฉัน</span>
        </div>
        <fieldset class="choices">
            <legend>ประเภทการสอบ</legend>
            <?php foreach ($kindLabels as $value => $label): ?>
                <label class="choice">
                    <input type="radio" name="kind" value="<?= $value ?>" <?= (($old['kind'] ?? 'quiz') === $value) ? 'checked' : '' ?>>
                    <span><?= Security::escape($label) ?></span>
                </label>
            <?php endforeach; ?>
        </fieldset>
        <fieldset class="choices">
            <legend>รูปแบบการเลือกข้อสอบ</legend>
            <?php foreach ($modeLabels as $value => $label): ?>
                <label class="choice">
                    <input type="radio" name="mode" value="<?= $value ?>" <?= (($old['mode'] ?? 'fixed') === $value) ? 'checked' : '' ?>>
                    <span><?= Security::escape($label) ?></span>
                </label>
            <?php endforeach; ?>
        </fieldset>
    </section>

    <section class="form-section" aria-labelledby="sec-2">
        <div class="form-section__head"><span class="form-section__num">2</span><h3 id="sec-2">วันและเวลาสอบ</h3><p>รูปแบบ 24 ชั่วโมง</p></div>
        <div class="form-grid">
            <div class="field">
                <label class="field__label" for="open_at">วัน-เวลาเปิดสอบ <span class="req">*</span></label>
                <input class="input datetime-24h" type="text" id="open_at" name="open_at" required
                    value="<?= Security::escape((string)($old['open_at'] ?? date('Y-m-d H:i'))) ?>">
                <span class="field__hint">เช่น 2026-12-15 09:00</span>
            </div>
            <div class="field">
                <label class="field__label" for="close_at">วัน-เวลาปิดสอบ <span class="req">*</span></label>
                <input class="input datetime-24h" type="text" id="close_at" name="close_at" required
                    value="<?= Security::escape((string)($old['close_at'] ?? date('Y-m-d H:i', strtotime('+7 days')))) ?>">
                <span class="field__hint">ต้องหลังวัน-เวลาเปิดสอบ</span>
            </div>
            <div class="field">
                <label class="field__label" for="duration_min">เวลาทำข้อสอบ (นาที) <span class="req">*</span></label>
                <input class="input" type="number" id="duration_min" name="duration_min" min="1" required value="<?= Security::escape((string)($old['duration_min'] ?? '60')) ?>">
                <span class="field__hint">นับจากที่นิสิตกดเริ่มทำข้อสอบ</span>
            </div>
        </div>
    </section>

    <section class="form-section" aria-labelledby="sec-3">
        <div class="form-section__head"><span class="form-section__num">3</span><h3 id="sec-3">การเข้าสอบ</h3></div>
        <div class="form-grid">
            <div class="field">
                <label class="field__label" for="max_attempts">จำนวนครั้งที่สอบได้สูงสุด <span class="req">*</span></label>
                <input class="input" type="number" id="max_attempts" name="max_attempts" min="1" required value="<?= Security::escape((string)($old['max_attempts'] ?? '1')) ?>">
            </div>
        </div>
    </section>

    <div class="btn-row btn-row--end">
        <a class="btn btn--ghost btn--lg" href="<?= Security::escape((string)env('APP_URL', '')) ?>/">ยกเลิก</a>
        <button class="btn btn--primary btn--lg" type="submit">บันทึกชุดข้อสอบ</button>
    </div>
</form>

<?php require dirname(__DIR__) . '/includes/footer.php'; ?>
