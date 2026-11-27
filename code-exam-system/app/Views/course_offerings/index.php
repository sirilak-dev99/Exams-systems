<?php
// ---------- ค่าพื้นฐาน ----------
$courseOfferings = $courseOfferings ?? [];
$pageTitle       = $pageTitle ?? 'เปิดสอนรายวิชา';
require dirname(__DIR__) . '/includes/header.php';

// ---------- ตัวกรอง ----------
$termId   = trim((string)($_GET['academic_terms_id'] ?? ''));
$status   = (string)($_GET['status'] ?? '');
$filtered = $termId !== '' || $status !== '';

$termOptions = [];
foreach ($courseOfferings as $o) {
    $termOptions[(int)$o['academic_terms_id']] = (int)$o['term_no'] . '/' . (int)$o['academic_year'];
}

// ---------- ข้อความ ----------
$statusLabels = ['open' => 'เปิดสอน', 'completed' => 'สิ้นสุดภาค', 'closed' => 'ปิดแล้ว'];
$percent = function ($value): string {
    if ($value === null || $value === '') {
        return '—';
    }
    return rtrim(rtrim(number_format((float)$value, 2, '.', ''), '0'), '.') . '%';
};
?>
<section class="panel">
    <?php /* ---------- หัวข้อ ---------- */ ?>
    <h1>เปิดสอนรายวิชา</h1>
    <p class="muted">รายวิชาที่เปิดสอนในแต่ละภาคการศึกษา และจำนวนกลุ่มเรียน</p>

    <?php /* ---------- ตัวกรอง ---------- */ ?>
    <form class="filters" method="get" action="<?= Security::escape($appUrl) ?>/course-offerings">
        <div>
            <label for="fTerm">ภาคการศึกษา</label>
            <select id="fTerm" name="academic_terms_id">
                <option value="">ทุกภาค</option>
                <?php foreach ($termOptions as $id => $label): ?>
                    <option value="<?= $id ?>"<?= $termId === (string)$id ? ' selected' : '' ?>><?= Security::escape($label) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label for="fStatus">สถานะ</label>
            <select id="fStatus" name="status">
                <option value="">ทุกสถานะ</option>
                <?php foreach ($statusLabels as $value => $label): ?>
                    <option value="<?= Security::escape($value) ?>"<?= $status === $value ? ' selected' : '' ?>><?= Security::escape($label) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <button type="submit">ค้นหา</button>
        <?php if ($filtered): ?><a href="<?= Security::escape($appUrl) ?>/course-offerings">ล้างตัวกรอง</a><?php endif; ?>
    </form>

    <?php /* ---------- ตาราง ---------- */ ?>
    <?php if (!$courseOfferings): ?>
        <p class="muted"><?= $filtered ? 'ไม่พบรายวิชาที่เปิดสอนตามเงื่อนไข' : 'ยังไม่มีรายวิชาที่เปิดสอน' ?></p>
    <?php else: ?>
        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr>
                        <th scope="col">รหัสวิชา</th>
                        <th scope="col">ชื่อรายวิชา</th>
                        <th scope="col">ภาค/ปี</th>
                        <th scope="col">เกณฑ์ผ่าน</th>
                        <th scope="col">กลุ่มเรียน</th>
                        <th scope="col">สถานะ</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($courseOfferings as $o): ?>
                        <tr>
                            <td><b><?= Security::escape((string)$o['course_code']) ?></b></td>
                            <td><?= Security::escape((string)$o['course_name']) ?></td>
                            <td><?= (int)$o['term_no'] ?>/<?= (int)$o['academic_year'] ?></td>
                            <td><?= Security::escape($percent($o['pass_percent'] ?? null)) ?></td>
                            <td><?= (int)$o['sections_count'] ?> กลุ่ม</td>
                            <td><span class="badge badge--offering-<?= Security::escape((string)$o['status']) ?>"><?= Security::escape($statusLabels[$o['status']] ?? (string)$o['status']) ?></span></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <p class="muted">ทั้งหมด <?= count($courseOfferings) ?> รายการ</p>
    <?php endif; ?>
</section>
<?php require dirname(__DIR__) . '/includes/footer.php'; ?>
