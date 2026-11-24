<?php require dirname(__DIR__).'/includes/header.php'; ?>
<section class="panel form-panel">
    <h1>เข้าสู่ระบบ</h1>
    <?php if (!empty($error)): ?>
        <p class="alert" role="alert"><?= Security::escape($error) ?></p>
    <?php endif; ?>
    <form method="post" action="<?= Security::escape((string) env('APP_URL', '')) ?>/login">
        <input type="hidden" name="_csrf" value="<?= Security::escape(Security::csrfToken()) ?>">
        <label for="identifier">อีเมลหรือรหัสนิสิต</label>
        <input
            id="identifier"
            name="identifier"
            type="text"
            value="<?= Security::escape($identifier ?? '') ?>"
            autocomplete="username"
            required
        >
        <label for="password">รหัสผ่าน</label>
        <input id="password" name="password" type="password" autocomplete="current-password" required>
        <input type="hidden" name="csrf_token" value="<?= Security::escape(Security::csrfToken()) ?>">
        <button type="submit">เข้าสู่ระบบ</button>
    </form>
</section>
<?php require dirname(__DIR__).'/includes/footer.php'; ?>
