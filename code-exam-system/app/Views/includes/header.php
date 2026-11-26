<?php
/**
 * ============================================================
 *  header.php — ส่วนหัวของทุกหน้า (ใช้คู่กับ footer.php เสมอ)
 * ------------------------------------------------------------
 *  วิธีใช้ใน view
 *    <?php require dirname(__DIR__) . '/includes/header.php'; ?>
 *      ...เนื้อหา...
 *    <?php require dirname(__DIR__) . '/includes/footer.php'; ?>
 *
 *  ตัวแปรที่ตั้งก่อน include ได้ (ไม่ตั้งก็มีค่าเริ่มต้น)
 *    $pageTitle  ชื่อหน้าบนแท็บเบราว์เซอร์ (Controller ส่งมาอยู่แล้ว)
 *    $layout     app     = เมนูซ้าย + แถบบน (navbar.php) ← ค่าเริ่มต้นเมื่อเข้าสู่ระบบแล้ว
 *                blank   = กล่องกลางจอ                  ← ค่าเริ่มต้นเมื่อยังไม่เข้าสู่ระบบ (เช่น 403, 404)
 *                auth    = แบ่งสองฝั่ง แผงแบรนด์ + ฟอร์ม (หน้า Login ตั้งเอง)
 *                default = หน้าตาเดิมก่อนมี Design System
 *    $pageCss    CSS เพิ่มเฉพาะหน้า เช่น ['assets/css/exam-builder.css']
 *    $crumbSub   ชื่อหน้าย่อยใน breadcrumb ของแถบบน (layout app)
 *
 *  ลำดับในไฟล์: 1 ค่าพื้นฐาน · 2 เวอร์ชัน CSS · 3 ฟังก์ชันช่วย · 4 <head> · 5 เปิดโครงหน้า
 * ============================================================
 */
// ---------- 1. ค่าพื้นฐานของหน้า ----------
$pageTitle = $pageTitle ?? 'ระบบจัดการคลังข้อสอบและทดสอบออนไลน์';
$appUrl    = rtrim((string)env('APP_URL', ''), '/');
$authUser  = Security::currentUser();
$layout    = $layout ?? ($authUser !== null ? 'app' : 'blank');

// ---------- 2. เลขเวอร์ชันของ CSS (เวลาแก้ไฟล์) กันเบราว์เซอร์ใช้ไฟล์เก่า ----------
$cssFile = dirname(__DIR__, 3) . '/public/assets/css/app.css';
$cssVer  = is_file($cssFile) ? (string)filemtime($cssFile) : '1';

