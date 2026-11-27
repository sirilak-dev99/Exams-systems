<?php
// ---------- ค่าพื้นฐาน ----------
$courses   = $courses ?? [];
$pageTitle = 'รายวิชา';
require dirname(__DIR__) . '/includes/header.php';

// ---------- ตัวกรอง ----------
$groupId  = trim((string)($_GET['course_groups_id'] ?? ''));
$filtered = $groupId !== '';

$groupOptions = [];
foreach ($courses as $c) {
    $groupOptions[(int)$c['course_groups_id']] = (string)$c['course_group_name'];
}
?>
<?php /* ---------- หัวข้อ ---------- */ ?>
<div class="page-head">
    <div class="page-head__text">
        <h1>รายวิชา</h1>
        <p>รายวิชาทั้งหมดในระบบ แยกตามกลุ่มวิชา</p>
    </div>
</div>

<section class="card card--flush">
    <?php /* ---------- ตัวกรอง ---------- */ ?>
    <form class="toolbar" method="get" action="<?= Security::escape($appUrl) ?>/courses" role="search">
        <div class="field field--sm">
            <label class="field__label" for="fGroup">กลุ่มวิชา</label>
            <select class="select" id="fGroup" name="course_groups_id">
                <option value="">ทุกกลุ่ม</option>
                <?php foreach ($groupOptions as $id => $label): ?>
                    <option value="<?= $id ?>"<?= $groupId === (string)$id ? ' selected' : '' ?>><?= Security::escape($label) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="btn-row">
            <button class="btn btn--primary" type="submit">ค้นหา</button>
            <?php if ($filtered): ?><a class="btn btn--ghost" href="<?= Security::escape($appUrl) ?>/courses">ล้างตัวกรอง</a><?php endif; ?>
        </div>
    </form>

    <?php /* ---------- ตาราง ---------- */ ?>
    <?php if (!$courses): ?>
        <div class="empty"><?= ui_icon('inbox', 40) ?><b><?= $filtered ? 'ไม่พบรายวิชาในกลุ่มนี้' : 'ยังไม่มีรายวิชา' ?></b></div>
    <?php else: ?>
        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr>
                        <th scope="col">รหัสวิชา</th>
                        <th scope="col">ชื่อรายวิชา</th>
                        <th scope="col">กลุ่มวิชา</th>
                        <th scope="col">ผู้สร้าง</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($courses as $c): ?>
                        <tr>
                            <td class="code nowrap"><?= Security::escape((string)$c['code']) ?></td>
                            <td><?= Security::escape((string)$c['name']) ?></td>
                            <td><?= Security::escape((string)$c['course_group_name']) ?></td>
                            <td><?= Security::escape((string)($c['created_by_name'] ?? '—')) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <div class="table-foot">ทั้งหมด <?= count($courses) ?> รายวิชา</div>
    <?php endif; ?>
</section>
<?php require dirname(__DIR__) . '/includes/footer.php'; ?>
