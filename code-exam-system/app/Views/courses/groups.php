<?php
/**
 * ============================================================
 *  หน้า        : กลุ่มวิชา (แท็บที่ 2 ของเมนู "หลักสูตรและรายวิชา")
 *  Route       : GET /course-groups → CourseController@groupIndex
 *  สิทธิ์       : course.manage (ฝ่ายวิชาการ, แอดมิน)
 * ------------------------------------------------------------
 *  ข้อมูลที่ได้รับ
 *    $courseGroups  รายการกลุ่มวิชา [id_course_groups, name, created_at, courses_count]
 *  ตัวกรอง (GET): q = ค้นหาชื่อกลุ่มวิชา
 * ============================================================
 */
require dirname(__DIR__) . '/includes/header.php';

$courseGroups = $courseGroups ?? [];
$q            = trim((string)($_GET['q'] ?? ''));
$appUrl       = rtrim((string)env('APP_URL', ''), '/');
$canViewCourses = Security::hasPermission('course.view');
?>
<?php $catalogTab = 'groups'; $pageHeading = 'กลุ่มวิชา'; $pageDesc = 'กลุ่มวิชาและจำนวนรายวิชาที่ใช้งานอยู่ในแต่ละกลุ่ม'; require dirname(__DIR__) . '/includes/catalog-tabs.php'; ?>

<section class="card card--flush">
    <form class="toolbar" method="get" action="<?= Security::escape($appUrl) ?>/course-groups" role="search">
        <div class="field">
            <label class="field__label" for="q">ค้นหา</label>
            <div class="search"><?= ui_icon('search', 20) ?><input class="input" id="q" name="q" type="search" value="<?= Security::escape($q) ?>" placeholder="ชื่อกลุ่มวิชา"></div>
        </div>
        <div class="btn-row">
            <button class="btn btn--primary" type="submit">ค้นหา</button>
            <?php if ($q !== ''): ?><a class="btn btn--ghost" href="<?= Security::escape($appUrl) ?>/course-groups">ล้างตัวกรอง</a><?php endif; ?>
        </div>
    </form>

    <?php if (empty($courseGroups)): ?>
        <div class="empty">
            <?= ui_icon('inbox', 40) ?>
            <b><?= $q !== '' ? 'ไม่พบกลุ่มวิชาที่ตรงกับคำค้นหา' : 'ยังไม่มีกลุ่มวิชา' ?></b>
            <span><?= $q !== '' ? 'ลองเปลี่ยนคำค้นหา' : 'เมื่อเพิ่มกลุ่มวิชาแล้วจะแสดงที่นี่' ?></span>
        </div>
    <?php else: ?>
        <div class="table-wrap">
            <table class="table">
                <caption class="sr-only">รายการกลุ่มวิชา</caption>
                <thead>
                    <tr>
                        <th scope="col">ชื่อกลุ่มวิชา</th>
                        <th scope="col" class="num">จำนวนรายวิชา</th>
                        <th scope="col">สร้างเมื่อ</th>
                        <th scope="col"><span class="sr-only">ดูรายวิชา</span></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($courseGroups as $g): ?>
                        <tr>
                            <td><b><?= Security::escape((string)$g['name']) ?></b></td>
                            <td class="num"><?= (int)$g['courses_count'] ?></td>
                            <td class="nowrap"><?= Security::escape(thai_date((string)$g['created_at'], false)) ?></td>
                            <td class="num">
                                <?php if ($canViewCourses && (int)$g['courses_count'] > 0): ?>
                                    <a class="btn btn--secondary btn--sm" href="<?= Security::escape($appUrl . '/courses?course_groups_id=' . (int)$g['id_course_groups']) ?>">ดูรายวิชา<?= ui_icon('chev', 18) ?></a>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <div class="table-foot">ทั้งหมด <?= count($courseGroups) ?> กลุ่มวิชา</div>
    <?php endif; ?>
</section>
<?php require dirname(__DIR__) . '/includes/footer.php'; ?>
