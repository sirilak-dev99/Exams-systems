<?php
/**
 * ============================================================
 *  หน้า        : จัดการรายวิชา (รายวิชาที่อาจารย์สอน)
 *  Route       : GET /my-courses → TeachingController@myCourses
 *  สิทธิ์       : อาจารย์, แอดมิน
 *  แบบหน้าจอ   : prototype Course.html
 * ------------------------------------------------------------
 *  ข้อมูลที่ได้รับ (TeachingModel::getTeachingOfferings)
 *    $offerings  รายวิชาที่เปิดสอนซึ่งผู้ใช้เป็นผู้สอน (section_staff)
 *                [id_course_offerings, courses_id, code, name, programs_id, program_name,
 *                 academic_year, term_no, section_nos ('01, 02'), student_count, status, my_role]
 *                status  : open = เปิดสอน · completed = สิ้นสุดภาค · closed = ปิดแล้ว
 *                my_role : coordinator (ผู้ประสานงาน · เห็นปุ่มแก้ไขรายวิชา) | teacher | assistant
 *    $detailRoutes  true = ลิงก์ไปหน้ารายละเอียดได้
 *  ตัวกรอง (GET): q, programs_id, academic_year, term_no, section_no
 * ============================================================
 */
$pageTitle  = 'จัดการรายวิชา';
$offerings  = $offerings ?? [];
$appUrl     = rtrim((string)env('APP_URL', ''), '/');
// ยังไม่มีหน้ารายละเอียดรายวิชา (CourseView / CourseViewStudents / CourseDetail) — ปุ่มแสดงเป็น "เร็วๆ นี้"
$detailRoutes = $detailRoutes ?? false;
require dirname(__DIR__) . '/includes/header.php';

$statusLabels = ['open' => 'เปิดสอน', 'completed' => 'สิ้นสุดภาค', 'closed' => 'ปิดแล้ว'];

// ---------- ค่าตัวกรองจาก URL ----------
$f = [
    'q'             => trim((string)($_GET['q'] ?? '')),
    'programs_id'   => (string)($_GET['programs_id'] ?? ''),
    'academic_year' => (string)($_GET['academic_year'] ?? ''),
    'term_no'       => (string)($_GET['term_no'] ?? ''),
    'section_no'    => (string)($_GET['section_no'] ?? ''),
];
$filtered = implode('', $f) !== '';
$sel = fn (string $key, string $value) => $f[$key] === $value ? ' selected' : '';

// ตัวเลือกของตัวกรองสร้างจากข้อมูลที่มี (ไม่ต้องส่งรายการแยก)
// ---------- ตัวเลือกของตัวกรอง สร้างจากข้อมูลที่มี (Controller ไม่ต้องส่งแยก) ----------
$programs = [];
$years = [];
$sectionNos = [];
foreach ($offerings as $o) {
    $programs[(string)$o['programs_id']] = (string)$o['program_name'];
    $years[(string)$o['academic_year']] = true;
    foreach (array_filter(array_map('trim', explode(',', (string)($o['section_nos'] ?? '')))) as $s) {
        $sectionNos[$s] = true;
    }
}
krsort($years);
ksort($sectionNos);

// ---------- กรองรายการตามตัวกรอง (ทำในหน้า เพราะรายการต่ออาจารย์มีไม่มาก) ----------
$total = count($offerings);
$rows = array_values(array_filter($offerings, function ($o) use ($f) {
    $sections = array_map('trim', explode(',', (string)($o['section_nos'] ?? '')));
    return ($f['q'] === '' || mb_stripos($o['code'] . ' ' . $o['name'], $f['q']) !== false)
        && ($f['programs_id'] === '' || (string)$o['programs_id'] === $f['programs_id'])
        && ($f['academic_year'] === '' || (string)$o['academic_year'] === $f['academic_year'])
        && ($f['term_no'] === '' || (string)$o['term_no'] === $f['term_no'])
        && ($f['section_no'] === '' || in_array($f['section_no'], $sections, true));
}));

