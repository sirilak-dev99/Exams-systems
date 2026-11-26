<?php
/**
 * ============================================================
 *  หน้า        : รายละเอียดรายวิชาที่สอน (9 แท็บ)
 *  Route       : GET /my-courses/{id} → TeachingController@courseShow   (id = id_course_offerings)
 *  สิทธิ์       : ผู้สอนของรายวิชานี้ (section_staff) หรือแอดมิน · ไม่ได้สอน = "ไม่พบรายวิชา" (404)
 *  แบบหน้าจอ   : prototype CourseView.html · คู่มือฝั่งอาจารย์ หน้า 06–17
 * ------------------------------------------------------------
 *  แท็บ (GET ?tab=)
 *    ov ภาพรวม · staff ผู้สอน · sec กลุ่มเรียน · stu นิสิต · topic หัวข้อหลัก/รอง
 *    lo ผลการเรียนรู้ · content เนื้อหาการเรียน · bank ข้อสอบ · ex ชุดสอบ
 *
 *  ข้อมูลที่ได้รับ (TeachingModel::getOfferingDetail · ไม่ส่งรายการไหน แท็บนั้นแสดง "ยังไม่มีข้อมูล")
 *    $offering          ข้อมูลรายวิชาที่เปิดสอน + my_role (coordinator | teacher | assistant) · null = ไม่พบ
 *    $stats             ตัวเลขสรุป [students, sections, chapters, concepts_main, concepts_sub, questions]
 *    $staff             ผู้สอน [display_name, email, section_nos, staff_role]
 *    $sections          กลุ่มเรียน [section_no, staff_names, enrolled, dropped]
 *    $students          นิสิต [student_code, display_name, email, section_no, enrollment_status]
 *    $chapters          บท [chapters_no, name, question_count]
 *    $concepts          แนวคิดหลัก [code, name, question_count, subs => แนวคิดรอง [code, name, question_count]]
 *    $learningOutcomes  CLO [code, description, question_count]
 *    $contents          เนื้อหาการเรียน [title, content_type, status, description, attachment_count, updated_at]
 *    $questions         ข้อสอบของวิชา [id_questions, text, chapters_no, chapter_name, bloom_name_th, status]
 *    $exams             ชุดสอบของรายวิชานี้ [id_exams, title, kind, open_at, close_at, status, eligible_count, submitted_count, avg_score]
 *
 *  ปุ่มที่ต้องบันทึกข้อมูล (แก้ไขรายวิชา, เพิ่มผู้สอน, เพิ่ม CLO ฯลฯ) ยังไม่มี route → แสดงเป็นปุ่มจาง "เร็วๆ นี้"
 * ============================================================
 */

// ---------- รับข้อมูล (ค่าเริ่มต้นเมื่อไม่ได้ส่งมา) ----------
$offering         = $offering ?? null;
$stats            = $stats ?? [];
$staff            = $staff ?? [];
$sections         = $sections ?? [];
$students         = $students ?? [];
$chapters         = $chapters ?? [];
$concepts         = $concepts ?? [];
$learningOutcomes = $learningOutcomes ?? [];
$contents         = $contents ?? [];
$questions        = $questions ?? [];
$exams            = $exams ?? [];
$appUrl           = rtrim((string)env('APP_URL', ''), '/');
$listPath         = $listPath ?? '/my-courses';

$pageTitle = $offering ? $offering['code'] . ' ' . $offering['name'] : 'ไม่พบรายวิชา';
$crumbSub  = $offering ? $offering['code'] . ' ' . $offering['name'] : null;
require dirname(__DIR__) . '/includes/header.php';

// ---------- แท็บ และข้อความไทยของค่าในฐานข้อมูล ----------
$tabs = ['ov' => 'ภาพรวม', 'staff' => 'ผู้สอน', 'sec' => 'กลุ่มเรียน', 'stu' => 'นิสิต', 'topic' => 'หัวข้อหลัก/รอง',
         'lo' => 'ผลการเรียนรู้', 'content' => 'เนื้อหาการเรียน', 'bank' => 'ข้อสอบ', 'ex' => 'ชุดสอบ'];
