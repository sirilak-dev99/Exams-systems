<?php
/**
 * ============================================================
 *  หน้า        : เพิ่ม / แก้ไขข้อสอบ (ใช้ไฟล์เดียวทั้งสองแบบ)
 *  สถานะ       : view พร้อมใช้ — รอ backend เพิ่ม route/Controller (ร่างเก็บไว้ที่ branch draft/teaching-backend)
 *  Route       : GET /questions/create → create · POST /questions → store
 *                GET /questions/{id}/edit → edit · POST /questions/{id} → update   (QuestionController)
 *  สิทธิ์       : question.manage
 *  แบบหน้าจอ   : prototype QuestionForm.html (8 ขั้น)
 * ------------------------------------------------------------
 *  ข้อมูลที่ได้รับ
 *    $question          null = เพิ่มใหม่ · มีค่า = แก้ไข (บันทึกแล้วได้ "ฉบับใหม่")
 *    $options           ตัวเลือกของฉบับล่าสุด [label, text, is_correct]
 *    $selectedLoIds     CLO ที่ผูกอยู่ · $latestVersionNo  เลขฉบับล่าสุด
 *    $courses / $chapters / $learningOutcomes / $bloomLevels / $scenarios  ตัวเลือกในฟอร์ม
 *        (บทและ CLO ส่งมาทุกวิชา หน้ากรองตามรายวิชาที่เลือกด้วย JS)
 *    $old / $error / $errors  เมื่อบันทึกไม่ผ่าน — $errors = ['ชื่อช่อง' => 'ข้อความ'] แสดงใต้ช่องนั้น
 * ------------------------------------------------------------
 *  ค่าที่ฟอร์มส่ง (POST)
 *    ทุกชนิด   : csrf_token, type, courses_id ('' = คลังกลาง), chapters_id, bloom_levels_code, scenario_id,
 *               text, explanation, learning_outcome_ids[], student_visible, save_action (draft | draft_add | publish)
 *    ปรนัย     : options[i][label], options[i][text], options[i][is_correct], shuffle_options
 *    ตอบสั้น   : options[i][label], options[i][text]  (ทุกแถว = คำตอบที่ยอมรับ)
 *    ถูก/ผิด   : tf_answer = true | false
 *    อัตนัย    : manual_max_score
 *    ช่องของชนิดที่ไม่ได้เลือกจะถูกปิด (disabled) จึงไม่ถูกส่ง
 * ============================================================
 */
require dirname(__DIR__) . '/includes/header.php';
require __DIR__ . '/_labels.php';

$question         = $question ?? null;
$options          = $options ?? [];
$selectedLoIds    = array_map('intval', $selectedLoIds ?? []);
$latestVersionNo  = $latestVersionNo ?? null;
$courses          = $courses ?? [];
$chapters         = $chapters ?? [];
$learningOutcomes = $learningOutcomes ?? [];
$bloomLevels      = $bloomLevels ?? [];
$scenarios        = $scenarios ?? [];
$old              = $old ?? [];
$errors           = $errors ?? [];
$appUrl           = rtrim((string)env('APP_URL', ''), '/');

$isEdit = $question !== null;
$qid    = $isEdit ? (int)$question['id_questions'] : 0;
/** ค่าของช่อง: ค่าที่กรอกค้าง ($old) > ค่าเดิมของข้อสอบ > ค่าเริ่มต้น */
// ---------- ค่าของแต่ละช่อง: ค่าที่กรอกค้าง ($old) > ค่าเดิมของข้อสอบ > ค่าเริ่มต้น ----------
$val = fn (string $key, mixed $default = '') => $old[$key] ?? ($question[$key] ?? $default);

