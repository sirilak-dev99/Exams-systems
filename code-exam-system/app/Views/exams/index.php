<?php
/**
 * ============================================================
 *  หน้า        : จัดการชุดสอบ (รายการชุดสอบ)
 *  สถานะ       : view พร้อมใช้ — รอ backend เพิ่ม route/Controller (ร่างเก็บไว้ที่ branch draft/teaching-backend)
 *  Route       : GET /exams → TeachingController@examIndex
 *  สิทธิ์       : อาจารย์ (เห็นชุดที่ตัวเองสร้าง), แอดมิน (เห็นทั้งหมด)
 *  แบบหน้าจอ   : prototype Exams.html
 * ------------------------------------------------------------
 *  ข้อมูลที่ได้รับ (TeachingModel::getExamList)
 *    $exams         [id_exams, title, kind, mode, status, open_at, close_at, duration_min,
 *                    course_code, course_name, term_label ('1/2569'), section_nos ('01, 02')]
 *    $courses       รายวิชาสำหรับตัวกรอง [id_courses, code, name]
 *    $terms         ภาคการศึกษาสำหรับตัวกรอง [id_academic_terms, academic_year, term_no]
 *    $statusCounts  จำนวนตามสถานะ ['all', 'draft', 'published', 'closed']
 *    $total         จำนวนทั้งหมดก่อนกรอง
 *  ตัวกรอง (GET): courses_id, academic_terms_id, kind, status, q
 *
 *  สถานะ "กำลังสอบ" ไม่ได้เก็บใน DB — คำนวณในหน้า: status = published และตอนนี้อยู่ระหว่าง open_at ถึง close_at
 *  ตัวแปรเสริม: $filterInView (กรองในหน้าเอง) · $detailRoutes (มีหน้ารายละเอียดชุดสอบหรือยัง)
 *              $listPath / $createHref (path ของหน้านี้และปุ่มสร้าง)
 * ============================================================
 */
$exams        = $exams ?? [];
$courses      = $courses ?? [];
$terms        = $terms ?? [];
$statusCounts = $statusCounts ?? null;
$now          = $now ?? date('Y-m-d H:i:s');
$listPath     = $listPath ?? '/exams';
$createHref   = $createHref ?? '/exams/create?new=1';
$filterInView = $filterInView ?? false;
$detailRoutes = $detailRoutes ?? true;
$appUrl       = rtrim((string)env('APP_URL', ''), '/');
$pageTitle    = 'จัดการชุดสอบ';
require dirname(__DIR__) . '/includes/header.php';

// ข้อความตาม prototype Exams.html
$kindLabels   = ['midterm' => 'กลางภาค', 'final' => 'ปลายภาค', 'quiz' => 'Quiz เก็บคะแนน', 'pre_post' => 'Pre/Post-test'];
$statusLabels = ['draft' => 'ฉบับร่าง', 'published' => 'เผยแพร่', 'live' => 'กำลังสอบ', 'closed' => 'ปิดการสอบ', 'archived' => 'เก็บถาวร'];

// ---------- ค่าตัวกรองจาก URL ----------
$f = [
    'courses_id'        => (string)($_GET['courses_id'] ?? ''),
    'academic_terms_id' => (string)($_GET['academic_terms_id'] ?? ''),
    'kind'              => (string)($_GET['kind'] ?? ''),
    'status'            => (string)($_GET['status'] ?? ''),
    'q'                 => trim((string)($_GET['q'] ?? '')),
];
$filtered = implode('', $f) !== '';
$sel = fn (string $key, string $value) => $f[$key] === $value ? ' selected' : '';

if ($filterInView) {
    // นับจากรายการทั้งหมดของผู้สร้าง แล้วกรองตามตัวกรองที่ข้อมูลมีให้ (สถานะ ประเภท ชื่อ)
    if ($statusCounts === null) {
        $statusCounts = ['all' => count($exams), 'draft' => 0, 'published' => 0, 'closed' => 0];
        foreach ($exams as $e) {
            if (isset($statusCounts[$e['status']])) {
                $statusCounts[$e['status']]++;
            }
        }
    }
    $total = count($exams);
    $exams = array_values(array_filter($exams, fn ($e) =>
        ($f['status'] === '' || $e['status'] === $f['status']) &&
        ($f['kind'] === '' || $e['kind'] === $f['kind']) &&
        ($f['q'] === '' || mb_stripos((string)$e['title'], $f['q']) !== false)));
}
$total = $total ?? count($exams);