$tab = (string)($_GET['tab'] ?? 'ov');
if (!isset($tabs[$tab])) { $tab = 'ov'; }

$offeringStatus = ['open' => 'เปิดสอน', 'completed' => 'สิ้นสุดภาค', 'closed' => 'ปิดแล้ว'];
$staffRoles     = ['coordinator' => 'ผู้ประสานงานรายวิชา', 'teacher' => 'อาจารย์ผู้สอน', 'assistant' => 'ผู้ช่วยสอน'];
$enrollLabels   = ['enrolled' => 'ลงทะเบียนแล้ว', 'dropped' => 'ถอนรายวิชา', 'completed' => 'เรียนจบแล้ว'];
$qStatus        = ['draft' => 'ฉบับร่าง', 'published' => 'เผยแพร่แล้ว', 'archived' => 'เก็บถาวร'];
$contentTypes   = ['text' => 'ข้อความ', 'file' => 'ไฟล์', 'video' => 'วิดีโอ', 'link' => 'ลิงก์', 'mixed' => 'หลายรูปแบบ'];
$contentStatus  = ['draft' => 'ร่าง', 'published' => 'เผยแพร่แล้ว', 'archived' => 'เก็บถาวร'];
$examStatus     = ['draft' => 'ฉบับร่าง', 'published' => 'เผยแพร่', 'live' => 'กำลังสอบ', 'closed' => 'ปิดการสอบ', 'archived' => 'เก็บถาวร'];
$now            = $now ?? date('Y-m-d H:i:s');

// ---------- ฟังก์ชันช่วย ----------
/** ปุ่มที่ต้องมี route ฝั่งบันทึก — ยังไม่มี จึงแสดงเป็นปุ่มจาง "เร็วๆ นี้" */
$soonBtn = fn (string $label, string $icon = 'pen', string $cls = 'btn--secondary btn--sm') =>
    '<span class="btn ' . $cls . '" aria-disabled="true" title="เร็วๆ นี้ — ยังไม่มี route">' . ui_icon($icon, 18) . Security::escape($label) . '</span>';
$empty = fn (string $title, string $hint = '') =>
    '<div class="empty">' . ui_icon('inbox', 40) . '<b>' . Security::escape($title) . '</b>' . ($hint !== '' ? '<span>' . Security::escape($hint) . '</span>' : '') . '</div>';
$tabUrl = fn (string $key, array $extra = []) => $appUrl . $listPath . '/' . (int)($offering['id_course_offerings'] ?? 0) . '?' . http_build_query(['tab' => $key] + $extra);
?>
<?php /* ==================== ไม่พบรายวิชา / ไม่มีสิทธิ์ ==================== */ ?>
<?php if ($offering === null): ?>
    <section class="card error-card">
        <?= ui_icon('book', 40) ?>
        <h1>ไม่พบรายวิชา</h1>
        <p class="muted">รายวิชานี้อาจไม่มีอยู่ หรือท่านไม่ได้เป็นผู้สอนของรายวิชานี้</p>
        <a class="btn btn--primary" href="<?= Security::escape($appUrl . $listPath) ?>"><?= ui_icon('back', 20) ?>กลับจัดการรายวิชา</a>
    </section>
<?php else: ?>
<?php $isCoordinator = ($offering['my_role'] ?? '') === 'coordinator'; ?>

