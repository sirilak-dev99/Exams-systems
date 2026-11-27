<?php
require_once __DIR__ . '/helpers.php';

// ---------- ค่าพื้นฐาน ----------
$pageTitle = $pageTitle ?? 'ระบบสอบกลางคณะ';
$appUrl    = rtrim((string)env('APP_URL', ''), '/');
$authUser  = Security::currentUser();
$layout    = $authUser !== null ? 'app' : 'default';

// ---------- เวอร์ชัน CSS ----------
$cssFile = dirname(__DIR__, 3) . '/public/assets/css/app.css';
$cssVer  = is_file($cssFile) ? (string)filemtime($cssFile) : '1';
?>
<?php /* ---------- head ---------- */ ?>
<!doctype html>
<html lang="th">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= Security::escape($pageTitle) ?> · ระบบคลังข้อสอบ คณะพยาบาลศาสตร์</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Sans+Thai:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= Security::escape($appUrl) ?>/assets/css/app.css?v=<?= Security::escape($cssVer) ?>">
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
            <div class="crumbs"><?= ui_icon('home', 20) ?><b><?= Security::escape($pageTitle) ?></b></div>
            <div class="user">
                <span class="user__avatar" aria-hidden="true"><?= ui_icon('user', 22) ?></span>
                <span class="user__name"><b><?= Security::escape((string)($authUser['display_name'] ?? '')) ?></b></span>
            </div>
        </header>

        <?php /* ---------- เนื้อหา ---------- */ ?>
        <main id="content" class="content">
<?php else: ?>
<?php /* ---------- แถบบน (ยังไม่เข้าสู่ระบบ) ---------- */ ?>
<header class="site-header">
    <a href="<?= Security::escape($appUrl) ?>/">ระบบสอบกลางคณะ</a>
    <a href="<?= Security::escape($appUrl) ?>/login">เข้าสู่ระบบ</a>
</header>

<?php /* ---------- เนื้อหา ---------- */ ?>
<main id="content" class="container">
<?php endif; ?>
