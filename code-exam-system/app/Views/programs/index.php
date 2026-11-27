<?php
// ---------- ค่าพื้นฐาน ----------
$programs  = $programs ?? [];
$crumbTitle = 'หลักสูตรและรายวิชา';
require dirname(__DIR__) . '/includes/header.php';

// ---------- ตัวกรอง ----------
$status   = (string)($_GET['status'] ?? '');
$filtered = $status !== '';

// ---------- ข้อความ ----------
$statusLabels = ['active' => 'ใช้งาน', 'inactive' => 'ปิดใช้งาน', 'archived' => 'จัดเก็บแล้ว'];
?>
<?php $catalogTab = '/programs'; require dirname(__DIR__) . '/includes/catalog-tabs.php'; ?>

<section class="card card--flush">
    <?php /* ---------- ตัวกรอง ---------- */ ?>
    <form class="toolbar" method="get" action="<?= Security::escape($appUrl) ?>/programs" role="search">
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
            <?php if ($filtered): ?><a class="btn btn--ghost" href="<?= Security::escape($appUrl) ?>/programs">ล้างตัวกรอง</a><?php endif; ?>
        </div>
    </form>

    <?php /* ---------- ตาราง ---------- */ ?>
    <?php if (!$programs): ?>
        <div class="empty"><?= ui_icon('inbox', 40) ?><b><?= $filtered ? 'ไม่พบหลักสูตรที่ตรงกับเงื่อนไข' : 'ยังไม่มีหลักสูตร' ?></b></div>
    <?php else: ?>
        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr>
                        <th scope="col">รหัสหลักสูตร</th>
                        <th scope="col">ชื่อหลักสูตร</th>
                        <th scope="col" class="num">จำนวนรุ่น</th>
                        <th scope="col">สถานะ</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($programs as $p): ?>
                        <tr>
                            <td class="code nowrap"><?= Security::escape((string)$p['code']) ?></td>
                            <td><?= Security::escape((string)$p['name']) ?></td>
                            <td class="num"><?= (int)$p['versions_count'] ?></td>
                            <td><span class="badge badge--<?= Security::escape((string)$p['status']) ?>"><?= Security::escape($statusLabels[$p['status']] ?? (string)$p['status']) ?></span></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <div class="table-foot">ทั้งหมด <?= count($programs) ?> หลักสูตร</div>
    <?php endif; ?>
</section>
<?php require dirname(__DIR__) . '/includes/footer.php'; ?>