<?php /* ==================== หัวหน้า: ชื่อวิชา · สถานะ · ตัวเลขสรุป · แท็บ ==================== */ ?>
<div class="page-head">
    <div class="page-head__text">
        <p><a href="<?= Security::escape($appUrl . $listPath) ?>">← จัดการรายวิชา</a></p>
        <h1 class="row"><?= Security::escape($offering['code'] . ' ' . $offering['name']) ?>
            <span class="badge badge--offering-<?= Security::escape((string)$offering['status']) ?>"><?= Security::escape($offeringStatus[$offering['status']] ?? (string)$offering['status']) ?></span></h1>
        <p>ภาคการศึกษา <?= (int)$offering['term_no'] ?>/<?= (int)$offering['academic_year'] ?>
            · <?= Security::escape((string)$offering['program_name']) ?> ฉบับที่ <?= (int)$offering['version_no'] ?>
            <?= !empty($offering['year_level']) ? ' · ชั้นปีที่ ' . (int)$offering['year_level'] : '' ?></p>
    </div>
    <?php if ($isCoordinator): ?>
        <div class="btn-row"><?= $soonBtn('แก้ไขรายวิชา', 'pen', 'btn--secondary') ?></div>
    <?php endif; ?>
</div>

<div class="kpis" role="list">
    <div class="kpi" role="listitem"><b><?= (int)($stats['students'] ?? 0) ?></b><span>นิสิต</span></div>
    <div class="kpi" role="listitem"><b><?= (int)($stats['sections'] ?? 0) ?></b><span>กลุ่มเรียน</span></div>
    <div class="kpi" role="listitem"><b><?= (int)($stats['chapters'] ?? 0) ?></b><span>หัวข้อหลัก (บท)<?= isset($stats['concepts_sub']) ? ' · ' . (int)$stats['concepts_sub'] . ' แนวคิดรอง' : '' ?></span></div>
    <div class="kpi" role="listitem"><b><?= (int)($stats['questions'] ?? 0) ?></b><span>ข้อสอบในคลัง</span></div>
</div>

<nav class="tabs tabs--scroll" aria-label="ส่วนของรายวิชา">
    <?php foreach ($tabs as $key => $label): ?>
        <a href="<?= Security::escape($tabUrl($key)) ?>"<?= $key === $tab ? ' aria-current="page"' : '' ?>><?= Security::escape($label) ?></a>
    <?php endforeach; ?>
</nav>

<?php /* ==================== แท็บ: ภาพรวม — ข้อมูลรายวิชา 9 ช่อง ==================== */ ?>
<?php if ($tab === 'ov'): ?>
    <section class="card" aria-label="ภาพรวมรายวิชา">
        <dl class="meta-grid">
            <div><dt>รหัสวิชา</dt><dd><?= Security::escape((string)$offering['code']) ?></dd></div>
            <div><dt>ชื่อวิชา</dt><dd><?= Security::escape((string)$offering['name']) ?></dd></div>
            <div><dt>กลุ่มวิชา</dt><dd><?= Security::escape((string)($offering['course_group_name'] ?? '—')) ?></dd></div>
            <div><dt>หลักสูตร</dt><dd><?= Security::escape((string)$offering['program_name']) ?><?= !empty($offering['program_code']) ? ' (' . Security::escape((string)$offering['program_code']) . ')' : '' ?></dd></div>
            <div><dt>ฉบับหลักสูตร</dt><dd>ฉบับที่ <?= (int)$offering['version_no'] ?><?= !empty($offering['effective_from']) ? ' · ใช้ตั้งแต่ ' . Security::escape(thai_date((string)$offering['effective_from'], false)) : '' ?></dd></div>
            <div><dt>อยู่ในหลักสูตร</dt><dd><?= !empty($offering['year_level']) ? 'ชั้นปีที่ ' . (int)$offering['year_level'] : '—' ?><?= !empty($offering['semester_no']) ? ' · ภาคที่ ' . (int)$offering['semester_no'] : '' ?></dd></div>
            <div><dt>ปีการศึกษา / ภาค</dt><dd><?= (int)$offering['academic_year'] ?> / ภาค <?= (int)$offering['term_no'] ?></dd></div>
            <div><dt>สถานะการเปิดสอน</dt><dd><?= Security::escape($offeringStatus[$offering['status']] ?? (string)$offering['status']) ?></dd></div>
            <div><dt>เกณฑ์ผ่านของภาคนี้</dt><dd><?= $offering['pass_percent'] !== null ? rtrim(rtrim(number_format((float)$offering['pass_percent'], 2), '0'), '.') . '%' : '—' ?></dd></div>
        </dl>
        <p class="muted small">รหัส ชื่อ และกลุ่มวิชาแก้ได้ที่งานวิชาการ · ผู้ประสานงานรายวิชาแก้หัวข้อ ผลการเรียนรู้ เกณฑ์ผ่าน และผู้สอนได้</p>
    </section>

