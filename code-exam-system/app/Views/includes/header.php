<?php $pageTitle=$pageTitle??'ระบบสอบกลางคณะ'; ?>
<!doctype html><html lang="th"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= Security::escape($pageTitle) ?></title>
<link rel="stylesheet" href="<?= Security::escape((string)env('APP_URL','')) ?>/assets/css/app.css"></head><body>
<header class="site-header"><a href="<?= Security::escape((string)env('APP_URL','')) ?>/">ระบบสอบกลางคณะ</a><a href="<?= Security::escape((string)env('APP_URL','')) ?>/login">เข้าสู่ระบบ</a></header><main class="container">
