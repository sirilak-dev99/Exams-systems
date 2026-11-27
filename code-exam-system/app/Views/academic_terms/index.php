<?php
// ---------- ค่าพื้นฐาน ----------
$academicTerms = $academicTerms ?? [];
$pageTitle     = $pageTitle ?? 'ภาคการศึกษา';
require dirname(__DIR__) . '/includes/header.php';

// ---------- ตัวกรอง ----------
$year     = trim((string)($_GET['academic_year'] ?? ''));
$status   = (string)($_GET['status'] ?? '');
$filtered = $year !== '' || $status !== '';

// ---------- ข้อความ ----------
$statusLabels = ['active' => 'ภาคปัจจุบัน', 'upcoming' => 'ภาคถัดไป', 'inactive' => 'ปิดใช้งาน', 'archived' => 'จัดเก็บแล้ว'];
$thaiDate = function (?string $date): string {
    if ($date === null || !preg_match('/^(\d{4})-(\d{2})-(\d{2})/', $date, $m)) {
        return '—';
    }
    $months = ['ม.ค.', 'ก.พ.', 'มี.ค.', 'เม.ย.', 'พ.ค.', 'มิ.ย.', 'ก.ค.', 'ส.ค.', 'ก.ย.', 'ต.ค.', 'พ.ย.', 'ธ.ค.'];
    return (int)$m[3] . ' ' . $months[(int)$m[2] - 1] . ' ' . ((int)$m[1] + 543);
};
?>
<section class="panel">
    <?php /* ---------- หัวข้อ ---------- */ ?>
    <h1>ภาคการศึกษา</h1>
    <p class="muted">ปีการศึกษา ภาคเรียน และช่วงวันเปิด–ปิดภาค</p>

    <?php /* ---------- ตัวกรอง ---------- */ ?>
    <form class="filters" method="get" action="<?= Security::escape($appUrl) ?>/academic-terms">
        <div>
            <label for="fYear">ปีการศึกษา (พ.ศ.)</label>
            <input id="fYear" name="academic_year" type="number" min="2500" step="1" value="<?= Security::escape($year) ?>" placeholder="เช่น 2569">
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
        <?php if ($filtered): ?><a href="<?= Security::escape($appUrl) ?>/academic-terms">ล้างตัวกรอง</a><?php endif; ?>
    </form>

    <?php /* ---------- ตาราง ---------- */ ?>
    <?php if (!$academicTerms): ?>
        <p class="muted"><?= $filtered ? 'ไม่พบภาคการศึกษาที่ตรงกับเงื่อนไข' : 'ยังไม่มีภาคการศึกษา' ?></p>
    <?php else: ?>
        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr>
                        <th scope="col">ภาค/ปีการศึกษา</th>
                        <th scope="col">วันเปิดภาค</th>
                        <th scope="col">วันปิดภาค</th>
                        <th scope="col">สถานะ</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($academicTerms as $t): ?>
                        <tr>
                            <td><b>ภาค <?= (int)$t['term_no'] ?>/<?= (int)$t['academic_year'] ?></b></td>
                            <td><?= Security::escape($thaiDate($t['start_date'] ?? null)) ?></td>
                            <td><?= Security::escape($thaiDate($t['end_date'] ?? null)) ?></td>
                            <td><span class="badge badge--<?= Security::escape((string)$t['status']) ?>"><?= Security::escape($statusLabels[$t['status']] ?? (string)$t['status']) ?></span></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <p class="muted">ทั้งหมด <?= count($academicTerms) ?> ภาคการศึกษา</p>
    <?php endif; ?>
</section>
<?php require dirname(__DIR__) . '/includes/footer.php'; ?>