/** สถานะที่แสดง: published ที่อยู่ในช่วงเวลาสอบ = กำลังสอบ */
$displayStatus = function (array $e) use ($now): string {
    if ($e['status'] === 'published' && !empty($e['open_at']) && !empty($e['close_at']) && $e['open_at'] <= $now && $now <= $e['close_at']) {
        return 'live';
    }
    return (string)$e['status'];
};
/** วันเวลาเปิดสอบ: วันนี้ → "วันนี้ 09:00–10:30", ไม่มี → "ยังไม่ตั้งเวลา" */
$whenText = function (array $e) use ($now): array {
    if (empty($e['open_at'])) {
        return ['ยังไม่ตั้งเวลา', ''];
    }
    $open = (string)$e['open_at'];
    $close = (string)($e['close_at'] ?? '');
    if (substr($open, 0, 10) === substr($now, 0, 10)) {
        $main = 'วันนี้ ' . substr($open, 11, 5) . (substr($close, 0, 10) === substr($open, 0, 10) ? '–' . substr($close, 11, 5) : '');
        return [$main, substr($close, 0, 10) !== substr($open, 0, 10) && $close !== '' ? 'ปิดรับ ' . thai_date($close) : ''];
    }
    return [thai_date($open), $close !== '' ? 'ปิดรับ ' . thai_date($close) : ''];
};
/** ปุ่มไอคอนในคอลัมน์จัดการ — ถ้ายังไม่มี route หน้ารายละเอียด แสดงเป็นปุ่มจาง "เร็วๆ นี้" แทนลิงก์ไปหน้า 404 */
// ---------- ฟังก์ชันช่วยแสดงผล ----------
$iconLink = function (string $path, string $icon, string $title, string $examTitle) use ($appUrl, $detailRoutes): string {
    if ($detailRoutes) {
        return '<a class="icon-btn" href="' . Security::escape($appUrl . $path) . '" title="' . Security::escape($title) . '" aria-label="' . Security::escape($title . ' ' . $examTitle) . '">' . ui_icon($icon, 20) . '</a>';
    }
    return '<span class="icon-btn is-off" title="' . Security::escape($title) . ' · เร็วๆ นี้" aria-disabled="true">' . ui_icon($icon, 20) . '</span>';
};
$soonItem = fn (string $label, string $icon) => '<span>' . ui_icon($icon, 18) . Security::escape($label) . '<span class="badge badge--plain">เร็วๆ นี้</span></span>';
?>
<?php /* ==================== หัวหน้า + ปุ่มสร้างชุดสอบ ==================== */ ?>
<div class="page-head">
    <div class="page-head__text">
        <h1>จัดการชุดสอบ</h1>
        <p>สร้าง กำหนดเวลา และติดตามการสอบในรายวิชาที่รับผิดชอบ</p>
    </div>
    <div class="btn-row">
        <a class="btn btn--primary" href="<?= Security::escape($appUrl . $createHref) ?>"><?= ui_icon('clip', 20) ?>สร้างชุดสอบ</a>
        <span class="btn btn--secondary" aria-disabled="true" title="เร็วๆ นี้ — ยังไม่มี route"><?= ui_icon('layers', 20) ?>คัดลอกจากชุดสอบเดิม</span>
    </div>
</div>

