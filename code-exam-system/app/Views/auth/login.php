<?php require dirname(__DIR__).'/includes/header.php'; ?>
<section class="panel form-panel"><h1>เข้าสู่ระบบ</h1>
<?php if (!empty($error)): ?><p class="alert"><?= Security::escape($error) ?></p><?php endif; ?>
<form method="post" action="<?= Security::escape((string)env('APP_URL','')) ?>/login">
<input type="hidden" name="_csrf" value="<?= Security::escape(Security::csrfToken()) ?>">
<label for="email">อีเมล / รหัสผู้ใช้</label><input id="email" name="email" autocomplete="username" required>
<label for="password">รหัสผ่าน</label><input id="password" name="password" type="password" autocomplete="current-password" required>
<button type="submit">เข้าสู่ระบบ</button></form><p class="muted">หน้านี้เป็นโครงตัวอย่าง ยังไม่เชื่อมตรวจสอบบัญชีจริง</p></section>
<?php require dirname(__DIR__).'/includes/footer.php'; ?>
