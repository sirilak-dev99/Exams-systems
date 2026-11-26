<?php
/**
 * ============================================================
 *  catalog-tabs.php — หัวหน้า + แท็บของเมนู "หลักสูตรและรายวิชา"
 * ------------------------------------------------------------
 *  ใช้ใน programs/index.php · courses/groups.php · courses/index.php
 *  ตาม prototype AdminPrograms.html (หัวข้อเดียว แล้วแยกส่วนด้วยแท็บ: หลักสูตร | กลุ่มวิชา | รายวิชา)
 *
 *  ตั้งก่อน include
 *    $catalogTab   แท็บที่เปิดอยู่: 'programs' | 'groups' | 'courses'
 *    $pageHeading  หัวข้อของหน้า (ใช้เมื่อผู้ใช้เห็นแท็บเดียว เช่น อาจารย์เห็นแค่รายวิชา)
 *    $pageDesc     คำอธิบายใต้หัวข้อ (ใช้เมื่อเห็นแท็บเดียว)
 *  แสดงเฉพาะแท็บที่ผู้ใช้มีสิทธิ์ · เหลือแท็บเดียวจะไม่แสดงแถบแท็บ
 * ============================================================
 */
$catalogTabs = array_filter([
    'programs' => Security::hasPermission('program.manage') ? ['/programs', 'หลักสูตร', 'school'] : null,
    'groups'   => Security::hasPermission('course.manage')  ? ['/course-groups', 'กลุ่มวิชา', 'folder'] : null,
    'courses'  => Security::hasPermission('course.view')    ? ['/courses', 'รายวิชา', 'book'] : null,
]);
$catalogTab = $catalogTab ?? '';
$tabsUrl    = rtrim((string)env('APP_URL', ''), '/');
$multiTab   = count($catalogTabs) > 1;
?>
<div class="page-head">
    <div class="page-head__text">
        <?php if ($multiTab): ?>
            <h1>หลักสูตรและรายวิชา</h1>
            <p>หลักสูตรของคณะ กลุ่มวิชา และรายวิชาทั้งหมด</p>
        <?php else: ?>
            <h1><?= Security::escape($pageHeading ?? '') ?></h1>
            <p><?= Security::escape($pageDesc ?? '') ?></p>
        <?php endif; ?>
    </div>
</div>
<?php if ($multiTab): ?>
<nav class="tabs" aria-label="หลักสูตรและรายวิชา">
    <?php foreach ($catalogTabs as $key => [$href, $label, $icon]): ?>
        <a href="<?= Security::escape($tabsUrl . $href) ?>"<?= $key === $catalogTab ? ' aria-current="page"' : '' ?>><?= ui_icon($icon, 20) ?><?= Security::escape($label) ?></a>
    <?php endforeach; ?>
</nav>
<?php endif; ?>