/** ปุ่มไอคอน — ยังไม่มี route ปลายทาง แสดงเป็นปุ่มจาง "เร็วๆ นี้" แทนลิงก์ไปหน้า 404 */
// ---------- ปุ่มไอคอนในคอลัมน์จัดการ ----------
$iconLink = function (string $path, string $icon, string $title, string $what) use ($appUrl, $detailRoutes): string {
    if ($detailRoutes) {
        return '<a class="icon-btn" href="' . Security::escape($appUrl . $path) . '" title="' . Security::escape($title) . '" aria-label="' . Security::escape($title . ' ' . $what) . '">' . ui_icon($icon, 20) . '</a>';
    }
    return '<span class="icon-btn is-off" title="' . Security::escape($title) . ' · เร็วๆ นี้" aria-disabled="true">' . ui_icon($icon, 20) . '</span>';
};
$soonItem = fn (string $label, string $icon) => '<span>' . ui_icon($icon, 18) . Security::escape($label) . '<span class="badge badge--plain">เร็วๆ นี้</span></span>';
?>
<?php /* ==================== หัวหน้า ==================== */ ?>
<div class="page-head">
    <div class="page-head__text">
        <h1>จัดการรายวิชา</h1>
        <p>ดูแลรายวิชาที่ท่านรับผิดชอบ และจัดการข้อมูลที่เกี่ยวข้อง</p>
    </div>
</div>

<?php /* ==================== ตัวกรอง (ฟอร์ม GET · กรองในหน้า) ==================== */ ?>
<section class="card card--flush">
    <form class="filters" method="get" action="<?= Security::escape($appUrl . ($listPath ?? '/my-courses')) ?>" role="search">
        <div class="field field--search">
            <label class="field__label" for="q">ค้นหา</label>
            <div class="search"><?= ui_icon('search', 20) ?><input class="input" id="q" name="q" type="search" value="<?= Security::escape($f['q']) ?>" placeholder="ค้นหารหัสวิชาหรือชื่อรายวิชา"></div>
        </div>
        <div class="field">
            <label class="field__label" for="f-program">หลักสูตร</label>
            <select class="select" id="f-program" name="programs_id">
                <option value="">ทั้งหมด</option>
                <?php foreach ($programs as $id => $name): ?>
                    <option value="<?= Security::escape((string)$id) ?>"<?= $sel('programs_id', (string)$id) ?>><?= Security::escape($name) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="field">
            <label class="field__label" for="f-year">ปีการศึกษา</label>
            <select class="select" id="f-year" name="academic_year">
                <option value="">ทั้งหมด</option>
                <?php foreach (array_keys($years) as $y): ?>
                    <option value="<?= Security::escape((string)$y) ?>"<?= $sel('academic_year', (string)$y) ?>><?= Security::escape((string)$y) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="field">
            <label class="field__label" for="f-term">ภาคการศึกษา</label>
            <select class="select" id="f-term" name="term_no">
                <option value="">ทั้งหมด</option>
                <?php foreach ([1, 2, 3] as $t): ?>
                    <option value="<?= $t ?>"<?= $sel('term_no', (string)$t) ?>>ภาค <?= $t === 3 ? 'ฤดูร้อน' : $t ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="field">
            <label class="field__label" for="f-section">กลุ่มเรียน</label>
            <select class="select" id="f-section" name="section_no">
                <option value="">ทั้งหมด</option>
                <?php foreach (array_keys($sectionNos) as $s): ?>
                    <option value="<?= Security::escape((string)$s) ?>"<?= $sel('section_no', (string)$s) ?>><?= Security::escape((string)$s) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="btn-row">
            <button class="btn btn--primary" type="submit">ค้นหา</button>
            <?php if ($filtered): ?><a class="btn btn--ghost" href="<?= Security::escape($appUrl . ($listPath ?? '/my-courses')) ?>">ล้างตัวกรอง</a><?php endif; ?>
        </div>
    </form>
</section>