<?php /* ==================== แท็บ: ผู้สอน ==================== */ ?>
<?php elseif ($tab === 'staff'): ?>
    <section class="card card--flush" aria-label="ผู้สอนในรายวิชา">
        <div class="card__head"><div class="sec-title"><?= ui_icon('users') ?><h2>ผู้สอนในรายวิชา (<?= count($staff) ?> คน)</h2></div><?= $isCoordinator ? $soonBtn('เพิ่มผู้สอน', 'user') : '' ?></div>
        <?php if (!$staff): ?><?= $empty('ยังไม่มีผู้สอน') ?><?php else: ?>
        <div class="table-wrap"><table class="table">
            <thead><tr><th scope="col">ชื่อ</th><th scope="col">อีเมล</th><th scope="col">กลุ่มเรียน</th><th scope="col">บทบาท</th></tr></thead>
            <tbody>
            <?php foreach ($staff as $p): ?>
                <tr><td><b><?= Security::escape((string)$p['display_name']) ?></b></td><td><?= Security::escape((string)$p['email']) ?></td>
                    <td class="nowrap"><?= Security::escape((string)$p['section_nos']) ?></td>
                    <td><span class="badge badge--plain<?= $p['staff_role'] === 'coordinator' ? ' badge--active' : '' ?>"><?= Security::escape($staffRoles[$p['staff_role']] ?? (string)$p['staff_role']) ?></span></td></tr>
            <?php endforeach; ?>
            </tbody></table></div>
        <?php endif; ?>
    </section>

<?php /* ==================== แท็บ: กลุ่มเรียน ==================== */ ?>
<?php elseif ($tab === 'sec'): ?>
    <section class="card card--flush" aria-label="กลุ่มเรียน">
        <div class="card__head"><div class="sec-title"><?= ui_icon('layers') ?><h2>กลุ่มเรียน (<?= count($sections) ?> กลุ่ม)</h2></div></div>
        <?php if (!$sections): ?><?= $empty('ยังไม่มีกลุ่มเรียน') ?><?php else: ?>
        <div class="table-wrap"><table class="table">
            <thead><tr><th scope="col">กลุ่มเรียน</th><th scope="col">ผู้สอน</th><th scope="col" class="num">ลงทะเบียน</th><th scope="col" class="num">ถอน</th><th scope="col" class="num"></th></tr></thead>
            <tbody>
            <?php foreach ($sections as $g): ?>
                <tr><td><b>กลุ่ม <?= Security::escape((string)$g['section_no']) ?></b></td><td><?= Security::escape((string)($g['staff_names'] ?? '—')) ?></td>
                    <td class="num"><?= (int)$g['enrolled'] ?></td><td class="num"><?= (int)$g['dropped'] ?></td>
                    <td class="num"><a class="btn btn--ghost btn--sm" href="<?= Security::escape($tabUrl('stu', ['section_no' => (string)$g['section_no']])) ?>">ดูนิสิตในกลุ่ม</a></td></tr>
            <?php endforeach; ?>
            </tbody></table></div>
        <?php endif; ?>
        <p class="legend">นิสิตเข้ากลุ่มเรียนโดยงานวิชาการ/แอดมิน (หน้าลงทะเบียนนิสิตในรายวิชา)</p>
    </section>

