<?php
// ---------- ค่าพื้นฐาน ----------
$exams       = $exams ?? [];
$crumbTitle  = 'จัดการชุดสอบ';
$pageScripts = ['/js/pages/exams.js'];
require dirname(__DIR__) . '/includes/header.php';

// ---------- ข้อความ ----------
$kindLabels = [
    'quiz'     => 'สอบย่อย (Quiz)',
    'midterm'  => 'สอบกลางภาค',
    'final'    => 'สอบปลายภาค',
    'pre_post' => 'สอบก่อน / หลังเรียน',
];
$modeLabels = [
    'fixed'     => ['กำหนดข้อสอบเอง', 'เลือกข้อสอบจากคลังทีละข้อ'],
    'blueprint' => ['สุ่มตามสัดส่วน', 'สุ่มจากคลังตาม Blueprint ที่กำหนด'],
];
$statusLabels = [
    'draft'     => 'ฉบับร่าง',
    'published' => 'เผยแพร่แล้ว',
    'closed'    => 'ปิดรับการเข้าสอบ',
    'archived'  => 'จัดเก็บแล้ว',
];
$showAnswerLabels = [
    'never'          => 'ไม่แสดงเฉลย',
    'after_submit'   => 'หลังส่งข้อสอบ',
    'after_close'    => 'หลังปิดการสอบ',
    'after_announce' => 'หลังประกาศผล',
];
$openForm    = str_ends_with((string)parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH), '/exams/create');
$thaiDateTime = static function (?string $value): string {
    if (!$value) {
        return '—';
    }
    $ts = strtotime($value);
    return $ts ? date('j/n/', $ts) . ((int)date('Y', $ts) + 543) . ' ' . date('H:i', $ts) : $value;
};
?>
<?php /* ---------- หัวข้อ ---------- */ ?>
<div class="page-head">
    <div class="page-title">
        <h1>จัดการชุดสอบ</h1>
        <p>สร้างชุดสอบ กำหนดเวลา และเลือกกลุ่มเรียนที่เข้าสอบ</p>
    </div>
    <button class="btn btn--primary" type="button" id="examNewBtn" data-open="<?= $openForm ? '1' : '0' ?>">+ สร้างชุดสอบ</button>
</div>

<?php /* ---------- รายการ ---------- */ ?>
<section class="card card--flush">
    <?php if (!$exams): ?>
        <div class="empty"><?= ui_icon('clip', 40) ?><b>ยังไม่มีชุดสอบ</b><span>กด "สร้างชุดสอบ" เพื่อเริ่มต้น</span></div>
    <?php else: ?>
        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr>
                        <th scope="col">ชุดสอบ</th>
                        <th scope="col">รายวิชา</th>
                        <th scope="col">ประเภท</th>
                        <th scope="col">ช่วงเวลาสอบ</th>
                        <th scope="col" class="num">เวลา</th>
                        <th scope="col" class="num">ข้อ / คะแนน</th>
                        <th scope="col">สถานะ</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($exams as $e): ?>
                        <tr>
                            <td class="exam-title">
                                <b><?= Security::escape((string)$e['title']) ?></b>
                                <small class="muted block"><?= Security::escape($modeLabels[$e['mode']][0] ?? (string)$e['mode']) ?></small>
                            </td>
                            <td class="nowrap" data-offering="<?= (int)$e['course_offerings_id'] ?>">—</td>
                            <td class="nowrap"><?= Security::escape($kindLabels[$e['kind']] ?? (string)$e['kind']) ?></td>
                            <td class="nowrap"><?= Security::escape($thaiDateTime($e['open_at'])) ?><br><span class="muted">ถึง <?= Security::escape($thaiDateTime($e['close_at'])) ?></span></td>
                            <td class="num nowrap"><?= (int)$e['duration_min'] ?> นาที</td>
                            <td class="num nowrap"><?= (int)$e['total_questions'] ?> / <?= Security::escape(rtrim(rtrim(number_format((float)$e['total_score'], 2, '.', ''), '0'), '.')) ?></td>
                            <td><span class="badge badge--<?= Security::escape((string)$e['status']) ?>"><?= Security::escape($statusLabels[$e['status']] ?? (string)$e['status']) ?></span></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <div class="table-foot">ทั้งหมด <?= count($exams) ?> ชุด</div>
    <?php endif; ?>
