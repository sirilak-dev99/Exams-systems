<?php
// ---------- บทบาท ----------
$roles     = (array)($authUser['roles'] ?? []);
$isTeacher = in_array('instructor', $roles, true);
$isAdmin   = in_array('admin', $roles, true);
$isStaff   = $isAdmin || in_array('academic_staff', $roles, true);

// ---------- รายการเมนู: [ชื่อ, ไอคอน, path หรือ null, view, path ของหน้าย่อย] ----------
$catalog = ['/programs', '/course-groups', '/courses', '/academic-terms', '/course-offerings'];
$navGroups = [];
if ($isStaff && !$isTeacher) {
    $navGroups['งานวิชาการ'] = [
        ['Dashboard',          'home',   '/',         'dashboard',      []],
        ['หลักสูตรและรายวิชา', 'school', '/programs', 'programs/index', $catalog],
        ['จัดการนิสิต',         'users',  null,        null,             []],
        ['ติดตามผลการสอบ',     'chart',  null,        null,             []],
        ['ประชาสัมพันธ์',       'news',   null,        null,             []],
    ];
} else {
    $navGroups[''] = [['หน้าหลัก', 'home', '/', 'dashboard', []]];
    if ($isTeacher) {
        $navGroups[''][] = ['จัดการรายวิชา',   'book',  null, null, []];
        $navGroups[''][] = ['คลังข้อสอบ',      'bank',  null, null, []];
        $navGroups[''][] = ['จัดการชุดสอบ',    'clip',  null, null, []];
        $navGroups[''][] = ['ผลสอบและรายงาน', 'chart', null, null, []];
    } else {
        $navGroups[''][] = ['รายวิชาของฉัน',     'book',  null, null, []];
        $navGroups[''][] = ['การสอบของฉัน',     'clip',  null, null, []];
        $navGroups[''][] = ['ผลการสอบ',         'chart', null, null, []];
        $navGroups[''][] = ['ข่าวประชาสัมพันธ์', 'news',  null, null, []];
        $navGroups[''][] = ['โปรไฟล์',          'user',  null, null, []];
    }
}
if ($isAdmin) {
    $navGroups['ระบบ'] = [
        ['ผู้ใช้และสิทธิ์',  'shield', null, null, []],
        ['บันทึกการใช้งาน', 'log',    null, null, []],
        ['ตั้งค่าระบบ',      'gear',   null, null, []],
    ];
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
            <?php foreach ($items as [$label, $icon, $href, $view, $match]): ?>
                <li>
                    <?php if ($href !== null && is_file($viewsDir . '/' . $view . '.php')): ?>
                        <a class="nav__link" href="<?= Security::escape($appUrl . $href) ?>"<?= $currentPath === $href ? ' aria-current="page"' : (in_array($currentPath, $match, true) ? ' aria-current="true"' : '') ?>>
                            <?= ui_icon($icon) ?><span><?= Security::escape($label) ?></span>
                        </a>
                    <?php else: ?>
                        <span class="nav__link nav__link--soon" aria-disabled="true" title="เร็วๆ นี้">
                            <?= ui_icon($icon) ?><span><?= Security::escape($label) ?></span><span class="sr-only"> (เร็วๆ นี้)</span>
                        </span>
                    <?php endif; ?>
                </li>
            <?php endforeach; ?>
        <?php endforeach; ?>
    </ul>
</nav>