<?php /* ==================== แท็บ: นิสิต (ค้นหา / กรองตามสถานะและกลุ่ม · กรองในหน้า) ==================== */ ?>
<?php elseif ($tab === 'stu'): ?>
<?php
    $sf = ['q' => trim((string)($_GET['q'] ?? '')), 'section_no' => (string)($_GET['section_no'] ?? ''), 'status' => (string)($_GET['status'] ?? '')];
    $rows = array_values(array_filter($students, fn ($s) =>
        ($sf['q'] === '' || mb_stripos($s['student_code'] . ' ' . $s['display_name'], $sf['q']) !== false)
        && ($sf['section_no'] === '' || (string)$s['section_no'] === $sf['section_no'])
        && ($sf['status'] === '' || $s['enrollment_status'] === $sf['status'])));
    $secNos = array_unique(array_map(fn ($s) => (string)$s['section_no'], $students)); sort($secNos);
?>
    <section class="card card--flush" aria-label="รายชื่อนิสิต">
        <form class="filters" method="get" action="<?= Security::escape($appUrl . $listPath . '/' . (int)$offering['id_course_offerings']) ?>" role="search">
            <input type="hidden" name="tab" value="stu">
            <div class="field field--search"><label class="field__label" for="sq">ค้นหา</label>
                <div class="search"><?= ui_icon('search', 20) ?><input class="input" id="sq" name="q" type="search" value="<?= Security::escape($sf['q']) ?>" placeholder="รหัสนิสิตหรือชื่อ"></div></div>
            <div class="field"><label class="field__label" for="ss">สถานะ</label>
                <select class="select" id="ss" name="status"><option value="">ทั้งหมด</option>
                <?php foreach ($enrollLabels as $v => $l): ?><option value="<?= $v ?>"<?= $sf['status'] === $v ? ' selected' : '' ?>><?= Security::escape($l) ?></option><?php endforeach; ?></select></div>
            <div class="field"><label class="field__label" for="sg">กลุ่ม</label>
                <select class="select" id="sg" name="section_no"><option value="">ทั้งหมด</option>
                <?php foreach ($secNos as $v): ?><option value="<?= Security::escape($v) ?>"<?= $sf['section_no'] === $v ? ' selected' : '' ?>><?= Security::escape($v) ?></option><?php endforeach; ?></select></div>
            <div class="btn-row"><button class="btn btn--primary" type="submit">ค้นหา</button><?= $soonBtn('ส่งออกรายชื่อ', 'file', 'btn--secondary') ?></div>
        </form>
        <?php if (!$rows): ?><?= $empty($students ? 'ไม่พบนิสิตตามตัวกรอง' : 'ยังไม่มีนิสิตในรายวิชา') ?><?php else: ?>
        <div class="table-wrap"><table class="table">
            <thead><tr><th scope="col">รหัสนิสิต</th><th scope="col">ชื่อ-สกุล</th><th scope="col">อีเมล</th><th scope="col">กลุ่ม</th><th scope="col">สถานะการลงทะเบียน</th></tr></thead>
            <tbody>
            <?php foreach ($rows as $s): ?>
                <tr><td class="code"><?= Security::escape((string)$s['student_code']) ?></td><td><?= Security::escape((string)$s['display_name']) ?></td><td><?= Security::escape((string)$s['email']) ?></td>
                    <td><?= Security::escape((string)$s['section_no']) ?></td>
                    <td><span class="badge <?= $s['enrollment_status'] === 'enrolled' ? 'badge--active' : 'badge--archived' ?>"><?= Security::escape($enrollLabels[$s['enrollment_status']] ?? (string)$s['enrollment_status']) ?></span></td></tr>
            <?php endforeach; ?>
            </tbody></table></div>
        <div class="table-foot">แสดง <?= count($rows) ?> จาก <?= count($students) ?> คน</div>
        <?php endif; ?>
    </section>