$type     = (string)$val('type', 'multiple_choice');
$courseId = (string)$val('courses_id', '');
if (isset($old['options']) && is_array($old['options'])) {
    $options = array_values($old['options']);
}
$choiceOptions = $type === 'true_false' ? [] : $options;
if (!$choiceOptions) {
    $choiceOptions = array_map(fn ($l) => ['label' => $l, 'text' => '', 'is_correct' => 0], ['ก', 'ข', 'ค', 'ง']);
}
// ถูก/ผิด: เฉลยมาจาก $old['tf_answer'] หรือ option ที่ is_correct ของข้อเดิม
$tfAnswer = (string)($old['tf_answer'] ?? '');
if ($tfAnswer === '' && $type === 'true_false') {
    foreach ($options as $o) {
        if ((int)$o['is_correct'] === 1) {
            $tfAnswer = in_array($o['label'], ['ถูก', 'true'], true) ? 'true' : 'false';
        }
    }
}
$checkedLo = isset($old['learning_outcome_ids']) ? array_map('intval', (array)$old['learning_outcome_ids']) : $selectedLoIds;
$err = fn (string $key) => isset($errors[$key]) ? '<span class="field__error" role="alert">' . Security::escape($errors[$key]) . '</span>' : '';
$invalid = fn (string $key) => isset($errors[$key]) ? ' aria-invalid="true"' : '';
?>
<?php /* ==================== หัวหน้า ==================== */ ?>
<div class="page-head">
    <div class="page-head__text">
        <p><a href="<?= Security::escape($appUrl) ?>/questions">← คลังข้อสอบ</a></p>
        <h1><?= $isEdit ? 'แก้ไขข้อสอบ ' . Security::escape(question_code($qid)) : 'เพิ่มข้อสอบ' ?></h1>
        <p><?= $isEdit ? 'บันทึกแล้วระบบเก็บเป็นฉบับใหม่ ฉบับเดิมยังดูย้อนหลังได้' : 'ปลายทาง: คลังข้อสอบ · นิสิตยังไม่เห็นจนกว่าจะใส่ในชุดสอบ' ?></p>
    </div>
</div>

<?php if (!empty($error)): ?>
    <div class="alert alert--danger" role="alert"><?= ui_icon('alert') ?><div><b>บันทึกไม่สำเร็จ</b><?= Security::escape((string)$error) ?></div></div>
<?php endif; ?>