<?php /* ==================== ตารางรายการรายวิชาของอาจารย์ ==================== */ ?>
<section class="card card--flush card--menus" aria-labelledby="list-title">
    <div class="card__head">
        <div class="sec-title"><?= ui_icon('book') ?><h2 id="list-title">รายการรายวิชาของอาจารย์</h2></div>
        <p>พบทั้งหมด <?= count($rows) ?> รายวิชา</p>
    </div>

    <?php if (empty($rows)): ?>
        <div class="empty">
            <?= ui_icon('inbox', 40) ?>
            <b><?= $filtered ? 'ไม่พบรายวิชาตามตัวกรอง' : 'ยังไม่มีรายวิชาที่ท่านสอน' ?></b>
            <span><?= $filtered ? 'ลองเปลี่ยนตัวกรอง หรือกดล้างตัวกรอง' : 'รายวิชาจะแสดงเมื่อฝ่ายวิชาการกำหนดให้ท่านเป็นผู้สอนในกลุ่มเรียน' ?></span>
        </div>
    <?php else: ?>
        <div class="table-wrap">
            <table class="table">
                <caption class="sr-only">รายการรายวิชาของอาจารย์</caption>
                <thead>
                    <tr>
                        <th scope="col">รหัสวิชา</th>
                        <th scope="col">ชื่อรายวิชา</th>
                        <th scope="col">หลักสูตร</th>
                        <th scope="col">ปีการศึกษา / ภาค</th>
                        <th scope="col">กลุ่มเรียน</th>
                        <th scope="col" class="num">จำนวนนิสิต</th>
                        <th scope="col">สถานะ</th>
                        <th scope="col" class="num">จัดการ</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($rows as $o): ?>
                        <?php
                            $oid = (int)$o['id_course_offerings'];
                            $what = (string)$o['code'];
                            $st = (string)$o['status'];
                        ?>
                        <tr>
                            <td class="code nowrap"><?= Security::escape((string)$o['code']) ?></td>
                            <td>
                                <?php if ($detailRoutes): ?>
                                    <a class="exam-title" href="<?= Security::escape($appUrl . '/my-courses/' . $oid) ?>"><?= Security::escape((string)$o['name']) ?></a>
                                <?php else: ?>
                                    <b><?= Security::escape((string)$o['name']) ?></b>
                                <?php endif; ?>
                                <?php if (($o['my_role'] ?? '') === 'coordinator'): ?><span class="exam-sub">ผู้ประสานงานรายวิชา</span><?php endif; ?>
                            </td>
                            <td><?= Security::escape((string)$o['program_name']) ?></td>
                            <td class="nowrap"><?= (int)$o['term_no'] ?>/<?= (int)$o['academic_year'] ?></td>
                            <td class="nowrap"><?= Security::escape((string)($o['section_nos'] ?? '—')) ?></td>
                            <td class="num"><?= (int)($o['student_count'] ?? 0) ?></td>
                            <td><span class="badge badge--offering-<?= Security::escape($st) ?>"><?= Security::escape($statusLabels[$st] ?? $st) ?></span></td>
                            <td class="num nowrap">
                                <?= $iconLink('/my-courses/' . $oid, 'file', 'ดูรายละเอียด', $what) ?>
                                <?= $iconLink('/my-courses/' . $oid . '?tab=stu', 'users', 'รายชื่อนิสิต', $what) ?>
                                <?php if (($o['my_role'] ?? '') === 'coordinator'): ?>
                                    <span class="icon-btn is-off" title="แก้ไขรายวิชา (ผู้ประสานงาน) · เร็วๆ นี้" aria-disabled="true"><?= ui_icon('pen', 20) ?></span>
                                <?php else: ?>
                                    <span class="icon-btn" aria-hidden="true"></span>
                                <?php endif; ?>
                                <details class="more">
                                    <summary class="icon-btn" title="เมนูเพิ่มเติม" aria-label="เมนูเพิ่มเติม <?= Security::escape($what) ?>">⋯</summary>
                                    <div class="more__menu">
                                        <?= $soonItem('ข้อสอบของวิชานี้', 'bank') ?>
                                        <?= $soonItem('ชุดสอบของวิชานี้', 'clip') ?>
                                        <?= $soonItem('เนื้อหาการเรียน', 'video') ?>
                                        <?= $soonItem('ส่งออกรายชื่อนิสิต', 'file') ?>
                                    </div>
                                </details>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <div class="table-foot">แสดง <?= count($rows) ?> จาก <?= $total ?> รายการ</div>
    <?php endif; ?>
    <div class="legend">
        ปุ่มในคอลัมน์จัดการ:
        <span><?= ui_icon('file', 16) ?>ดูรายละเอียด</span>
        <span><?= ui_icon('users', 16) ?>รายชื่อนิสิต</span>
        <span><?= ui_icon('pen', 16) ?>แก้ไขรายวิชา (ผู้ประสานงาน)</span>
        <span>⋯ เมนูอื่น ๆ</span>
    </div>
</section>
<?php require dirname(__DIR__) . '/includes/footer.php'; ?>