</section>

<?php /* ---------- ฟอร์มสร้างชุดสอบ ---------- */ ?>
<div class="modal" id="examModal" role="dialog" aria-modal="true" aria-labelledby="examModalTitle" aria-describedby="examModalSub">
    <form class="modal__box sheet" id="examForm" novalidate>
        <div class="sheet__head">
            <span class="sheet__icon" aria-hidden="true"><?= ui_icon('clip', 28) ?></span>
            <div class="grow">
                <h2 id="examModalTitle">สร้างชุดสอบ</h2>
                <p id="examModalSub">บันทึกเป็นฉบับร่างก่อน แล้วค่อยเพิ่มข้อสอบและเผยแพร่ภายหลัง</p>
            </div>
            <button class="modal__close" type="button" data-modal-close aria-label="ปิด">✕</button>
        </div>

        <div class="sheet__body">
            <div class="alert alert--danger" id="examFormError" role="alert" hidden><b></b></div>

            <?php /* ---------- 1. รายวิชา ---------- */ ?>
            <section class="sheet__section" aria-labelledby="eSecCourse">
                <div class="sheet__section-head">
                    <span class="form-section__num" aria-hidden="true">1</span>
                    <h3 id="eSecCourse">รายวิชาและกลุ่มเรียน</h3>
                </div>
                <div class="field">
                    <label class="field__label" for="eOffering">รายวิชาที่เปิดสอน<span class="req">*</span></label>
                    <select class="select" id="eOffering" required aria-describedby="eOfferingError"><option value="">กำลังโหลด…</option></select>
                    <span class="field__error" id="eOfferingError" hidden></span>
                </div>
                <fieldset class="field" id="eSections" hidden>
                    <legend class="field__label">กลุ่มเรียนที่เข้าสอบ</legend>
                    <div class="choices" id="eSectionList"></div>
                    <span class="field__hint">ไม่เลือกก็ได้ เพิ่มกลุ่มเรียนภายหลังได้</span>
                </fieldset>
            </section>

            <?php /* ---------- 2. ข้อมูลชุดสอบ ---------- */ ?>
            <section class="sheet__section" aria-labelledby="eSecInfo">
                <div class="sheet__section-head">
                    <span class="form-section__num" aria-hidden="true">2</span>
                    <h3 id="eSecInfo">ข้อมูลชุดสอบ</h3>
                </div>
                <div class="field">
                    <label class="field__label" for="eTitle">ชื่อชุดสอบ<span class="req">*</span></label>
                    <input class="input" id="eTitle" maxlength="150" required autocomplete="off" placeholder="เช่น สอบกลางภาค การพยาบาลพื้นฐาน" aria-describedby="eTitleError">
                    <span class="field__error" id="eTitleError" hidden></span>
                </div>
                <fieldset class="choices">
                    <legend>ประเภทการสอบ</legend>
                    <?php foreach ($kindLabels as $value => $label): ?>
                        <label class="choice"><input type="radio" name="kind" value="<?= Security::escape($value) ?>"<?= $value === 'quiz' ? ' checked' : '' ?>><span><?= Security::escape($label) ?></span></label>
                    <?php endforeach; ?>
                </fieldset>
                <fieldset class="picks">
                    <legend class="sr-only">รูปแบบการเลือกข้อสอบ</legend>
                    <?php foreach ($modeLabels as $value => [$label, $desc]): ?>
                        <label class="pick">
                            <input type="radio" name="mode" value="<?= Security::escape($value) ?>"<?= $value === 'fixed' ? ' checked' : '' ?>>
                            <span class="pick__icon" aria-hidden="true"><?= ui_icon($value === 'fixed' ? 'clip' : 'layers', 24) ?></span>
                            <span class="pick__body"><b><?= Security::escape($label) ?></b><small><?= Security::escape($desc) ?></small></span>
                        </label>
                    <?php endforeach; ?>
                </fieldset>
            </section>

            <?php /* ---------- 3. เวลาและการเข้าสอบ ---------- */ ?>
            <section class="sheet__section" aria-labelledby="eSecTime">
                <div class="sheet__section-head">
                    <span class="form-section__num" aria-hidden="true">3</span>
                    <h3 id="eSecTime">เวลาและการเข้าสอบ</h3>
                </div>
                <div class="form-grid">
                    <div class="field">
                        <label class="field__label" for="eOpen">เปิดให้เข้าสอบ<span class="req">*</span></label>
                        <input class="input" id="eOpen" type="datetime-local" required aria-describedby="eOpenError">
                        <span class="field__error" id="eOpenError" hidden></span>
                    </div>
                    <div class="field">
                        <label class="field__label" for="eClose">ปิดการสอบ<span class="req">*</span></label>
                        <input class="input" id="eClose" type="datetime-local" required aria-describedby="eCloseError">
                        <span class="field__error" id="eCloseError" hidden></span>
                    </div>
                </div>
                <div class="sheet__grid">
                    <div class="field">
                        <label class="field__label" for="eDuration">เวลาทำข้อสอบ (นาที)<span class="req">*</span></label>
                        <input class="input" id="eDuration" type="number" min="1" step="1" value="60" required aria-describedby="eDurationError">
                        <span class="field__error" id="eDurationError" hidden></span>
                    </div>
                    <div class="field">
                        <label class="field__label" for="eAttempts">จำนวนครั้งที่สอบได้<span class="req">*</span></label>
                        <input class="input" id="eAttempts" type="number" min="1" step="1" value="1" required aria-describedby="eAttemptsError">
                        <span class="field__error" id="eAttemptsError" hidden></span>
                    </div>
                    <div class="field">
                        <label class="field__label" for="eGrace">เวลาผ่อนผัน (นาที)</label>
                        <input class="input" id="eGrace" type="number" min="0" step="1" value="5">
                    </div>
                </div>
                <div class="form-grid">
                    <div class="field">
                        <label class="field__label" for="eShowAnswer">การแสดงเฉลย</label>
                        <select class="select" id="eShowAnswer">
                            <?php foreach ($showAnswerLabels as $value => $label): ?>
                                <option value="<?= Security::escape($value) ?>"<?= $value === 'after_announce' ? ' selected' : '' ?>><?= Security::escape($label) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="field">
                        <label class="field__label" for="eStatus">สถานะเมื่อบันทึก</label>
                        <select class="select" id="eStatus">
                            <option value="draft" selected>ฉบับร่าง</option>
                            <option value="published">เผยแพร่ทันที</option>
                        </select>
                    </div>
                </div>
                <div class="checks">
                    <label class="check"><input type="checkbox" id="eShuffle"><span>สลับลำดับข้อสอบ<small>นิสิตแต่ละคนเห็นข้อสอบไม่เรียงกัน</small></span></label>
                    <label class="check"><input type="checkbox" id="eCode"><span>ต้องใช้รหัสเข้าสอบ<small>ผู้คุมสอบออกรหัส 5 ตัวอักษรให้ก่อนเริ่มสอบ</small></span></label>
                    <div class="field field--sm" id="eTtlField" hidden>
                        <label class="field__label" for="eTtl">รหัสใช้ได้นาน (นาที)</label>
                        <input class="input" id="eTtl" type="number" min="1" step="1" value="10">
                    </div>
                    <label class="check" id="eResumeField" hidden><input type="checkbox" id="eResume" checked><span>ขอรหัสอีกครั้งเมื่อกลับเข้าสอบต่อ</span></label>
                    <label class="check"><input type="checkbox" id="eLocation"><span>ขอความยินยอมเก็บตำแหน่ง<small>ใช้ตรวจสอบว่าสอบในสถานที่ที่กำหนด</small></span></label>
                </div>
            </section>
        </div>

        <div class="sheet__foot">
            <span class="sheet__req"><span class="req">*</span> จำเป็นต้องกรอก</span>
            <button class="btn btn--ghost" type="button" data-modal-close>ยกเลิก</button>
            <button class="btn btn--primary" type="submit">บันทึกชุดสอบ</button>
        </div>
    </form>
</div>
<?php require dirname(__DIR__) . '/includes/footer.php'; ?>
