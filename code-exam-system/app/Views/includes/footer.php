<?php
/**
 * ============================================================
 *  footer.php — ปิดหน้า (ใช้คู่กับ header.php เสมอ)
 * ------------------------------------------------------------
 *  ตอนนี้ไม่มี JS กลาง · ตั้งก่อน include ได้: $pageJs = ['assets/js/xxx.js']  (JS เฉพาะหน้า)
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
<?php /* ==================== JS เฉพาะหน้า ($pageJs) ==================== */ ?>
<?php foreach (($pageJs ?? []) as $js): ?>
<script src="<?= Security::escape($jsUrl((string)$js)) ?>"></script>
<?php endforeach; ?>
</body>
</html>