<?php /* ==================== ซ้าย = ฟอร์ม 8 ขั้น · ขวา = สรุป ==================== */ ?>
<div class="layout-side">
<form class="card" id="qForm" method="post" action="<?= Security::escape($appUrl . ($isEdit ? '/questions/' . $qid : '/questions')) ?>">
    <input type="hidden" name="csrf_token" value="<?= Security::escape(Security::csrfToken()) ?>">

    <?php /* ==================== ขั้น 1 ชนิดข้อสอบ ==================== */ ?>
    <section class="form-section" aria-labelledby="s1">
        <div class="form-section__head"><span class="form-section__num">1</span><h3 id="s1">ข้อสอบชนิดไหน?</h3></div>
        <fieldset class="choices">
            <legend class="sr-only">ชนิดข้อสอบ</legend>
            <?php foreach ($questionTypeLabels as $value => $label): ?>
                <label class="choice" title="<?= Security::escape($questionTypeHints[$value]) ?>">
                    <input type="radio" name="type" value="<?= $value ?>" <?= $type === $value ? 'checked' : '' ?>>
                    <span><?= Security::escape($label) ?></span>
                </label>
            <?php endforeach; ?>
        </fieldset>
        <p class="field__hint" id="typeHint"><?= Security::escape($questionTypeHints[$type] ?? '') ?></p>
        <?= $err('type') ?>
    </section>

    <?php /* ==================== ขั้น 2 รายวิชาและบท (บทกรองตามรายวิชาด้วย JS ด้านล่าง) ==================== */ ?>
    <section class="form-section" aria-labelledby="s2">
        <div class="form-section__head"><span class="form-section__num">2</span><h3 id="s2">รายวิชาและหัวข้อ</h3></div>
        <div class="form-grid">
            <div class="field">
                <label class="field__label" for="courses_id">รายวิชา</label>
                <select class="select" id="courses_id" name="courses_id"<?= $invalid('courses_id') ?>>
                    <option value="">คลังกลาง (ยังไม่ผูกวิชา)</option>
                    <?php foreach ($courses as $c): ?>
                        <option value="<?= (int)$c['id_courses'] ?>"<?= $courseId === (string)(int)$c['id_courses'] ? ' selected' : '' ?>><?= Security::escape($c['code'] . ' ' . $c['name']) ?></option>
                    <?php endforeach; ?>
                </select>
                <span class="field__hint">คลังกลาง = ข้อพื้นฐานที่หลายวิชาใช้ร่วมกัน</span>
                <?= $err('courses_id') ?>
            </div>
            <div class="field">
                <label class="field__label" for="chapters_id">หัวข้อหลัก (บท)</label>
                <select class="select" id="chapters_id" name="chapters_id"<?= $invalid('chapters_id') ?>>
                    <option value="">ไม่ระบุ</option>
                    <?php foreach ($chapters as $ch): ?>
                        <option value="<?= (int)$ch['id_chapters'] ?>" data-course="<?= (int)$ch['courses_id'] ?>"<?= (string)$val('chapters_id') === (string)(int)$ch['id_chapters'] ? ' selected' : '' ?>>บทที่ <?= (int)$ch['chapters_no'] ?> <?= Security::escape($ch['name']) ?></option>
                    <?php endforeach; ?>
                </select>
                <?= $err('chapters_id') ?>
            </div>
        </div>
    </section>

    <?php /* ==================== ขั้น 3 ระดับ Bloom (บังคับ) ==================== */ ?>
    <section class="form-section" aria-labelledby="s3">
        <div class="form-section__head"><span class="form-section__num">3</span><h3 id="s3">ระดับการวัด (Bloom) <span class="req">*</span></h3></div>
        <?php if ($bloomLevels): ?>
            <fieldset class="choices">
                <legend class="sr-only">ระดับ Bloom</legend>
                <?php foreach ($bloomLevels as $b): ?>
                    <label class="choice">
                        <input type="radio" name="bloom_levels_code" value="<?= Security::escape($b['code']) ?>" required <?= (string)$val('bloom_levels_code') === (string)$b['code'] ? 'checked' : '' ?>>
                        <span><?= Security::escape($b['name_th']) ?></span>
                    </label>
                <?php endforeach; ?>
            </fieldset>
        <?php else: ?>
            <div class="alert"><?= ui_icon('alert') ?><div><b>ยังไม่มีข้อมูลระดับ Bloom</b>ต้องเพิ่มข้อมูลในตาราง bloom_levels ก่อน จึงจะบันทึกข้อสอบได้</div></div>
        <?php endif; ?>
        <?= $err('bloom_levels_code') ?>
    </section>

    <?php /* ==================== ขั้น 4 โจทย์ (และโจทย์สถานการณ์ถ้ามี) ==================== */ ?>
    <section class="form-section" aria-labelledby="s4">
        <div class="form-section__head"><span class="form-section__num">4</span><h3 id="s4">โจทย์</h3></div>
        <?php if ($scenarios): ?>
            <div class="field">
                <label class="field__label" for="scenario_id">ใช้โจทย์สถานการณ์ร่วมกับข้ออื่นไหม?</label>
                <select class="select" id="scenario_id" name="scenario_id">
                    <option value="">ไม่ใช้ (ข้อเดี่ยว)</option>
                    <?php foreach ($scenarios as $s): ?>
                        <option value="<?= (int)$s['id_question_scenarios'] ?>"<?= (string)$val('scenario_id') === (string)(int)$s['id_question_scenarios'] ? ' selected' : '' ?>><?= Security::escape($s['title']) ?></option>
                    <?php endforeach; ?>
                </select>
                <span class="field__hint">ข้อในกลุ่มสถานการณ์อยู่ติดกันเสมอ แม้เปิดสลับข้อ</span>
            </div>
        <?php endif; ?>
        <div class="field">
            <label class="field__label" for="text">ข้อความโจทย์ <span class="req">*</span></label>
            <textarea class="textarea textarea--lg" id="text" name="text" required<?= $invalid('text') ?> placeholder="พิมพ์โจทย์ที่นี่"><?= Security::escape((string)$val('text')) ?></textarea>
            <?= $err('text') ?>
        </div>
    </section>

    <?php /* ==================== ขั้น 5 คำตอบ — แสดงเฉพาะส่วนของชนิดที่เลือก (data-show-for) ==================== */ ?>
    <section class="form-section" aria-labelledby="s5">
        <div class="form-section__head"><span class="form-section__num">5</span><h3 id="s5">คำตอบและการให้คะแนน</h3></div>

        <div data-show-for="multiple_choice short_answer">
            <p class="field__hint" data-show-for="multiple_choice">ติ๊ก "ถูก" หน้าตัวเลือกที่เป็นคำตอบ (ถูกได้มากกว่า 1 ข้อ) · ทุกข้อคิด 1 คะแนน</p>
            <p class="field__hint" data-show-for="short_answer">ใส่คำตอบที่ยอมรับได้ทุกแบบ ระบบตรวจเทียบให้อัตโนมัติ · ทุกข้อคิด 1 คะแนน</p>
            <div class="opt-rows" id="optRows">
                <?php foreach ($choiceOptions as $i => $o): ?>
                    <div class="opt-row">
                        <input class="input input--label" name="options[<?= $i ?>][label]" value="<?= Security::escape((string)$o['label']) ?>" aria-label="ลำดับตัวเลือก" maxlength="20">
                        <input class="input" name="options[<?= $i ?>][text]" value="<?= Security::escape((string)$o['text']) ?>" aria-label="ข้อความตัวเลือก <?= Security::escape((string)$o['label']) ?>" placeholder="ข้อความตัวเลือก">
                        <label class="check" data-show-for="multiple_choice"><input type="checkbox" name="options[<?= $i ?>][is_correct]" value="1" <?= (int)($o['is_correct'] ?? 0) === 1 ? 'checked' : '' ?>>ถูก</label>
                        <button class="btn btn--ghost btn--sm" type="button" data-remove-row aria-label="ลบตัวเลือกนี้">ลบ</button>
                    </div>
                <?php endforeach; ?>
            </div>
            <p><button class="btn btn--secondary btn--sm" type="button" id="addRow">+ เพิ่มตัวเลือก</button></p>
            <?= $err('options') ?>
        </div>

        <fieldset class="choices" data-show-for="true_false">
            <legend>คำตอบที่ถูก</legend>
            <label class="choice"><input type="radio" name="tf_answer" value="true" <?= $tfAnswer === 'true' ? 'checked' : '' ?>><span>ถูก</span></label>
            <label class="choice"><input type="radio" name="tf_answer" value="false" <?= $tfAnswer === 'false' ? 'checked' : '' ?>><span>ผิด</span></label>
        </fieldset>
        <div data-show-for="true_false"><?= $err('tf_answer') ?></div>

        <div class="form-grid" data-show-for="essay">
            <div class="field">
                <label class="field__label" for="manual_max_score">คะแนนเต็ม</label>
                <input class="input" type="number" id="manual_max_score" name="manual_max_score" min="0" step="0.5" value="<?= Security::escape((string)$val('manual_max_score', '')) ?>"<?= $invalid('manual_max_score') ?>>
                <span class="field__hint">นิสิตพิมพ์คำตอบเอง อาจารย์ให้คะแนนในหน้าตรวจข้อเขียนหลังปิดสอบ</span>
                <?= $err('manual_max_score') ?>
            </div>
        </div>

        <div class="alert" data-show-for="matching"><?= ui_icon('alert') ?><div><b>ข้อสอบจับคู่ยังบันทึกไม่ได้</b>ตาราง question_options ยังไม่มีช่องเก็บข้อความฝั่งที่จับคู่ ต้องตกลงรูปแบบข้อมูลกับ backend ก่อน</div></div>
    </section>

    <?php /* ==================== ขั้น 6 คำอธิบายเฉลย ==================== */ ?>
    <section class="form-section" aria-labelledby="s6">
        <div class="form-section__head"><span class="form-section__num">6</span><h3 id="s6">คำอธิบายเฉลย</h3><p>ไม่บังคับ</p></div>
        <div class="field">
            <label class="field__label sr-only" for="explanation">คำอธิบายเฉลย</label>
            <textarea class="textarea" id="explanation" name="explanation" placeholder="อธิบายว่าทำไมคำตอบนี้ถูก หรือใส่แนวคำตอบของข้อเขียน"><?= Security::escape((string)$val('explanation')) ?></textarea>
        </div>
    </section>

    <?php /* ==================== ขั้น 7 CLO (กรองตามรายวิชา) ==================== */ ?>
    <section class="form-section" aria-labelledby="s7">
        <div class="form-section__head"><span class="form-section__num">7</span><h3 id="s7">ผลการเรียนรู้ (CLO)</h3><p>ไม่บังคับ · เลือกได้หลายข้อ</p></div>
        <?php if ($learningOutcomes): ?>
            <div id="loList">
                <?php foreach ($learningOutcomes as $lo): ?>
                    <label class="check" data-course="<?= (int)$lo['courses_id'] ?>">
                        <input type="checkbox" name="learning_outcome_ids[]" value="<?= (int)$lo['id_learning_outcomes'] ?>" <?= in_array((int)$lo['id_learning_outcomes'], $checkedLo, true) ? 'checked' : '' ?>>
                        <span><b><?= Security::escape($lo['code']) ?></b> <?= Security::escape($lo['description']) ?></span>
                    </label>
                <?php endforeach; ?>
            </div>
            <p class="field__hint" id="loEmpty" hidden>รายวิชานี้ยังไม่มี CLO</p>
        <?php else: ?>
            <p class="field__hint">เลือกรายวิชาที่มี CLO แล้วจะแสดงให้เลือกที่นี่</p>
        <?php endif; ?>
    </section>

    <?php /* ==================== ขั้น 8 การแสดงผล + ปุ่มบันทึก (save_action = draft | draft_add | publish) ==================== */ ?>
    <section class="form-section" aria-labelledby="s8">
        <div class="form-section__head"><span class="form-section__num">8</span><h3 id="s8">การแสดงผล</h3></div>
        <label class="check" data-show-for="multiple_choice"><input type="checkbox" name="shuffle_options" value="1" <?= (int)$val('shuffle_options', 0) === 1 ? 'checked' : '' ?>><span>สลับลำดับตัวเลือกให้นิสิตแต่ละคน</span></label>
        <label class="check"><input type="checkbox" name="student_visible" value="1" <?= (int)$val('student_visible', 0) === 1 ? 'checked' : '' ?>><span>ให้นิสิตเห็นข้อนี้และคำอธิบายเฉลยได้<small>ตามเวลาแสดงเฉลยที่ตั้งในชุดสอบ</small></span></label>
    </section>

    <div class="btn-row btn-row--end">
        <a class="btn btn--ghost" href="<?= Security::escape($appUrl . ($isEdit ? '/questions/' . $qid : '/questions')) ?>">ยกเลิก</a>
        <button class="btn btn--secondary" type="submit" name="save_action" value="draft">บันทึกเป็นร่าง</button>
        <?php if (!$isEdit): ?><button class="btn btn--secondary" type="submit" name="save_action" value="draft_add">บันทึก แล้วเพิ่มข้อต่อ</button><?php endif; ?>
        <button class="btn btn--primary" type="submit" name="save_action" value="publish">บันทึกและเผยแพร่</button>
    </div>
