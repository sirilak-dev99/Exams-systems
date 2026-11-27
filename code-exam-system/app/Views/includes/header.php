<?php
require_once __DIR__ . '/helpers.php';

// ---------- ค่าพื้นฐาน ----------
$pageTitle = $pageTitle ?? 'ระบบสอบกลางคณะ';
$appUrl    = rtrim((string)env('APP_URL', ''), '/');
$authUser  = Security::currentUser();
$layout    = $authUser !== null ? 'app' : (!empty($authLayout) ? 'auth' : 'default');

// ---------- เวอร์ชันไฟล์ ----------
$assetsDir = dirname(__DIR__, 3) . '/public/assets';
$assetVer  = static fn (string $path): string => is_file($assetsDir . $path) ? (string)filemtime($assetsDir . $path) : '1';
$cssVer    = $assetVer('/css/app.css');
$scripts   = array_merge(['/js/api.js'], (array)($pageScripts ?? []));
?>
<?php /* ---------- head ---------- */ ?>
<!doctype html>
<html lang="th">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="app-url" content="<?= Security::escape($appUrl) ?>">
<meta name="csrf-token" content="<?= Security::escape(Security::csrfToken()) ?>">
<title><?= Security::escape($pageTitle) ?> · ระบบคลังข้อสอบ คณะพยาบาลศาสตร์</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Sans+Thai:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= Security::escape($appUrl) ?>/assets/css/app.css?v=<?= Security::escape($cssVer) ?>">
<?php /* ---------- สคริปต์ ---------- */ ?>
<?php foreach ($scripts as $script): ?>
<script defer src="<?= Security::escape($appUrl) ?>/assets<?= Security::escape($script) ?>?v=<?= Security::escape($assetVer($script)) ?>"></script>
<?php endforeach; ?>
</head>
<body class="layout-<?= $layout ?>">
<a class="skip-link" href="#content">ข้ามไปที่เนื้อหา</a>

<?php if ($layout === 'app'): ?>
<div class="app">
    <?php /* ---------- เมนูซ้าย ---------- */ ?>
    <?php require __DIR__ . '/navbar.php'; ?>

    <div class="main">
        <?php /* ---------- แถบบน ---------- */ ?>
        <header class="topbar">
            <a class="crumbs" href="<?= Security::escape($appUrl) ?>/"><svg width="26" height="26" viewBox="0 0 24 24" aria-hidden="true"><path d="M12 3 2.5 11h2.5v9.5h5.5v-6h3v6H19V11h2.5z" fill="currentColor"/></svg><b><?= Security::escape($crumbTitle ?? $pageTitle) ?></b></a>
            <?php /* ---------- ค้นหา ---------- */ ?>
            <?php
            $userRoles = (array)($authUser['roles'] ?? []);
            $searchHint = array_intersect($userRoles, ['admin', 'academic_staff']) && !in_array('instructor', $userRoles, true)
                ? 'ค้นหาผู้ใช้ รายวิชา หรือหลักสูตร...'
                : (in_array('instructor', $userRoles, true) ? 'ค้นหารายวิชา ชุดสอบ หรือข้อสอบ...' : 'ค้นหารายวิชา การสอบ หรือเนื้อหา...');
            ?>
            <label class="topbar__search" title="การค้นหาจะเปิดใช้เร็วๆ นี้">
                <?= ui_icon('search', 22) ?>
                <input type="search" placeholder="<?= Security::escape($searchHint) ?>" aria-label="ค้นหา (เร็วๆ นี้)" readonly>
            </label>
            <span class="topbar__bell" title="การแจ้งเตือน · เร็วๆ นี้" aria-label="การแจ้งเตือน (เร็วๆ นี้)" role="img"><?= ui_icon('bell', 26) ?></span>
            <span class="topbar__sep" aria-hidden="true"></span>
            <?php /* ---------- เมนูโปรไฟล์ ---------- */ ?>
            <details class="profile">
                <summary class="user" aria-label="เมนูผู้ใช้">
                    <span class="user__avatar" aria-hidden="true"><?= ui_icon('user', 22) ?></span>
                    <span class="user__name"><b><?= Security::escape((string)($authUser['display_name'] ?? '')) ?></b></span>
                    <span class="user__caret"><?= ui_icon('chev', 18) ?></span>
                </summary>
                <div class="profile__menu">
                    <span class="profile__item profile__item--soon" aria-disabled="true"><?= ui_icon('gear', 20) ?>ตั้งค่า<small>เร็วๆ นี้</small></span>
                    <div class="profile__sep" role="separator"></div>
                    <form method="post" action="<?= Security::escape($appUrl) ?>/logout">
                        <input type="hidden" name="_csrf" value="<?= Security::escape(Security::csrfToken()) ?>">
                        <button type="submit" class="profile__item"><?= ui_icon('logout', 20) ?>ออกจากระบบ</button>
                    </form>
                </div>
            </details>
        </header>

        <?php /* ---------- เนื้อหา ---------- */ ?>
        <main id="content" class="content">
<?php elseif ($layout === 'auth'): ?>
<?php /* ---------- เนื้อหา (หน้าเข้าสู่ระบบ) ---------- */ ?>
<main id="content" class="auth">
<?php else: ?>
<?php /* ---------- แถบบน (ยังไม่เข้าสู่ระบบ) ---------- */ ?>
<header class="site-header">
    <a href="<?= Security::escape($appUrl) ?>/">ระบบสอบกลางคณะ</a>
    <a href="<?= Security::escape($appUrl) ?>/login">เข้าสู่ระบบ</a>
</header>

<?php /* ---------- เนื้อหา ---------- */ ?>
<main id="content" class="container">
<?php endif; ?>
