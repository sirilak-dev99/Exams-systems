<?php
/**
 * ============================================================
 *  หน้า        : ดูรายละเอียดข้อสอบ
 *  Route       : GET /questions/{id} → QuestionController@show
 *  สิทธิ์       : question.manage
 *  แบบหน้าจอ   : prototype QuestionView.html
 * ------------------------------------------------------------
 *  ข้อมูลที่ได้รับ (QuestionBankModel) — แสดง "ฉบับล่าสุด" ของข้อสอบ
 *    $question          ข้อสอบ + ชื่อรายวิชา/บท/Bloom/สถานการณ์/ผู้ออกข้อสอบ · null = ไม่พบ
 *    $options           ตัวเลือก [label, text, is_correct, sort_order, image]
 *    $versions          ทุกฉบับ ใหม่ → เก่า [id_question_versions, version_no, created_at, created_by_name]
 *    $learningOutcomes  CLO [code, description]
 *    $concepts          แนวคิด [main_code, main_name, sub_code, sub_name]
 *  "ประวัติการใช้งาน" และ "คุณภาพข้อสอบ" ยังไม่มีข้อมูล (ต้องมีการสอบจริงก่อน)
 * ============================================================
 */
require dirname(__DIR__) . '/includes/header.php';
require __DIR__ . '/_labels.php';

$question         = $question ?? null;
$options          = $options ?? [];
$versions         = $versions ?? [];
$learningOutcomes = $learningOutcomes ?? [];
$concepts         = $concepts ?? [];
$appUrl           = rtrim((string)env('APP_URL', ''), '/');
?>
<?php /* ==================== ไม่พบข้อสอบ ==================== */ ?>
<?php if ($question === null): ?>
    <section class="card error-card">
        <?= ui_icon('bank', 40) ?>
        <h1>ไม่พบข้อสอบ</h1>
        <p class="muted">ข้อสอบนี้อาจถูกลบ หรือลิงก์ไม่ถูกต้อง</p>
        <a class="btn btn--primary" href="<?= Security::escape($appUrl) ?>/questions"><?= ui_icon('back', 20) ?>กลับคลังข้อสอบ</a>
    </section>
<?php else: ?>
<?php
    $id        = (int)$question['id_questions'];
    $type      = (string)$question['type'];
    $latestVer = $versions[0]['version_no'] ?? null;
    $correct   = array_values(array_filter($options, fn ($o) => (int)$o['is_correct'] === 1));
    $whereText = !empty($question['courses_id'])
        ? trim(($question['course_code'] ?? '') . ' ' . ($question['course_name'] ?? ''))
        : 'คลังกลาง (ยังไม่ผูกวิชา)';
    $chapterText = !empty($question['courses_id']) && !empty($question['chapters_id']) ? 'บทที่ ' . (int)($question['chapters_no'] ?? 0) . ' ' . ($question['chapter_name'] ?? '') : 'ยังไม่ระบุบท';
?>
<?php /* ==================== หัวหน้า: รหัสข้อสอบ · สถานะ · ปุ่มแก้ไข ==================== */ ?>
<div class="page-head">
    <div class="page-head__text">
        <p><a href="<?= Security::escape($appUrl) ?>/questions">← คลังข้อสอบ</a></p>
        <h1><span class="q-code"><?= Security::escape(question_code($id)) ?></span></h1>
        <p class="row">
            <span class="badge badge--<?= Security::escape((string)$question['status']) ?>"><?= Security::escape($questionStatusLabels[$question['status']] ?? (string)$question['status']) ?></span>
            <span><?= Security::escape($whereText) ?> · <?= Security::escape($chapterText) ?> · <?= Security::escape($questionTypeLabels[$type] ?? $type) ?><?= $latestVer !== null ? ' · ฉบับที่ ' . (int)$latestVer : '' ?></span>
        </p>
    </div>
    <div class="btn-row">
        <a class="btn btn--primary" href="<?= Security::escape($appUrl . '/questions/' . $id . '/edit') ?>"><?= ui_icon('pen', 20) ?>แก้ไข</a>
    </div>
