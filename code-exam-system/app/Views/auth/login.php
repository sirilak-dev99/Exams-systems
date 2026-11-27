<?php
// ---------- ค่าพื้นฐาน ----------
$authLayout = true;
require dirname(__DIR__) . '/includes/header.php';
?>
<div class="auth__split">
    <?php /* ---------- ฝั่งแบรนด์ ---------- */ ?>
    <section class="auth__brand" aria-label="ระบบคลังข้อสอบ">
        <?= ui_icon('brand', 104) ?>
        <p class="auth__org">คณะพยาบาลศาสตร์ · มหาวิทยาลัยบูรพา</p>
        <h1>ระบบคลังข้อสอบ<br>และทดสอบออนไลน์กลาง</h1>
        <p class="auth__lead">ออกข้อสอบ จัดสอบ และดูผลวิเคราะห์ ในระบบเดียว · สำหรับอาจารย์ นิสิต และงานวิชาการ</p>
    </section>

    <?php /* ---------- ฟอร์มเข้าสู่ระบบ ---------- */ ?>
    <section class="auth__side">
        <div class="auth__card">
            <h2>เข้าสู่ระบบ</h2>
            <p class="auth__hint">ใช้อีเมลของมหาวิทยาลัยหรือรหัสนิสิต และรหัสผ่าน</p>

            <?php if (!empty($error)): ?>
                <div class="alert alert--danger" role="alert"><b><?= Security::escape($error) ?></b></div>
            <?php endif; ?>

            <form class="form-stack" method="post" action="<?= Security::escape($appUrl) ?>/login">
                <input type="hidden" name="_csrf" value="<?= Security::escape(Security::csrfToken()) ?>">
                <input type="hidden" name="csrf_token" value="<?= Security::escape(Security::csrfToken()) ?>">
                <div class="field">
                    <label class="field__label" for="identifier">อีเมลหรือรหัสนิสิต</label>
                    <input class="input" id="identifier" name="identifier" type="text" value="<?= Security::escape($identifier ?? '') ?>" autocomplete="username" required<?= !empty($error) ? ' aria-invalid="true"' : '' ?>>
                </div>
                <div class="field">
                    <label class="field__label" for="password">รหัสผ่าน</label>
                    <input class="input" id="password" name="password" type="password" autocomplete="current-password" required<?= !empty($error) ? ' aria-invalid="true"' : '' ?>>
                </div>
                <button class="btn btn--primary btn--block btn--lg" type="submit">เข้าสู่ระบบ</button>
            </form>

            <div class="auth__domains">
                <p><b>นิสิต</b> ใช้อีเมล @go.buu.ac.th</p>
                <p><b>อาจารย์และบุคลากร</b> ใช้อีเมล @nurse.buu.ac.th</p>
            </div>

            <div class="auth__or" aria-hidden="true"><span>หรือ</span></div>
            <button class="btn btn--ghost btn--block" type="button" disabled>เข้าสู่ระบบด้วย Google <small>เร็วๆ นี้</small></button>

            <p class="auth__help">เข้าไม่ได้? ติดต่อผู้ดูแลระบบของคณะ</p>
        </div>
    </section>
</div>
<?php require dirname(__DIR__) . '/includes/footer.php'; ?>
