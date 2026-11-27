<?php
// ---------- แท็บ: [path, ชื่อ, ไอคอน, สิทธิ์] ----------
$catalogTabs = [
    ['/programs',         'หลักสูตร',       'school',   Security::hasPermission('program.manage')],
    ['/course-groups',    'กลุ่มวิชา',       'folder',   Security::hasPermission('course.manage')],
    ['/courses',          'รายวิชา',        'book',     Security::hasPermission('course.view')],
    ['/academic-terms',   'ภาคการศึกษา',    'calendar', Security::hasPermission('course.view')],
    ['/course-offerings', 'เปิดสอนรายวิชา', 'layers',   Security::hasPermission('course.view')],
];
$catalogTab = $catalogTab ?? '';
?>
<?php /* ---------- หัวข้อ ---------- */ ?>
<div class="page-head">
    <div class="page-head__text">
        <h1>หลักสูตรและรายวิชา</h1>
        <p>ทุกหลักสูตรของคณะ · เปิดภาคการศึกษา จัดรายวิชา กลุ่มเรียน และผู้สอน</p>
    </div>
</div>

<?php /* ---------- แท็บ ---------- */ ?>
<nav class="tabs" aria-label="หลักสูตรและรายวิชา">
    <?php foreach ($catalogTabs as [$href, $label, $icon, $can]): ?>
        <?php if (!$can) { continue; } ?>
        <a href="<?= Security::escape($appUrl . $href) ?>"<?= $catalogTab === $href ? ' aria-current="page"' : '' ?>><?= ui_icon($icon, 20) ?><?= Security::escape($label) ?></a>
    <?php endforeach; ?>
</nav>