<?php /* ==================== แท็บ: หัวข้อหลัก (บท) และแนวคิดหลัก/รอง ==================== */ ?>
<?php elseif ($tab === 'topic'): ?>
    <div class="layout-main layout-main--even">
        <section class="card" aria-label="หัวข้อหลัก (บท)">
            <div class="sec-title"><?= ui_icon('book') ?><h2>หัวข้อหลัก (บท)</h2><?= $soonBtn('เพิ่มบทใหม่', 'pen') ?></div>
            <p class="muted small">ตัวเลขด้านขวาคือจำนวนข้อสอบในคลัง · ทุกคนที่สอนวิชานี้เพิ่มและแก้ได้</p>
            <?php if (!$chapters): ?><?= $empty('ยังไม่มีบท') ?><?php else: ?>
            <ul class="tree">
                <?php foreach ($chapters as $ch): ?>
                    <li><span><b>บทที่ <?= (int)$ch['chapters_no'] ?></b> <?= Security::escape((string)$ch['name']) ?></span><span class="tree__n"><?= (int)$ch['question_count'] ?> ข้อ</span></li>
                <?php endforeach; ?>
            </ul>
            <?php endif; ?>
        </section>
        <section class="card" aria-label="แนวคิดหลักและแนวคิดรอง">
            <div class="sec-title"><?= ui_icon('layers') ?><h2>แนวคิดหลัก / แนวคิดรอง</h2><?= $soonBtn('เพิ่มแนวคิด', 'pen') ?></div>
            <p class="muted small">ใช้ผูกข้อสอบและเนื้อหาการเรียน · ดูผลสอบตามแนวคิดได้ในหน้าผลสอบ</p>
            <?php if (!$concepts): ?><?= $empty('ยังไม่มีแนวคิด') ?><?php else: ?>
            <ul class="tree">
                <?php foreach ($concepts as $m): ?>
                    <li><span><b><?= Security::escape((string)$m['code']) ?></b> <?= Security::escape((string)$m['name']) ?></span><span class="tree__n"><?= (int)$m['question_count'] ?> ข้อ</span>
                        <?php if (!empty($m['subs'])): ?>
                            <ul><?php foreach ($m['subs'] as $s): ?><li><span><?= Security::escape($s['code'] . ' ' . $s['name']) ?></span><span class="tree__n"><?= (int)$s['question_count'] ?> ข้อ</span></li><?php endforeach; ?></ul>
                        <?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ul>
            <?php endif; ?>
        </section>
    </div>

<?php /* ==================== แท็บ: ผลการเรียนรู้ (CLO) ==================== */ ?>
<?php elseif ($tab === 'lo'): ?>
    <section class="card card--flush" aria-label="ผลการเรียนรู้ของรายวิชา">
        <div class="card__head"><div class="sec-title"><?= ui_icon('shield') ?><h2>ผลการเรียนรู้ของรายวิชา (CLO)</h2></div><?= $isCoordinator ? $soonBtn('เพิ่ม CLO', 'pen') : '' ?></div>
        <?php if (!$learningOutcomes): ?><?= $empty('ยังไม่มี CLO') ?><?php else: ?>
        <div class="table-wrap"><table class="table">
            <thead><tr><th scope="col">รหัส</th><th scope="col">ผลการเรียนรู้</th><th scope="col" class="num">ข้อสอบที่วัด</th></tr></thead>
            <tbody>
            <?php foreach ($learningOutcomes as $l): ?>
                <tr><td class="code"><?= Security::escape((string)$l['code']) ?></td><td><?= Security::escape((string)$l['description']) ?></td><td class="num"><?= (int)$l['question_count'] ?> ข้อ</td></tr>
            <?php endforeach; ?>
            </tbody></table></div>
        <?php endif; ?>
    </section>