</form>

<?php /* ==================== กล่องสรุปด้านขวา ==================== */ ?>
<aside class="card" aria-label="สรุปข้อสอบ">
    <div class="sec-title"><?= ui_icon('log') ?><h2>สรุป</h2></div>
    <dl class="meta-list">
        <dt>ผู้ออกข้อสอบ</dt><dd><?= Security::escape((string)($authUser['display_name'] ?? '')) ?> <span class="muted">(อัตโนมัติ)</span></dd>
        <dt>สถานะ</dt><dd><?= Security::escape($questionStatusLabels[(string)$val('status', 'draft')] ?? 'ฉบับร่าง') ?></dd>
        <dt>ฉบับที่</dt><dd><?= $isEdit && $latestVersionNo !== null ? (int)$latestVersionNo . ' → ' . ((int)$latestVersionNo + 1) : '1' ?></dd>
        <dt>ความยาก · อำนาจจำแนก</dt><dd class="muted">คำนวณเองหลังใช้สอบ</dd>
    </dl>
</aside>
</div>

<?php /* ==================== JS ในหน้า: แสดง/ซ่อนช่องตามชนิด · กรองบท/CLO ตามรายวิชา · เพิ่ม/ลบแถวตัวเลือก (ไม่เรียก API) ==================== */ ?>
<script>
// แสดง/ซ่อนช่องตามชนิดข้อสอบ + กรองบท/CLO ตามรายวิชา (ไม่เรียก API — ข้อมูลอยู่ในหน้าแล้ว)
(function () {
    var form = document.getElementById('qForm');
    var hints = <?= json_encode($questionTypeHints, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) ?>;

    function applyType() {
        var t = (form.querySelector('input[name="type"]:checked') || {}).value || 'multiple_choice';
        form.querySelectorAll('[data-show-for]').forEach(function (el) {
            var on = el.getAttribute('data-show-for').split(' ').indexOf(t) !== -1;
            el.hidden = !on;
            // ช่องที่ซ่อนอยู่ไม่ส่งค่า (แต่ไม่ปิดช่องที่ยังอยู่ในบล็อกที่แสดง)
            el.querySelectorAll('input, textarea, select').forEach(function (inp) {
                var visible = true;
                for (var p = inp.closest('[data-show-for]'); p; p = p.parentElement && p.parentElement.closest('[data-show-for]')) {
                    if (p.getAttribute('data-show-for').split(' ').indexOf(t) === -1) { visible = false; break; }
                }
                inp.disabled = !visible;
            });
        });
        document.getElementById('typeHint').textContent = hints[t] || '';
    }

    function applyCourse() {
        var c = document.getElementById('courses_id').value;
        var ch = document.getElementById('chapters_id');
        Array.prototype.forEach.call(ch.options, function (o) {
            if (!o.value) return;
            o.hidden = o.disabled = o.getAttribute('data-course') !== c;
            if (o.hidden && o.selected) ch.value = '';
        });
        var shown = 0;
        form.querySelectorAll('#loList [data-course]').forEach(function (l) {
            var on = l.getAttribute('data-course') === c;
            l.hidden = !on;
            l.querySelector('input').disabled = !on;
            if (on) shown++;
        });
        var empty = document.getElementById('loEmpty');
        if (empty) empty.hidden = shown > 0;
    }

    // เพิ่ม/ลบแถวตัวเลือก
    var rows = document.getElementById('optRows');
    var thai = ['ก', 'ข', 'ค', 'ง', 'จ', 'ฉ', 'ช', 'ซ', 'ฌ', 'ญ'];
    document.getElementById('addRow').addEventListener('click', function () {
        var n = rows.children.length;
        var tpl = rows.children[0].cloneNode(true);
        tpl.querySelectorAll('input').forEach(function (inp) {
            inp.name = inp.name.replace(/options\[\d+\]/, 'options[' + n + ']');
            if (inp.type === 'checkbox') inp.checked = false; else inp.value = '';
        });
        tpl.querySelector('.input--label').value = thai[n] || String(n + 1);
        rows.appendChild(tpl);
        applyType();
        tpl.querySelector('.input:not(.input--label)').focus();
    });
    rows.addEventListener('click', function (e) {
        var btn = e.target.closest('[data-remove-row]');
        if (btn && rows.children.length > 1) btn.closest('.opt-row').remove();
    });

    form.querySelectorAll('input[name="type"]').forEach(function (r) { r.addEventListener('change', applyType); });
    document.getElementById('courses_id').addEventListener('change', applyCourse);
    applyType();
    applyCourse();
})();
</script>
<?php require dirname(__DIR__) . '/includes/footer.php'; ?>
