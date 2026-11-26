<?php
/**
 * ============================================================
 *  หน้า        : รายวิชา (แท็บที่ 3 ของเมนู "หลักสูตรและรายวิชา")
 *  Route       : GET /courses → CourseController@index
 *  สิทธิ์       : course.view (อาจารย์, ฝ่ายวิชาการ, แอดมิน)
 * ------------------------------------------------------------
 *  ข้อมูลที่ได้รับ
 *    $courses  รายการรายวิชา [id_courses, code, name, course_groups_id, course_group_name, created_by_name, updated_at]
 *  ตัวกรอง (GET): q = ค้นหารหัส/ชื่อวิชา · course_groups_id = กรองตามกลุ่ม
 *  ⚠️ ค้นหา (?q=) ยัง error 500 จากบั๊ก :q ซ้ำใน CourseModel (docs/api-check.md ข้อ 1)
 * ============================================================
 */
require dirname(__DIR__) . '/includes/header.php';

$courses  = $courses ?? [];
$q        = trim((string)($_GET['q'] ?? ''));
$groupId  = ctype_digit((string)($_GET['course_groups_id'] ?? '')) ? (int)$_GET['course_groups_id'] : 0;
$filtered = $q !== '' || $groupId > 0;
$appUrl   = rtrim((string)env('APP_URL', ''), '/');
// ชื่อกลุ่มที่กำลังกรอง (เอาจากรายการที่ได้มา เพราะ Controller ไม่ได้ส่งรายการกลุ่มมาด้วย)
$groupName = '';
foreach ($courses as $c) {
    if ((int)$c['course_groups_id'] === $groupId) { $groupName = (string)$c['course_group_name']; break; }
}
?>
<?php $catalogTab = 'courses'; $pageHeading = 'จัดการรายวิชา'; $pageDesc = 'รายวิชาทั้งหมดในระบบ ค้นหาได้จากรหัสหรือชื่อวิชา'; require dirname(__DIR__) . '/includes/catalog-tabs.php'; ?>

<?php if ($groupId > 0): ?>
    <div class="alert" role="status">
        <?= ui_icon('folder') ?>
        <div>แสดงเฉพาะกลุ่มวิชา <strong><?= Security::escape($groupName !== '' ? $groupName : '#' . $groupId) ?></strong> · <a href="<?= Security::escape($appUrl) ?>/courses">แสดงทุกกลุ่ม</a></div>
    </div>
<?php endif; ?>

<section class="card card--flush">
    <form class="toolbar" method="get" action="<?= Security::escape($appUrl) ?>/courses" role="search">
        <?php if ($groupId > 0): ?>
            <input type="hidden" name="course_groups_id" value="<?= $groupId ?>">
        <?php endif; ?>
        <div class="field">
            <label class="field__label" for="q">ค้นหา</label>
            <div class="search"><?= ui_icon('search', 20) ?><input class="input" id="q" name="q" type="search" value="<?= Security::escape($q) ?>" placeholder="รหัสวิชาหรือชื่อวิชา"></div>
        </div>
        <div class="btn-row">
            <button class="btn btn--primary" type="submit">ค้นหา</button>
            <?php if ($filtered): ?><a class="btn btn--ghost" href="<?= Security::escape($appUrl) ?>/courses">ล้างตัวกรอง</a><?php endif; ?>
        </div>
    </form>

    <?php if (empty($courses)): ?>
        <div class="empty">
            <?= ui_icon('inbox', 40) ?>
            <b><?= $filtered ? 'ไม่พบรายวิชาที่ตรงกับเงื่อนไข' : 'ยังไม่มีรายวิชา' ?></b>
            <span><?= $filtered ? 'ลองเปลี่ยนคำค้นหา หรือกดล้างตัวกรอง' : 'เมื่อเพิ่มรายวิชาแล้วจะแสดงที่นี่' ?></span>
        </div>
    <?php else: ?>
        <div class="table-wrap">
            <table class="table">
                <caption class="sr-only">รายการรายวิชา</caption>
                <thead>
                    <tr>
                        <th scope="col">รหัสวิชา</th>
                        <th scope="col">ชื่อวิชา</th>
                        <th scope="col">กลุ่มวิชา</th>
                        <th scope="col">ผู้สร้าง</th>
                        <th scope="col">แก้ไขล่าสุด</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($courses as $c): ?>
                        <tr>
                            <td class="code nowrap"><?= Security::escape((string)$c['code']) ?></td>
                            <td><?= Security::escape((string)$c['name']) ?></td>
                            <td>
                                <?php if ($groupId > 0): ?>
                                    <?= Security::escape((string)$c['course_group_name']) ?>
                                <?php else: ?>
                                    <a href="<?= Security::escape($appUrl . '/courses?course_groups_id=' . (int)$c['course_groups_id']) ?>"><?= Security::escape((string)$c['course_group_name']) ?></a>
                                <?php endif; ?>
                            </td>
                            <td><?= Security::escape((string)($c['created_by_name'] ?? '-')) ?></td>
                            <td class="nowrap"><?= Security::escape(thai_date((string)$c['updated_at'])) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <div class="table-foot">ทั้งหมด <?= count($courses) ?> รายวิชา</div>
    <?php endif; ?>
</section>
<?php require dirname(__DIR__) . '/includes/footer.php'; ?>