<?php /* ==================== แท็บ: เนื้อหาการเรียน ==================== */ ?>
<?php elseif ($tab === 'content'): ?>
    <section class="card card--flush" aria-label="เนื้อหาการเรียน">
        <div class="card__head"><div class="sec-title"><?= ui_icon('video') ?><h2>เนื้อหาการเรียน (<?= count($contents) ?>)</h2></div><p>นิสิตเห็นเฉพาะที่เผยแพร่แล้ว</p><?= $soonBtn('เพิ่มเนื้อหา', 'pen', 'btn--primary btn--sm') ?></div>
        <?php if (!$contents): ?><?= $empty('ยังไม่มีเนื้อหาการเรียน', 'เพิ่มไฟล์ วิดีโอ หรือลิงก์ให้นิสิตได้') ?><?php else: ?>
        <div class="table-wrap"><table class="table">
            <thead><tr><th scope="col">เนื้อหา</th><th scope="col">ประเภท</th><th scope="col" class="num">ไฟล์</th><th scope="col">แก้ไขล่าสุด</th><th scope="col">สถานะ</th></tr></thead>
            <tbody>
            <?php foreach ($contents as $x): ?>
                <tr><td><b><?= Security::escape((string)$x['title']) ?></b><?php if (!empty($x['description'])): ?><span class="exam-sub"><?= Security::escape((string)$x['description']) ?></span><?php endif; ?></td>
                    <td><span class="tag tag--quiz"><?= Security::escape($contentTypes[$x['content_type']] ?? (string)$x['content_type']) ?></span></td>
                    <td class="num"><?= (int)($x['attachment_count'] ?? 0) ?></td>
                    <td class="nowrap"><?= Security::escape(thai_date((string)($x['updated_at'] ?? ''), false)) ?></td>
                    <td><span class="badge <?= $x['status'] === 'published' ? 'badge--active' : ($x['status'] === 'draft' ? 'badge--exam-draft' : 'badge--archived') ?>"><?= Security::escape($contentStatus[$x['status']] ?? (string)$x['status']) ?></span></td></tr>
            <?php endforeach; ?>
            </tbody></table></div>
        <?php endif; ?>
    </section>

<?php /* ==================== แท็บ: ข้อสอบของวิชานี้ (กรองตามบท) ==================== */ ?>
<?php elseif ($tab === 'bank'): ?>
<?php
    $bch = (string)($_GET['chapter'] ?? '');
    $rows = array_values(array_filter($questions, fn ($q) => $bch === '' || (string)$q['chapters_no'] === $bch));
    $cnt = ['published' => 0, 'draft' => 0, 'archived' => 0];
    foreach ($questions as $q) { if (isset($cnt[$q['status']])) { $cnt[$q['status']]++; } }
