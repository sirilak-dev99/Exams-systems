<?php
// ---------- ค่าพื้นฐาน ----------
$pageTitle = $pageTitle ?? 'ระบบสอบกลางคณะ';
$appUrl    = rtrim((string)env('APP_URL', ''), '/');
$authUser  = Security::currentUser();

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
<title><?= Security::escape($pageTitle) ?> · ระบบสอบกลางคณะ</title>
<link rel="stylesheet" href="<?= Security::escape($appUrl) ?>/assets/css/app.css?v=<?= Security::escape($cssVer) ?>">
</head>
<body class="layout-default">
<a class="skip-link" href="#content">ข้ามไปที่เนื้อหา</a>

<?php /* ---------- แถบบน ---------- */ ?>
<header class="site-header">
    <a href="<?= Security::escape($appUrl) ?>/">ระบบสอบกลางคณะ</a>

    <?php if ($authUser === null): ?>
        <a href="<?= Security::escape($appUrl) ?>/login">เข้าสู่ระบบ</a>
    <?php else: ?>
        <form method="post" action="<?= Security::escape($appUrl) ?>/logout" style="display:flex;align-items:center;gap:12px;margin:0">
            <span><?= Security::escape((string)($authUser['display_name'] ?? '')) ?></span>
            <input type="hidden" name="_csrf" value="<?= Security::escape(Security::csrfToken()) ?>">
            <button type="submit" style="margin:0;width:auto">ออกจากระบบ</button>
        </form>
    <?php endif; ?>
</header>

<?php /* ---------- เนื้อหา ---------- */ ?>
<main id="content" class="container">
