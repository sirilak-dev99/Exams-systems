<?php
// ---------- รายการเมนู ----------
$navItems = [
    ['หน้าหลัก',        '/',                 'dashboard',               true],
    ['หลักสูตร',         '/programs',         'programs/index',          Security::hasPermission('program.manage')],
    ['กลุ่มวิชา',         '/course-groups',    'courses/groups',          Security::hasPermission('course.manage')],
    ['รายวิชา',          '/courses',          'courses/index',           Security::hasPermission('course.view')],
    ['ภาคการศึกษา',      '/academic-terms',   'academic_terms/index',    Security::hasPermission('course.view')],
    ['เปิดสอนรายวิชา',    '/course-offerings', 'course_offerings/index',  Security::hasPermission('course.view')],
];

// ---------- หน้าปัจจุบัน ----------
$basePath    = (string)(parse_url($appUrl, PHP_URL_PATH) ?: '');
$currentPath = (string)(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/');
if ($basePath !== '' && str_starts_with($currentPath, $basePath)) {
    $currentPath = substr($currentPath, strlen($basePath)) ?: '/';
}
$viewsDir = dirname(__DIR__);
?>
<?php /* ---------- เมนู ---------- */ ?>
<nav class="site-nav" aria-label="เมนูหลัก">
    <?php foreach ($navItems as [$label, $href, $view, $can]): ?>
        <?php if (!$can) { continue; } ?>
        <?php if (is_file($viewsDir . '/' . $view . '.php')): ?>
            <a href="<?= Security::escape($appUrl . $href) ?>"<?= $currentPath === $href ? ' aria-current="page"' : '' ?>><?= Security::escape($label) ?></a>
        <?php else: ?>
            <span aria-disabled="true"><?= Security::escape($label) ?> <small>เร็วๆ นี้</small></span>
        <?php endif; ?>
    <?php endforeach; ?>
</nav>