?>
    <section class="card card--flush" aria-label="ข้อสอบของวิชานี้">
        <form class="toolbar" method="get" action="<?= Security::escape($appUrl . $listPath . '/' . (int)$offering['id_course_offerings']) ?>">
            <input type="hidden" name="tab" value="bank">
            <div class="field field--sm"><label class="field__label" for="bch">บท</label>
                <select class="select" id="bch" name="chapter" onchange="this.form.submit()"><option value="">ทุกบท</option>
                <?php foreach ($chapters as $ch): ?><option value="<?= (int)$ch['chapters_no'] ?>"<?= $bch === (string)(int)$ch['chapters_no'] ? ' selected' : '' ?>>บทที่ <?= (int)$ch['chapters_no'] ?> <?= Security::escape((string)$ch['name']) ?></option><?php endforeach; ?></select></div>
            <noscript><button class="btn btn--secondary" type="submit">กรอง</button></noscript>
            <p class="muted grow">เผยแพร่แล้ว <?= $cnt['published'] ?> · ร่าง <?= $cnt['draft'] ?> · เก็บถาวร <?= $cnt['archived'] ?></p>
            <a class="btn btn--primary" href="<?= Security::escape($appUrl . '/questions/create?courses_id=' . (int)$offering['courses_id']) ?>"><?= ui_icon('pen', 18) ?>เพิ่มข้อสอบในวิชานี้</a>
        </form>
        <?php if (!$rows): ?><?= $empty('ยังไม่มีข้อสอบ' . ($bch !== '' ? 'ในบทนี้' : 'ของวิชานี้')) ?><?php else: ?>
        <div class="table-wrap"><table class="table">
            <thead><tr><th scope="col">รหัส</th><th scope="col">คำถาม</th><th scope="col">บท</th><th scope="col">ระดับ</th><th scope="col">สถานะ</th></tr></thead>
            <tbody>
            <?php foreach ($rows as $q): ?>
                <tr><td class="q-code"><a href="<?= Security::escape($appUrl . '/questions/' . (int)$q['id_questions']) ?>">Q-<?= str_pad((string)(int)$q['id_questions'], 4, '0', STR_PAD_LEFT) ?></a></td>
                    <td><span class="q-stem"><?= Security::escape((string)$q['text']) ?></span></td>
                    <td class="nowrap"><?= $q['chapters_no'] !== null ? 'บทที่ ' . (int)$q['chapters_no'] : '—' ?></td>
                    <td class="nowrap"><?= Security::escape((string)($q['bloom_name_th'] ?? '')) ?></td>
                    <td><span class="badge badge--<?= Security::escape((string)$q['status']) ?>"><?= Security::escape($qStatus[$q['status']] ?? (string)$q['status']) ?></span></td></tr>
            <?php endforeach; ?>
            </tbody></table></div>
        <?php endif; ?>
    </section>

<?php /* ==================== แท็บ: ชุดสอบของวิชานี้ ==================== */ ?>
<?php elseif ($tab === 'ex'): ?>
    <section class="card card--flush" aria-label="ชุดสอบของวิชานี้">
        <div class="card__head"><div class="sec-title"><?= ui_icon('clip') ?><h2>ภาค <?= (int)$offering['term_no'] ?>/<?= (int)$offering['academic_year'] ?> · <?= count($exams) ?> ชุดสอบ</h2></div>
            <a class="btn btn--primary btn--sm" href="<?= Security::escape($appUrl) ?>/exams/create?new=1"><?= ui_icon('clip', 18) ?>สร้างชุดสอบของวิชานี้</a></div>
        <?php if (!$exams): ?><?= $empty('ยังไม่มีชุดสอบของวิชานี้') ?><?php else: ?>
        <div class="table-wrap"><table class="table">
            <thead><tr><th scope="col">ชุดสอบ</th><th scope="col">เปิด–ปิด</th><th scope="col">สถานะ</th><th scope="col" class="num">ส่งคำตอบ</th><th scope="col" class="num">เฉลี่ย</th></tr></thead>
            <tbody>
            <?php foreach ($exams as $x): ?>
                <?php $st = ($x['status'] === 'published' && $x['open_at'] <= $now && $now <= $x['close_at']) ? 'live' : (string)$x['status']; ?>
                <tr><td><b><?= Security::escape((string)$x['title']) ?></b></td>
                    <td class="exam-when"><?= Security::escape(thai_date((string)$x['open_at'])) ?><small>ถึง <?= Security::escape(thai_date((string)$x['close_at'])) ?></small></td>
                    <td><span class="badge badge--exam-<?= Security::escape($st) ?>"><?= Security::escape($examStatus[$st] ?? $st) ?></span></td>
                    <td class="num"><?= (int)($x['submitted_count'] ?? 0) ?>/<?= (int)($x['eligible_count'] ?? 0) ?></td>
                    <td class="num"><?= $x['avg_score'] !== null && $x['avg_score'] !== '' ? Security::escape((string)$x['avg_score']) : '—' ?></td></tr>
            <?php endforeach; ?>
            </tbody></table></div>
        <?php endif; ?>
    </section>
<?php endif; ?>
<?php endif; ?>
<?php require dirname(__DIR__) . '/includes/footer.php'; ?>
