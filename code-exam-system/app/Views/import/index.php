<?php
// ---------- ค่าพื้นฐาน ----------
$crumbTitle = 'นำเข้าข้อมูล';
require dirname(__DIR__) . '/includes/header.php';

// ---------- ประเภทข้อมูล ----------
$roles   = (array)($authUser['roles'] ?? []);
$isStaff = (bool)array_intersect(['admin', 'academic_staff'], $roles);
$imports = [
    ['/import/courses',  'book',  'รายวิชา',     'รหัสวิชา ชื่อวิชา และกลุ่มวิชา', $isStaff],
    ['/import/students', 'users', 'รายชื่อนิสิต', 'รหัสนิสิต ชื่อ อีเมล และหลักสูตร', $isStaff],
];
?>
<?php /* ---------- หัวข้อ ---------- */ ?>
<div class="page-title">
    <h1><?= Security::escape($pageTitle) ?></h1>
    <p>เลือกประเภทข้อมูลที่จะนำเข้าจากไฟล์ Excel</p>
</div>

<?php /* ---------- ประเภท ---------- */ ?>
<div class="tiles">
    <?php foreach ($imports as [$href, $icon, $title, $desc, $allowed]): ?>
        <?php if ($allowed): ?>
            <a class="tile" href="<?= Security::escape($appUrl . $href) ?>">
                <span class="tile__icon" aria-hidden="true"><?= ui_icon($icon, 26) ?></span>
                <span class="tile__text"><b><?= Security::escape($title) ?></b><span><?= Security::escape($desc) ?></span></span>
            </a>
        <?php else: ?>
            <span class="tile tile--off" aria-disabled="true" title="เฉพาะผู้ดูแลระบบและเจ้าหน้าที่ฝ่ายวิชาการ">
                <span class="tile__icon" aria-hidden="true"><?= ui_icon($icon, 26) ?></span>
                <span class="tile__text"><b><?= Security::escape($title) ?></b><span>เฉพาะผู้ดูแลระบบและเจ้าหน้าที่ฝ่ายวิชาการ</span></span>
            </span>
        <?php endif; ?>
    <?php endforeach; ?>
</div>
<?php require dirname(__DIR__) . '/includes/footer.php'; ?>
