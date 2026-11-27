<?php
// ---------- ค่าพื้นฐาน ----------
$crumbTitle = 'โปรไฟล์';
require dirname(__DIR__) . '/includes/header.php';

// ---------- ข้อมูลผู้ใช้ ----------
$user      = $user ?? $authUser ?? [];
$roleNames = ['student' => 'นิสิต', 'instructor' => 'อาจารย์ผู้สอน', 'academic_staff' => 'เจ้าหน้าที่ฝ่ายวิชาการ', 'admin' => 'ผู้ดูแลระบบ'];
$roles     = array_map(static fn ($r) => $roleNames[$r] ?? $r, (array)($user['roles'] ?? []));
?>
<?php /* ---------- หัวข้อ ---------- */ ?>
<div class="page-title">
    <h1><?= Security::escape($pageTitle) ?></h1>
    <p>ข้อมูลบัญชีที่ใช้เข้าสู่ระบบ</p>
</div>

<?php /* ---------- บัญชี ---------- */ ?>
<section class="card profile-card">
    <div class="profile-card__head">
        <span class="profile-card__avatar" aria-hidden="true"><?= ui_icon('user', 40) ?></span>
        <div>
            <h2><?= Security::escape((string)($user['display_name'] ?? '')) ?></h2>
            <p class="muted"><?= Security::escape(implode(' · ', $roles)) ?></p>
        </div>
    </div>
    <dl class="info-list">
        <dt>รหัสนิสิต</dt>
        <dd class="code"><?= Security::escape((string)($user['student_code'] ?? '—')) ?></dd>
        <dt>อีเมล</dt>
        <dd><?= Security::escape((string)($user['email'] ?? '—')) ?></dd>
        <dt>บทบาท</dt>
        <dd><?= Security::escape($roles ? implode(', ', $roles) : '—') ?></dd>
    </dl>
</section>

<?php /* ---------- ข้อมูลการศึกษา ---------- */ ?>
<section class="card">
    <div class="card__head"><h2>ข้อมูลการศึกษา</h2></div>
    <?php $pendingApi = 'หลักสูตร ชั้นปี และสถานะการศึกษาของนิสิตจะแสดงเมื่อมี API ข้อมูลนิสิตของตัวเอง'; require dirname(__DIR__) . '/includes/api-pending.php'; ?>
</section>
<?php require dirname(__DIR__) . '/includes/footer.php'; ?>