// ---------- 3. ฟังก์ชันช่วยที่ทุก view ใช้: ui_icon() ไอคอน · thai_date() วันที่ภาษาไทย ----------
if (!function_exists('ui_icon')) {
    /** ไอคอนเส้น 24×24 (ใช้ currentColor) */
    function ui_icon(string $name, int $size = 22): string
    {
        static $paths = [
            'home'   => '<path d="M3 10.5 12 3l9 7.5"/><path d="M5 9.5V21h14V9.5"/><path d="M10 21v-6h4v6"/>',
            'clip'   => '<rect x="5" y="4" width="14" height="18" rx="2"/><path d="M9 2h6v4H9z"/><path d="m9 14 2 2 4-4"/>',
            'school' => '<path d="m2 9 10-5 10 5-10 5z"/><path d="M6 11v5c3 2 9 2 12 0v-5"/>',
            'folder' => '<path d="M3 7a2 2 0 0 1 2-2h4l2 2h8a2 2 0 0 1 2 2v8a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/>',
            'book'   => '<path d="M2 4h7a3 3 0 0 1 3 3v14a2 2 0 0 0-2-2H2z"/><path d="M22 4h-7a3 3 0 0 0-3 3v14a2 2 0 0 1 2-2h8z"/>',
            'logout' => '<path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><path d="m16 17 5-5-5-5M21 12H9"/>',
            'brand'  => '<path d="M12 20s-7-4.4-7-10a4 4 0 0 1 7-2.6A4 4 0 0 1 19 10c0 5.6-7 10-7 10z"/><path d="M12 9v5M9.5 11.5h5"/>',   // ตาม starter navbar.php
            'search' => '<circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/>',
            'alert'  => '<circle cx="12" cy="12" r="9"/><path d="M12 8v5M12 16h.01"/>',
            'inbox'  => '<path d="M22 12h-6l-2 3h-4l-2-3H2"/><path d="M5.5 5h13L22 12v6a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2v-6z"/>',
            'chev'   => '<path d="m9 6 6 6-6 6"/>',
            'back'   => '<path d="m15 18-6-6 6-6"/>',
            'users'  => '<circle cx="9" cy="8" r="3.5"/><path d="M2 20a7 7 0 0 1 14 0"/><path d="M16 4.5a3.5 3.5 0 0 1 0 7M22 20a7 7 0 0 0-4-6.3"/>',
            'user'   => '<circle cx="12" cy="8" r="4"/><path d="M4 21a8 8 0 0 1 16 0"/>',
            'video'  => '<rect x="2" y="5" width="14" height="14" rx="2"/><path d="m16 10 6-3v10l-6-3"/>',
            'bank'   => '<rect x="3" y="3" width="18" height="18" rx="3"/><path d="M8 8h8M8 12h8M8 16h5"/>',
            'monitor'=> '<rect x="2" y="4" width="20" height="13" rx="2"/><path d="M8 21h8M12 17v4"/><circle cx="12" cy="10.5" r="2.5"/>',
            'file'   => '<path d="M14 3H6a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V9z"/><path d="M14 3v6h6M8 13h8M8 17h5"/>',
            'pen'    => '<path d="M12 20h9"/><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4z"/>',
            'chart'  => '<path d="M4 20V10M10 20V4M16 20v-7M22 20H2"/>',
            'news'   => '<path d="M3 11v2a1 1 0 0 0 1 1h2l5 4V6L6 10H4a1 1 0 0 0-1 1z"/><path d="M15 9a4 4 0 0 1 0 6M18 6a8 8 0 0 1 0 12"/>',
            'play'   => '<circle cx="12" cy="12" r="9"/><path d="m10 8 6 4-6 4z"/>',
            'calendar'=> '<rect x="3" y="5" width="18" height="16" rx="2"/><path d="M3 10h18M8 3v4M16 3v4"/>',
            'layers' => '<path d="m12 3 9 5-9 5-9-5z"/><path d="m3 13 9 5 9-5"/>',
            'shield' => '<path d="M12 3 4 6v6c0 5 3.5 8 8 9 4.5-1 8-4 8-9V6z"/><path d="m9 12 2 2 4-4"/>',
            'log'    => '<path d="M14 3H6a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V9z"/><path d="M14 3v6h6"/><path d="M8 13h2M8 17h2M12 13h4M12 17h4"/>',
            'key'    => '<circle cx="8" cy="15" r="4"/><path d="m10.8 12.2 9.2-9.2M17 6l3 3M14.5 8.5l2 2"/>',
            'bolt'   => '<path d="M13 2 4 14h7l-1 8 9-12h-7z"/>',
            'arrow'  => '<path d="M5 12h14M13 6l6 6-6 6"/>',
            'file-plus' => '<path d="M14 3H6a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V9z"/><path d="M14 3v6h6M12 12v6M9 15h6"/>',
            'gear'   =>'<circle cx="12" cy="12" r="3"/><path d="M12 2v3M12 19v3M4.2 4.2l2.1 2.1M17.7 17.7l2.1 2.1M2 12h3M19 12h3M4.2 19.8l2.1-2.1M17.7 6.3l2.1-2.1"/>',
        ];
        return '<svg width="' . $size . '" height="' . $size . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . ($paths[$name] ?? '') . '</svg>';
    }
}
if (!function_exists('thai_date')) {
    /** '2026-11-25 10:06:00' → '25 พ.ย. 2569 10:06 น.' */
    function thai_date(?string $value, bool $withTime = true): string
    {
        if ($value === null || $value === '' || !preg_match('/^(\d{4})-(\d{2})-(\d{2})(?:[ T](\d{2}):(\d{2}))?/', $value, $m)) {
            return $value ?: '-';
        }
        $months = ['ม.ค.', 'ก.พ.', 'มี.ค.', 'เม.ย.', 'พ.ค.', 'มิ.ย.', 'ก.ค.', 'ส.ค.', 'ก.ย.', 'ต.ค.', 'พ.ย.', 'ธ.ค.'];
        $out = (int)$m[3] . ' ' . $months[(int)$m[2] - 1] . ' ' . ((int)$m[1] + 543);
        if ($withTime && isset($m[4])) {
            $out .= ' ' . $m[4] . ':' . $m[5] . ' น.';
        }
        return $out;
    }
}

?>
<?php /* ==================== 4. <head>: ชื่อหน้า · meta ของ api.js · ฟอนต์ · CSS ==================== */ ?>
<!doctype html>
<html lang="th">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= Security::escape($pageTitle) ?> · ระบบจัดการคลังข้อสอบและทดสอบออนไลน์</title>
<?php /* ค่าที่ assets/js/api.js อ่าน — routes ไม่มี prefix /api/v1 จึงใช้ path ของ APP_URL */ ?>
<meta name="api-base" content="<?= Security::escape((string)(parse_url($appUrl, PHP_URL_PATH) ?: '')) ?>">
<meta name="login-url" content="<?= Security::escape((string)(parse_url($appUrl, PHP_URL_PATH) ?: '')) ?>/login">
<meta name="csrf-token" content="<?= Security::escape(Security::csrfToken()) ?>">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Sans+Thai:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= Security::escape($appUrl) ?>/assets/css/app.css?v=<?= Security::escape($cssVer) ?>">
<?php foreach (($pageCss ?? []) as $css): ?>
<link rel="stylesheet" href="<?= Security::escape($appUrl . '/' . ltrim((string)$css, '/')) ?>">
<?php endforeach; ?>
</head>
<body class="layout-<?= Security::escape($layout) ?>">
<a class="skip-link" href="#content">ข้ามไปที่เนื้อหา</a>
<?php /* ==================== 5. เปิดโครงหน้าตาม layout (footer.php ปิดให้) ==================== */ ?>
<?php if ($layout === 'app'): ?>
<?php require __DIR__ . '/navbar.php'; ?>
<?php elseif ($layout === 'auth'): ?>
<main id="content" class="auth">
<?php elseif ($layout === 'blank'): ?>
<div class="blank">
  <a class="blank__brand" href="<?= Security::escape($appUrl) ?>/">
    <span class="brand__mark"><?= ui_icon('brand', 24) ?></span>
    <span class="brand__text"><b>ระบบจัดการคลังข้อสอบและทดสอบออนไลน์</b><small>คณะพยาบาลศาสตร์ มหาวิทยาลัยบูรพา</small></span>
  </a>
  <main id="content" class="blank__box">
<?php else: ?>
<header class="site-header"><a href="<?= Security::escape($appUrl) ?>/">ระบบจัดการคลังข้อสอบและทดสอบออนไลน์</a><a href="<?= Security::escape($appUrl) ?>/login">เข้าสู่ระบบ</a></header><main id="content" class="container">
<?php endif; ?>
