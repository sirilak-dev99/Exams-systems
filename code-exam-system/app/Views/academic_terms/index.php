<?php
// ---------- ค่าพื้นฐาน ----------
$academicTerms = $academicTerms ?? [];
$crumbTitle = 'หลักสูตรและรายวิชา';
require dirname(__DIR__) . '/includes/header.php';

// ---------- ตัวกรอง ----------
$year     = trim((string)($_GET['academic_year'] ?? ''));
$status   = (string)($_GET['status'] ?? '');
$filtered = $year !== '' || $status !== '';

// ---------- ข้อความ ----------
$statusLabels = ['active' => 'ภาคปัจจุบัน', 'upcoming' => 'ภาคถัดไป', 'inactive' => 'ปิดใช้งาน', 'archived' => 'จัดเก็บแล้ว'];
?>
<?php $catalogTab = '/academic-terms'; require dirname(__DIR__) . '/includes/catalog-tabs.php'; ?>

<section class="card card--flush">
    <?php /* ---------- ตัวกรอง ---------- */ ?>
    <form class="toolbar" method="get" action="<?= Security::escape($appUrl) ?>/academic-terms" role="search">
        <div class="field field--sm">
            <label class="field__label" for="fYear">ปีการศึกษา (พ.ศ.)</label>
            <input class="input" id="fYear" name="academic_year" type="number" min="2500" step="1" value="<?= Security::escape($year) ?>" placeholder="เช่น 2569">
        </div>
        <div class="field field--sm">
            <label class="field__label" for="fStatus">สถานะ</label>
            <select class="select" id="fStatus" name="status">
                <option value="">ทุกสถานะ</option>
                <?php foreach ($statusLabels as $value => $label): ?>
                    <option value="<?= Security::escape($value) ?>"<?= $status === $value ? ' selected' : '' ?>><?= Security::escape($label) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="btn-row">
            <button class="btn btn--primary" type="submit">ค้นหา</button>
            <?php if ($filtered): ?><a class="btn btn--ghost" href="<?= Security::escape($appUrl) ?>/academic-terms">ล้างตัวกรอง</a><?php endif; ?>
        </div>
    </form>

    <?php /* ---------- ตาราง ---------- */ ?>
    <?php if (!$academicTerms): ?>
        <div class="empty"><?= ui_icon('inbox', 40) ?><b><?= $filtered ? 'ไม่พบภาคการศึกษาที่ตรงกับเงื่อนไข' : 'ยังไม่มีภาคการศึกษา' ?></b></div>
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
                            <td class="nowrap"><b>ภาค <?= (int)$t['term_no'] ?>/<?= (int)$t['academic_year'] ?></b></td>
                            <td class="nowrap"><?= Security::escape(thai_date($t['start_date'] ?? null)) ?></td>
                            <td class="nowrap"><?= Security::escape(thai_date($t['end_date'] ?? null)) ?></td>
                            <td><span class="badge badge--<?= Security::escape((string)$t['status']) ?>"><?= Security::escape($statusLabels[$t['status']] ?? (string)$t['status']) ?></span></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <div class="table-foot">ทั้งหมด <?= count($academicTerms) ?> ภาคการศึกษา</div>
    <?php endif; ?>
</section>
<?php require dirname(__DIR__) . '/includes/footer.php'; ?>
