<?php
/**
 * ============================================================
 *  footer.php — ปิดหน้าและโหลด JS กลาง (ใช้คู่กับ header.php เสมอ)
 * ------------------------------------------------------------
 *  JS ที่โหลดทุกหน้า
 *    assets/js/api.js  HTTP client กลาง (fetch) อ่าน { data, error } ตาม docs/api_contract.md
 *    assets/js/ui.js   Modal · Toast · UI.confirm() · แสดง error ใต้ช่องกรอก · ปุ่มกำลังโหลด · ข้อความไทยของ enum
 *  ตั้งก่อน include ได้: $pageJs = ['assets/js/pages/xxx.js']  (JS เฉพาะหน้า โหลดหลัง api.js / ui.js)
 *  ทุกไฟล์ต่อท้าย ?v=เวลาแก้ไฟล์ กันเบราว์เซอร์ใช้ไฟล์เก่า
 * ============================================================
 */
$layout = $layout ?? 'default';
$appUrl = rtrim((string)env('APP_URL', ''), '/');
$jsUrl  = function (string $path) use ($appUrl): string {
    $file = dirname(__DIR__, 3) . '/public/' . ltrim($path, '/');
    return $appUrl . '/' . ltrim($path, '/') . (is_file($file) ? '?v=' . filemtime($file) : '');
};
?>
<?php /* ==================== ปิดโครงหน้าที่ header.php / navbar.php เปิดไว้ (ตาม layout) ==================== */ ?>
<?php if ($layout === 'app'): ?>
    </main>
    <footer class="app-footer">ระบบจัดการคลังข้อสอบและทดสอบออนไลน์ · คณะพยาบาลศาสตร์</footer>
  </div>
</div>
<?php elseif ($layout === 'auth'): ?>
</main>
<?php elseif ($layout === 'blank'): ?>
  </main>
  <p class="blank__foot">ระบบจัดการคลังข้อสอบและทดสอบออนไลน์ · Development</p>
</div>
<?php else: ?>
</main><footer class="site-footer">ระบบจัดการคลังข้อสอบและทดสอบออนไลน์ · Development</footer>
<?php endif; ?>
<?php /* ==================== JS กลางทุกหน้า แล้วตามด้วย JS เฉพาะหน้า ($pageJs) ==================== */ ?>
<script src="<?= Security::escape($jsUrl('assets/js/api.js')) ?>"></script>
<script src="<?= Security::escape($jsUrl('assets/js/ui.js')) ?>"></script>
<?php foreach (($pageJs ?? []) as $js): ?>
<script src="<?= Security::escape($jsUrl((string)$js)) ?>"></script>
<?php endforeach; ?>
</body>
</html>
