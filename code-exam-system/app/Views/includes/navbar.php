<?php
// ---------- บทบาท ----------
$roles      = (array)($authUser['roles'] ?? []);
$isTeacher  = in_array('instructor', $roles, true);
$isStaff    = (bool)array_intersect($roles, ['admin', 'academic_staff']);

// ---------- รายการเมนู: [ชื่อ, ไอคอน, path หรือ null, view] ----------
$navGroups = [];
$navGroups[''] = [['หน้าหลัก', 'home', '/', 'dashboard']];
if ($isTeacher) {
    $navGroups[''][] = ['จัดการรายวิชา',    'book',  null, null];
    $navGroups[''][] = ['คลังข้อสอบ',       'bank',  null, null];
    $navGroups[''][] = ['จัดการชุดสอบ',     'clip',  null, null];
    $navGroups[''][] = ['ผลสอบและรายงาน',  'chart', null, null];
}
if ($isStaff) {
    $navGroups['งานวิชาการ'] = array_values(array_filter([
        Security::hasPermission('program.manage') ? ['หลักสูตร',      'school',   '/programs',         'programs/index'] : null,
        Security::hasPermission('course.manage')  ? ['กลุ่มวิชา',      'folder',   '/course-groups',    'courses/groups'] : null,
        Security::hasPermission('course.view')    ? ['รายวิชา',       'book',     '/courses',          'courses/index'] : null,
        Security::hasPermission('course.view')    ? ['ภาคการศึกษา',   'calendar', '/academic-terms',   'academic_terms/index'] : null,
        Security::hasPermission('course.view')    ? ['เปิดสอนรายวิชา', 'layers',   '/course-offerings', 'course_offerings/index'] : null,
    ]));
}

// ---------- หน้าปัจจุบัน ----------
$basePath    = (string)(parse_url($appUrl, PHP_URL_PATH) ?: '');
$currentPath = (string)(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/');
if ($basePath !== '' && str_starts_with($currentPath, $basePath)) {
    $currentPath = substr($currentPath, strlen($basePath)) ?: '/';
}
$viewsDir = dirname(__DIR__);
?>
<nav class="sidebar" aria-label="เมนูหลัก">
    <?php /* ---------- โลโก้ ---------- */ ?>
    <a class="sidebar__brand" href="<?= Security::escape($appUrl) ?>/">
        <?= ui_icon('brand', 56) ?>
        <b>ระบบคลังข้อสอบ<br>คณะพยาบาลศาสตร์</b>
        <small>มหาวิทยาลัยบูรพา</small>
    </a>

    <?php /* ---------- เมนู ---------- */ ?>
    <ul class="nav">
        <?php foreach ($navGroups as $group => $items): ?>
            <?php if ($group !== ''): ?><li class="nav__group" aria-hidden="true"><?= Security::escape($group) ?></li><?php endif; ?>
            <?php foreach ($items as [$label, $icon, $href, $view]): ?>
                <li>
                    <?php if ($href !== null && is_file($viewsDir . '/' . $view . '.php')): ?>
                        <a class="nav__link" href="<?= Security::escape($appUrl . $href) ?>"<?= $currentPath === $href ? ' aria-current="page"' : '' ?>>
                            <?= ui_icon($icon) ?><span><?= Security::escape($label) ?></span>
                        </a>
                    <?php else: ?>
                        <span class="nav__link nav__link--soon" aria-disabled="true">
                            <?= ui_icon($icon) ?><span><?= Security::escape($label) ?></span><small class="nav__soon">เร็วๆ นี้</small>
                        </span>
                    <?php endif; ?>
                </li>
            <?php endforeach; ?>
        <?php endforeach; ?>
    </ul>

    <?php /* ---------- ออกจากระบบ ---------- */ ?>
    <div class="sidebar__foot">
        <form method="post" action="<?= Security::escape($appUrl) ?>/logout">
            <input type="hidden" name="_csrf" value="<?= Security::escape(Security::csrfToken()) ?>">
            <button type="submit" class="nav__link sidebar__logout"><?= ui_icon('logout') ?><span>ออกจากระบบ</span></button>
        </form>
    </div>
</nav>
