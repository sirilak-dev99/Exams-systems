<?php require __DIR__.'/includes/header.php'; ?>
<section class="panel">
    <p class="eyebrow">PROJECT WORKSPACE</p>
    <h1>ยินดีต้อนรับ <?= Security::escape($user['display_name'] ?? '') ?></h1>
    <p>เข้าสู่ระบบสำเร็จ</p>
    <p class="muted">บัญชี: <?= Security::escape($user['email'] ?? '') ?></p>
    <?php if (!empty($user['student_code'])): ?>
        <p class="muted">รหัสนิสิต: <?= Security::escape($user['student_code']) ?></p>
    <?php endif; ?>
    <p class="muted">บทบาท: <?= Security::escape(implode(', ', $user['roles'] ?? [])) ?: 'ยังไม่ได้กำหนด' ?></p>
    <form method="post" action="<?= Security::escape((string) env('APP_URL', '')) ?>/logout">
        <input type="hidden" name="_csrf" value="<?= Security::escape(Security::csrfToken()) ?>">
        <button type="submit">ออกจากระบบ</button>
    </form>
</section>
<?php require __DIR__.'/includes/footer.php'; ?>
