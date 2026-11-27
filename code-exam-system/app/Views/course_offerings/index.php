<?php
// ---------- ค่าพื้นฐาน ----------
$courseOfferings = $courseOfferings ?? [];
$crumbTitle = 'หลักสูตรและรายวิชา';
require dirname(__DIR__) . '/includes/header.php';

// ---------- ตัวกรอง ----------
$termId   = trim((string)($_GET['academic_terms_id'] ?? ''));
$courseId = trim((string)($_GET['courses_id'] ?? ''));
$status   = (string)($_GET['status'] ?? '');
$filtered = $termId !== '' || $courseId !== '' || $status !== '';

$termOptions   = [];
$courseOptions = [];
foreach ($courseOfferings as $o) {
    $termOptions[(int)$o['academic_terms_id']] = (int)$o['term_no'] . '/' . (int)$o['academic_year'];
    $courseOptions[(int)$o['courses_id']]      = $o['course_code'] . ' ' . $o['course_name'];
}
asort($courseOptions);

// ---------- ข้อความ ----------
$statusLabels  = ['open' => 'เปิดสอน', 'completed' => 'สิ้นสุดภาค', 'closed' => 'ปิดแล้ว'];
$statusFilters = ['open' => 'เปิดสอน', 'closed' => 'ปิดแล้ว'];
$percent = function ($value): string {
    if ($value === null || $value === '') {
        return '—';
    }
    return rtrim(rtrim(number_format((float)$value, 2, '.', ''), '0'), '.') . '%';
};
?>
<?php $catalogTab = '/course-offerings'; require dirname(__DIR__) . '/includes/catalog-tabs.php'; ?>

<section class="card card--flush">
    <?php /* ---------- ตัวกรอง ---------- */ ?>
    <form class="toolbar" method="get" action="<?= Security::escape($appUrl) ?>/course-offerings" role="search">
        <div class="field field--sm">
            <label class="field__label" for="fTerm">ภาคการศึกษา</label>
            <select class="select" id="fTerm" name="academic_terms_id">
                <option value="">ทุกภาค</option>
                <?php foreach ($termOptions as $id => $label): ?>
                    <option value="<?= $id ?>"<?= $termId === (string)$id ? ' selected' : '' ?>><?= Security::escape($label) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="field">
            <label class="field__label" for="fCourse">รายวิชา</label>
            <select class="select" id="fCourse" name="courses_id">
                <option value="">ทุกรายวิชา</option>
                <?php foreach ($courseOptions as $id => $label): ?>
                    <option value="<?= $id ?>"<?= $courseId === (string)$id ? ' selected' : '' ?>><?= Security::escape($label) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="field field--sm">
            <label class="field__label" for="fStatus">สถานะ</label>
            <select class="select" id="fStatus" name="status">
                <option value="">ทุกสถานะ</option>
                <?php foreach ($statusFilters as $value => $label): ?>
                    <option value="<?= Security::escape($value) ?>"<?= $status === $value ? ' selected' : '' ?>><?= Security::escape($label) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="btn-row">
            <button class="btn btn--primary" type="submit">ค้นหา</button>
            <?php if ($filtered): ?><a class="btn btn--ghost" href="<?= Security::escape($appUrl) ?>/course-offerings">ล้างตัวกรอง</a><?php endif; ?>
        </div>
    </form>

    <?php /* ---------- ตาราง ---------- */ ?>
    <?php if (!$courseOfferings): ?>
        <div class="empty"><?= ui_icon('inbox', 40) ?><b><?= $filtered ? 'ไม่พบรายวิชาที่เปิดสอนตามเงื่อนไข' : 'ยังไม่มีรายวิชาที่เปิดสอน' ?></b></div>
    <?php else: ?>
        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr>
                        <th scope="col">รหัสวิชา</th>
                        <th scope="col">ชื่อรายวิชา</th>
                        <th scope="col">ภาค/ปี</th>
                        <th scope="col" class="num">เกณฑ์ผ่าน</th>
                        <th scope="col" class="num">กลุ่มเรียน</th>
                        <th scope="col">สถานะ</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($courseOfferings as $o): ?>
                        <tr>
                            <td class="code nowrap"><?= Security::escape((string)$o['course_code']) ?></td>
                            <td><?= Security::escape((string)$o['course_name']) ?></td>
                            <td class="nowrap"><?= (int)$o['term_no'] ?>/<?= (int)$o['academic_year'] ?></td>
                            <td class="num"><?= Security::escape($percent($o['pass_percent'] ?? null)) ?></td>
                            <td class="num"><?= (int)$o['sections_count'] ?></td>
                            <td><span class="badge badge--offering-<?= Security::escape((string)$o['status']) ?>"><?= Security::escape($statusLabels[$o['status']] ?? (string)$o['status']) ?></span></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <div class="table-foot">ทั้งหมด <?= count($courseOfferings) ?> รายการ</div>
    <?php endif; ?>
</section>
<?php require dirname(__DIR__) . '/includes/footer.php'; ?>