<?php /* ==================== ตัวกรอง ==================== */ ?>
<section class="card card--flush">
    <form class="filters" method="get" action="<?= Security::escape($appUrl . $listPath) ?>" role="search">
        <?php /* ตัวกรองรายวิชา/ภาค แสดงเมื่อ Controller ส่งรายการมา (ExamController@create ตอนนี้ยังไม่ส่ง) */ ?>
        <?php if ($courses): ?>
        <div class="field">
            <label class="field__label" for="f-course">รายวิชา</label>
            <select class="select" id="f-course" name="courses_id">
                <option value="">ทั้งหมด</option>
                <?php foreach ($courses as $c): ?>
                    <option value="<?= (int)$c['id_courses'] ?>"<?= $sel('courses_id', (string)(int)$c['id_courses']) ?>><?= Security::escape($c['code'] . ' ' . $c['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <?php endif; ?>
        <?php if ($terms): ?>
        <div class="field">
            <label class="field__label" for="f-term">ภาคการศึกษา</label>
            <select class="select" id="f-term" name="academic_terms_id">
                <option value="">ทั้งหมด</option>
                <?php foreach ($terms as $t): ?>
                    <option value="<?= (int)$t['id_academic_terms'] ?>"<?= $sel('academic_terms_id', (string)(int)$t['id_academic_terms']) ?>><?= (int)$t['term_no'] ?>/<?= (int)$t['academic_year'] ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <?php endif; ?>
        <div class="field">
            <label class="field__label" for="f-kind">ประเภทการสอบ</label>
            <select class="select" id="f-kind" name="kind">
                <option value="">ทั้งหมด</option>
                <?php foreach ($kindLabels as $value => $label): ?>
                    <option value="<?= $value ?>"<?= $sel('kind', $value) ?>><?= Security::escape($label) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="field">
            <label class="field__label" for="f-status">สถานะ</label>
            <select class="select" id="f-status" name="status">
                <option value="">ทั้งหมด</option>
                <?php foreach (['draft', 'published', 'closed', 'archived'] as $value): ?>
                    <option value="<?= $value ?>"<?= $sel('status', $value) ?>><?= Security::escape($statusLabels[$value]) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="field field--search">
            <label class="field__label" for="q">ค้นหา</label>
            <div class="search"><?= ui_icon('search', 20) ?><input class="input" id="q" name="q" type="search" value="<?= Security::escape($f['q']) ?>" placeholder="ค้นหาชื่อชุดสอบ"></div>
        </div>
        <div class="btn-row">
            <button class="btn btn--primary" type="submit">ค้นหา</button>
            <?php if ($filtered): ?><a class="btn btn--ghost" href="<?= Security::escape($appUrl . $listPath) ?>">ล้างตัวกรอง</a><?php endif; ?>
        </div>
    </form>
</section>

<?php /* ==================== ปุ่มสรุปจำนวนตามสถานะ (กดแล้วกรอง) ==================== */ ?>
<?php if ($statusCounts !== null): ?>
    <nav class="stats" aria-label="จำนวนชุดสอบตามสถานะ">
        <?php foreach (['' => ['ชุดสอบทั้งหมด', 'clip'], 'draft' => ['ฉบับร่าง', 'pen'], 'published' => ['เผยแพร่', 'play'], 'closed' => ['ปิดการสอบ', 'shield']] as $st => [$label, $icon]): ?>
            <a class="stat" href="<?= Security::escape($appUrl . $listPath . ($st !== '' ? '?status=' . $st : '')) ?>"<?= $f['status'] === $st ? ' aria-current="page"' : '' ?>>
                <span class="stat__icon"><?= ui_icon($icon, 18) ?></span><?= Security::escape($label) ?> <b><?= (int)($statusCounts[$st === '' ? 'all' : $st] ?? 0) ?></b> ชุด
            </a>
        <?php endforeach; ?>
    </nav>
<?php endif; ?>

<?php /* ==================== ตารางรายการชุดสอบ ==================== */ ?>
<section class="card card--flush card--menus" aria-labelledby="list-title">
    <div class="card__head"><div class="sec-title"><?= ui_icon('clip') ?><h2 id="list-title">รายการชุดสอบ</h2></div></div>

    <?php if (empty($exams)): ?>
        <div class="empty">
            <?= ui_icon('inbox', 40) ?>
            <b><?= $filtered ? 'ไม่พบชุดสอบตามตัวกรอง' : 'ยังไม่มีชุดสอบ' ?></b>
            <span><?= $filtered ? 'ลองเปลี่ยนตัวกรอง หรือกดล้างตัวกรอง' : 'เริ่มจากปุ่ม "สร้างชุดสอบ" ด้านบน' ?></span>
        </div>
    <?php else: ?>
        <div class="table-wrap">
            <table class="table">
                <caption class="sr-only">รายการชุดสอบ</caption>
                <thead>
                    <tr>
                        <th scope="col">ชื่อชุดสอบ</th>
                        <th scope="col">รายวิชา</th>
                        <th scope="col">ประเภทการสอบ</th>
                        <th scope="col">วันและเวลาเปิดสอบ</th>
                        <th scope="col" class="num">ระยะเวลา</th>
                        <th scope="col">สถานะ</th>
                        <th scope="col" class="num">จัดการ</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($exams as $e): ?>
                        <?php
                            $id = (int)$e['id_exams'];
                            $st = $displayStatus($e);
                            [$when, $whenSub] = $whenText($e);
                        ?>
                        <tr>
                            <td>
                                <?php if ($detailRoutes): ?>
                                    <a class="exam-title" href="<?= Security::escape($appUrl . '/exams/' . $id) ?>"><?= Security::escape((string)$e['title']) ?></a>
                                <?php else: ?>
                                    <b><?= Security::escape((string)$e['title']) ?></b>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php $courseText = trim(($e['course_code'] ?? '') . ' ' . ($e['course_name'] ?? '')); ?>
                                <?= $courseText !== '' ? Security::escape($courseText) : '<span class="muted">—</span>' ?>
                                <span class="exam-sub"><?= !empty($e['section_nos']) ? 'กลุ่ม ' . Security::escape((string)$e['section_nos']) : 'ยังไม่กำหนดกลุ่มเรียน' ?><?= !empty($e['term_label']) ? ' · ' . Security::escape((string)$e['term_label']) : '' ?></span>
                            </td>
                            <td><span class="tag tag--<?= Security::escape((string)$e['kind']) ?>"><?= Security::escape($kindLabels[$e['kind']] ?? (string)$e['kind']) ?></span></td>
                            <td class="exam-when"><?= Security::escape($when) ?><?php if ($whenSub !== ''): ?><small><?= Security::escape($whenSub) ?></small><?php endif; ?></td>
                            <td class="num nowrap"><?= (int)$e['duration_min'] ?> นาที</td>
                            <td><span class="badge badge--exam-<?= Security::escape($st) ?>"><?= Security::escape($statusLabels[$st] ?? $st) ?></span></td>
                            <td class="num nowrap">
                                <?= $iconLink('/exams/' . $id, 'file', 'ดูรายละเอียด', (string)$e['title']) ?>
                                <?php if ($e['status'] === 'draft'): ?>
                                    <?= $iconLink('/exams/' . $id . '/edit', 'pen', 'แก้ไขชุดสอบ', (string)$e['title']) ?>
                                <?php elseif ($e['status'] === 'published'): ?>
                                    <?= $iconLink('/exams/' . $id, 'calendar', 'แก้ไขได้เฉพาะเวลา (เผยแพร่แล้ว)', (string)$e['title']) ?>
                                <?php else: ?>
                                    <?php /* ปิดการสอบ / เก็บถาวร: แก้ไขไม่ได้ — เว้นที่ไว้ให้ปุ่มตรงแนว */ ?>
                                    <span class="icon-btn" aria-hidden="true"></span>
                                <?php endif; ?>
                                <details class="more">
                                    <summary class="icon-btn" title="เมนูเพิ่มเติม" aria-label="เมนูเพิ่มเติม <?= Security::escape((string)$e['title']) ?>">⋯</summary>
                                    <div class="more__menu">
                                        <?php if ($st === 'live'): ?><?= $soonItem('คุมสอบแบบเรียลไทม์', 'monitor') ?><?php endif; ?>
                                        <?php if (in_array($st, ['closed', 'archived'], true)): ?><?= $soonItem('ดูผลสอบ', 'chart') ?><?php endif; ?>
                                        <?= $soonItem('บันทึกคำตอบจากกระดาษ', 'pen') ?>
                                        <?= $soonItem('คัดลอกเป็นชุดใหม่', 'layers') ?>
                                    </div>
                                </details>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <div class="table-foot">แสดง <?= count($exams) ?> จาก <?= (int)$total ?> รายการ</div>
    <?php endif; ?>
    <div class="legend">
        ปุ่มในคอลัมน์จัดการ:
        <span><?= ui_icon('file', 16) ?>ดูรายละเอียด</span>
        <span><?= ui_icon('pen', 16) ?>แก้ไข (เฉพาะฉบับร่าง)</span>
        <span>⋯ คุมสอบ · บันทึกคำตอบกระดาษ · ดูผลสอบ · คัดลอก</span>
    </div>
</section>
<?php require dirname(__DIR__) . '/includes/footer.php'; ?>
