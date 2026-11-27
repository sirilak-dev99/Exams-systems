<?php
// ---------- ค่าพื้นฐาน ----------
$courseGroups = $courseGroups ?? [];
$pageTitle    = 'หลักสูตรและรายวิชา';
require dirname(__DIR__) . '/includes/header.php';

// ---------- ตัวกรอง ----------
$q        = trim((string)($_GET['q'] ?? ''));
$filtered = $q !== '';
?>
<?php $catalogTab = '/course-groups'; require dirname(__DIR__) . '/includes/catalog-tabs.php'; ?>

<section class="card card--flush">
    <?php /* ---------- ตัวกรอง ---------- */ ?>
    <form class="toolbar" method="get" action="<?= Security::escape($appUrl) ?>/course-groups" role="search">
        <div class="field">
            <label class="field__label" for="q">ค้นหา</label>
            <div class="search"><?= ui_icon('search', 20) ?><input class="input" id="q" name="q" type="search" value="<?= Security::escape($q) ?>" placeholder="ชื่อกลุ่มวิชา"></div>
        </div>
        <div class="btn-row">
            <button class="btn btn--primary" type="submit">ค้นหา</button>
            <?php if ($filtered): ?><a class="btn btn--ghost" href="<?= Security::escape($appUrl) ?>/course-groups">ล้างตัวกรอง</a><?php endif; ?>
        </div>
    </form>

    <?php /* ---------- ตาราง ---------- */ ?>
    <?php if (!$courseGroups): ?>
        <div class="empty"><?= ui_icon('inbox', 40) ?><b><?= $filtered ? 'ไม่พบกลุ่มวิชาที่ตรงกับคำค้นหา' : 'ยังไม่มีกลุ่มวิชา' ?></b></div>
    <?php else: ?>
        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr>
                        <th scope="col">ชื่อกลุ่มวิชา</th>
                        <th scope="col" class="num">จำนวนรายวิชา</th>
                        <th scope="col">สร้างเมื่อ</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($courseGroups as $g): ?>
                        <tr>
                            <td><?= Security::escape((string)$g['name']) ?></td>
                            <td class="num"><?= (int)$g['courses_count'] ?></td>
                            <td class="nowrap"><?= Security::escape(thai_date($g['created_at'] ?? null)) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <div class="table-foot">ทั้งหมด <?= count($courseGroups) ?> กลุ่มวิชา</div>
    <?php endif; ?>
</section>
<?php require dirname(__DIR__) . '/includes/footer.php'; ?>