</div>

<?php /* ==================== ซ้าย = โจทย์ ตัวเลือก เฉลย · ขวา = ข้อมูลข้อสอบ คุณภาพ ฉบับ ==================== */ ?>
<div class="layout-side">
    <div class="form-stack">
        <?php if (!empty($question['scenario_id'])): ?>
            <section class="card" aria-label="โจทย์สถานการณ์">
                <div class="sec-title"><?= ui_icon('layers') ?><h2>สถานการณ์ <?= Security::escape((string)($question['scenario_title'] ?? '')) ?></h2></div>
                <p class="q-text"><?= Security::escape((string)($question['scenario_content'] ?? '')) ?></p>
            </section>
        <?php endif; ?>

        <section class="card" aria-label="คำถามและตัวเลือก">
            <div class="sec-title"><?= ui_icon('bank') ?><h2>คำถามและตัวเลือก</h2></div>
            <p class="q-text"><?= Security::escape((string)$question['text']) ?></p>
            <?php if (empty($question['image'])): ?>
                <p class="muted small">ไม่มีรูปภาพประกอบ</p>
            <?php endif; ?>

            <?php if ($type === 'essay'): ?>
                <div class="alert"><?= ui_icon('pen') ?><div><b>ข้อเขียน</b>นิสิตพิมพ์คำตอบเอง อาจารย์ให้คะแนนในหน้าตรวจข้อเขียนหลังปิดสอบ · คะแนนเต็ม <?= Security::escape(question_score($question['manual_max_score'] ?? null)) ?></div></div>
            <?php elseif ($options): ?>
                <ol class="opt-list">
                    <?php foreach ($options as $o): ?>
                        <li class="<?= (int)$o['is_correct'] === 1 ? 'is-correct' : '' ?>">
                            <span class="opt-label"><?= Security::escape((string)$o['label']) ?>.</span>
                            <span><?= Security::escape((string)$o['text']) ?></span>
                            <?php if ((int)$o['is_correct'] === 1): ?><span class="badge badge--active"><?= $type === 'short_answer' ? 'คำตอบที่ยอมรับ' : 'เฉลย' ?></span><?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                </ol>
            <?php else: ?>
                <div class="empty empty--sm"><?= ui_icon('inbox', 32) ?><b>ยังไม่มีตัวเลือก</b></div>
            <?php endif; ?>
        </section>

        <section class="card" aria-label="เฉลยและคำอธิบาย">
            <div class="sec-title"><?= ui_icon('file') ?><h2>เฉลยและคำอธิบาย</h2></div>
            <?php if ($correct && $type !== 'essay'): ?>
                <p><b>คำตอบที่ถูก:</b> <?= Security::escape(implode(', ', array_map(fn ($o) => $o['label'] . '. ' . $o['text'], $correct))) ?></p>
            <?php endif; ?>
            <p class="q-text"><?= !empty($question['explanation']) ? Security::escape((string)$question['explanation']) : '<span class="muted">ยังไม่มีคำอธิบายเฉลย</span>' ?></p>
            <p class="muted small">ให้นิสิตเห็นข้อนี้และคำอธิบาย: <b><?= (int)($question['student_visible'] ?? 0) === 1 ? 'เปิด' : 'ปิด' ?></b> · นิสิตเห็นเมื่อถึงเวลาแสดงเฉลยที่ตั้งในชุดสอบ</p>
        </section>

        <section class="card" aria-label="ประวัติการใช้งานข้อสอบ">
            <div class="sec-title"><?= ui_icon('clip') ?><h2>ประวัติการใช้งานข้อสอบ</h2></div>
            <div class="empty empty--sm"><?= ui_icon('inbox', 32) ?><b>ยังไม่มีข้อมูล</b><span>จะแสดงชุดสอบที่ใช้ข้อนี้เมื่อระบบชุดสอบเชื่อมกับคลังข้อสอบ</span></div>
        </section>
    </div>

    <div class="form-stack">
        <section class="card" aria-label="ข้อมูลข้อสอบ">
            <div class="sec-title"><?= ui_icon('log') ?><h2>ข้อมูลข้อสอบ</h2></div>
            <dl class="meta-list">
                <dt>รายวิชา</dt><dd><?= Security::escape($whereText) ?></dd>
                <dt>บท</dt><dd><?= Security::escape($chapterText) ?></dd>
                <dt>ชนิด</dt><dd><?= Security::escape($questionTypeLabels[$type] ?? $type) ?><?= in_array($type, ['multiple_choice'], true) && $correct ? ' · ถูก ' . count($correct) . ' ข้อ' : '' ?></dd>
                <dt>ระดับ Bloom</dt><dd><?= Security::escape((string)($question['bloom_name_th'] ?? $question['bloom_levels_code'])) ?></dd>
                <dt>คะแนน</dt><dd><?= Security::escape(question_score($question['manual_max_score'] ?? null)) ?></dd>
                <?php if (in_array($type, ['multiple_choice', 'matching'], true)): ?>
                    <dt>สลับตัวเลือก</dt><dd><?= (int)($question['shuffle_options'] ?? 0) === 1 ? 'สลับให้แต่ละคน' : 'ไม่สลับ' ?></dd>
                <?php endif; ?>
                <dt>ผลการเรียนรู้ (CLO)</dt>
                <dd><?= $learningOutcomes ? implode('<br>', array_map(fn ($lo) => Security::escape($lo['code'] . ' ' . $lo['description']), $learningOutcomes)) : '<span class="muted">ไม่ระบุ</span>' ?></dd>
                <?php if ($concepts): ?>
                    <dt>แนวคิดหลัก / รอง</dt>
                    <dd><?= implode('<br>', array_map(fn ($c) => Security::escape(trim(($c['main_code'] ?? '') . ' ' . ($c['main_name'] ?? '') . (!empty($c['sub_code']) ? ' · ' . $c['sub_code'] . ' ' . ($c['sub_name'] ?? '') : ''))), $concepts)) ?></dd>
                <?php endif; ?>
                <?php if (!empty($question['created_by_name'])): ?>
                    <dt>ผู้ออกข้อสอบ</dt><dd><?= Security::escape((string)$question['created_by_name']) ?></dd>
                <?php endif; ?>
                <dt>แก้ไขล่าสุด</dt><dd><?= Security::escape(thai_date((string)($question['updated_at'] ?? ''))) ?></dd>
            </dl>
        </section>

        <section class="card" aria-label="คุณภาพข้อสอบ">
            <div class="sec-title"><?= ui_icon('chart') ?><h2>คุณภาพข้อสอบ</h2></div>
            <div class="empty empty--sm"><?= ui_icon('chart', 32) ?><b>ยังไม่มีข้อมูล</b><span>ค่าความยาก (p) และอำนาจจำแนก (r) คำนวณหลังใช้สอบจริง</span></div>
        </section>

        <section class="card" aria-label="ฉบับของข้อสอบ">
            <div class="sec-title"><?= ui_icon('layers') ?><h2>ฉบับของข้อสอบ</h2></div>
            <?php if ($versions): ?>
                <ul class="version-list">
                    <?php foreach ($versions as $i => $v): ?>
                        <li>
                            <span><b>ฉบับที่ <?= (int)$v['version_no'] ?></b><?= $i === 0 ? ' <span class="badge badge--plain">ล่าสุด</span>' : '' ?></span>
                            <span class="muted"><?= Security::escape(thai_date((string)$v['created_at'])) ?><?= !empty($v['created_by_name']) ? ' · ' . Security::escape((string)$v['created_by_name']) : '' ?></span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php else: ?>
                <div class="empty empty--sm"><?= ui_icon('inbox', 32) ?><b>ยังไม่มีประวัติฉบับ</b></div>
            <?php endif; ?>
        </section>
    </div>
</div>
<?php endif; ?>
<?php require dirname(__DIR__) . '/includes/footer.php'; ?>
