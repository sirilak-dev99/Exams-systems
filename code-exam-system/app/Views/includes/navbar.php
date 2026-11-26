<?php
/**
 * ============================================================
 *  navbar.php — เมนูซ้ายตามบทบาท + แถบบน และเปิด <main class="content">
 * ------------------------------------------------------------
 *  header.php include ไฟล์นี้ให้อัตโนมัติเมื่อ layout = app (หน้าที่เข้าสู่ระบบแล้ว) · footer.php ปิดแท็กให้
 *  โครงเมนูทั้งหมด (ชื่อ ไอคอน path สิทธิ์) อยู่ใน includes/menu.php
 *
 *  ตัวแปรที่ใช้จาก header.php : $appUrl, $authUser, $pageTitle
 *  ตั้งก่อน include header ได้ : $crumbSub = ชื่อหน้าย่อยใน breadcrumb (เช่น 'สร้างชุดสอบ')
 * ============================================================
 */
// ---------- 1. path ปัจจุบัน (ตัด base ของ APP_URL ออกแบบเดียวกับ Router) — ใช้ไฮไลต์เมนู ----------
$basePath    = (string)(parse_url($appUrl, PHP_URL_PATH) ?: '');
$currentPath = (string)(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/');
if ($basePath !== '' && str_starts_with($currentPath, $basePath)) {
    $currentPath = substr($currentPath, strlen($basePath)) ?: '/';
}
$currentPath = '/' . trim($currentPath, '/');

// เมนูแยกตามบทบาท — โครงทั้งหมดอยู่ใน includes/menu.php
require_once __DIR__ . '/menu.php';
// ---------- 2. เมนูของผู้ใช้ + เมนูที่ครอบหน้านี้ (ดู 'match' ใน menu.php) ----------
$navGroups  = app_menu($authUser);
$activeItem = menu_active_item($navGroups, $currentPath);   // เมนูที่ครอบหน้านี้ (ดู 'match' ใน menu.php)
$homeItem   = menu_active_item($navGroups, '/');
$homeLabel  = $homeItem['label'] ?? 'หน้าหลัก';

// ---------- 3. ชื่อ บทบาท และอักษรย่อของผู้ใช้ (แสดงมุมขวาบน) ----------
$roleLabels = ['student' => 'นิสิต', 'instructor' => 'อาจารย์', 'academic_staff' => 'เจ้าหน้าที่ฝ่ายวิชาการ', 'admin' => 'ผู้ดูแลระบบ'];
$userName   = (string)($authUser['display_name'] ?? 'ผู้ใช้');
$userRole   = implode(' · ', array_map(fn ($r) => $roleLabels[$r] ?? $r, $authUser['roles'] ?? []));
$initial    = mb_substr(trim($userName) ?: 'ผ', 0, 1, 'UTF-8');
?>
<?php /* ==================== แถบเมนูซ้าย: โลโก้ · เมนูตามบทบาท · ปุ่มออกจากระบบ ==================== */ ?>
<div class="app">
  <nav class="sidebar" aria-label="เมนูหลัก">
    <a class="sidebar__brand" href="<?= Security::escape($appUrl) ?>/">
      <?= ui_icon('brand', 56) ?>
      <b>ระบบจัดการคลังข้อสอบ<br>และทดสอบออนไลน์</b>
      <small>คณะพยาบาลศาสตร์ มหาวิทยาลัยบูรพา</small>
    </a>
    <?php /* ==================== รายการเมนู — มี route = ลิงก์ · ยังไม่มี route = ป้าย "เร็วๆ นี้" (กดไม่ได้) ==================== */ ?>
    <ul class="nav">
      <?php foreach ($navGroups as $group => $items): ?>
        <?php if ($group !== ''): ?><li class="nav__group" aria-hidden="true"><?= Security::escape($group) ?></li><?php endif; ?>
        <?php foreach ($items as $item): ?>
          <li<?= $item['href'] === null ? ' class="nav__item--soon"' : '' ?>>
            <?php if ($item['href'] !== null): ?>
              <a class="nav__link" href="<?= Security::escape($appUrl . $item['href']) ?>"<?= ($activeItem !== null && $activeItem['label'] === $item['label']) ? ' aria-current="' . ($item['href'] === $currentPath ? 'page' : 'true') . '"' : '' ?>>
                <?= ui_icon($item['icon']) ?><span><?= Security::escape($item['label']) ?></span>
              </a>
            <?php else: ?>
              <span class="nav__link nav__link--soon" aria-disabled="true">
                <?= ui_icon($item['icon']) ?><span><?= Security::escape($item['label']) ?></span><small class="nav__soon">เร็วๆ นี้</small>
              </span>
            <?php endif; ?>
          </li>
        <?php endforeach; ?>
      <?php endforeach; ?>
    </ul>
    <?php /* ==================== ออกจากระบบ (ฟอร์ม POST /logout + CSRF) ==================== */ ?>
    <div class="sidebar__foot">
      <?php /* ออกจากระบบ: ฟอร์ม POST /logout + csrf แบบ dashboard.php เดิม (ตำแหน่งตาม starter navbar.php) */ ?>
      <form method="post" action="<?= Security::escape($appUrl) ?>/logout">
        <input type="hidden" name="_csrf" value="<?= Security::escape(Security::csrfToken()) ?>">
        <button type="submit" class="nav__link sidebar__logout"><?= ui_icon('logout') ?><span>ออกจากระบบ</span></button>
      </form>
    </div>
  </nav>

  <?php /* ==================== แถบบน: breadcrumb (หน้าหลัก › เมนู › หน้าย่อย) · ผู้ใช้ ==================== */ ?>
  <div class="main">
    <header class="topbar">
      <div class="crumbs">
        <?php if ($currentPath === '/'): ?>
          <b><?= Security::escape($homeLabel) ?></b>
        <?php else: ?>
          <a href="<?= Security::escape($appUrl) ?>/"><?= Security::escape($homeLabel) ?></a><?= ui_icon('chev', 16) ?>
          <?php if ($activeItem !== null && ($activeItem['href'] !== $currentPath || !empty($crumbSub))): ?>
            <?php /* หน้าย่อยของเมนู เช่น หลักสูตรและรายวิชา › กลุ่มวิชา หรือ จัดการชุดสอบ › สร้างชุดสอบ ($crumbSub) */ ?>
            <a href="<?= Security::escape($appUrl . $activeItem['href']) ?>"><?= Security::escape($activeItem['label']) ?></a><?= ui_icon('chev', 16) ?><b><?= Security::escape(!empty($crumbSub) ? $crumbSub : $pageTitle) ?></b>
          <?php else: ?>
            <b><?= Security::escape($activeItem['label'] ?? $pageTitle) ?></b>
          <?php endif; ?>
        <?php endif; ?>
      </div>
      <div class="user">
        <span class="user__avatar" aria-hidden="true"><?= Security::escape($initial) ?></span>
        <span class="user__name"><b><?= Security::escape($userName) ?></b><small><?= Security::escape($userRole !== '' ? $userRole : 'ยังไม่ได้กำหนดบทบาท') ?></small></span>
      </div>
    </header>
    <?php /* ==================== เนื้อหาของหน้า (footer.php ปิด </main> ให้) ==================== */ ?>
    <main id="content" class="content">
