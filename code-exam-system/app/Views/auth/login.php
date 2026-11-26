<?php
/**
 * ============================================================
 *  หน้า        : เข้าสู่ระบบ
 *  Route       : GET /login → AuthController@showLogin  ·  POST /login → AuthController@login
 *  แบบหน้าจอ   : prototype Login.html (ซ้ายแผงแบรนด์ · ขวาฟอร์ม)
 * ------------------------------------------------------------
 *  ข้อมูลที่ได้รับ
 *    $error       ข้อความเมื่อเข้าสู่ระบบไม่สำเร็จ (AuthController ส่งมา)
 *    $identifier  อีเมล/รหัสนิสิตที่กรอกไว้ เพื่อไม่ต้องพิมพ์ใหม่
 *  หมายเหตุ: ส่ง CSRF ทั้งชื่อ _csrf และ csrf_token เพราะ AuthController รับได้ทั้งสองชื่อ
 *            ยังไม่มีปุ่ม "เข้าด้วย Google" เพราะยังไม่มี route
 * ============================================================
 */
$layout = 'auth';   // หน้าแบ่งสองฝั่ง (ไม่มีเมนูซ้าย)
require dirname(__DIR__) . '/includes/header.php';
?>

<?php /* ---------- ฝั่งซ้าย: แผงแบรนด์ ---------- */ ?>
<section class="auth__brand" aria-label="เกี่ยวกับระบบ">
    <span class="brand__mark"><?= ui_icon('brand', 32) ?></span>
    <span class="auth__org">คณะพยาบาลศาสตร์</span>
    <h1>ระบบจัดการคลังข้อสอบ<br>และทดสอบออนไลน์</h1>
    <p>ออกข้อสอบ จัดสอบ และดูผลวิเคราะห์ ในระบบเดียว สำหรับอาจารย์ นิสิต และงานวิชาการ</p>
    <ul class="auth__points">
        <li><?= ui_icon('bank', 20) ?>คลังข้อสอบกลางของคณะ</li>
        <li><?= ui_icon('clip', 20) ?>จัดชุดสอบและสอบออนไลน์</li>
        <li><?= ui_icon('chart', 20) ?>ผลสอบและวิเคราะห์ข้อสอบ</li>
    </ul>
</section>

<?php /* ---------- ฝั่งขวา: การ์ดฟอร์ม ---------- */ ?>
<section class="auth__panel">
    <div class="card login-card">
        <div class="login-card__head">
            <h2>เข้าสู่ระบบ</h2>
            <p>ใช้อีเมลมหาวิทยาลัยหรือรหัสนิสิต และรหัสผ่าน</p>
        </div>

        <?php if (!empty($error)): ?>
            <div class="alert alert--danger" role="alert">
                <?= ui_icon('alert') ?>
                <div><b>เข้าสู่ระบบไม่สำเร็จ</b><?= Security::escape($error) ?></div>
            </div>
        <?php endif; ?>

        <?php /* ---------- ฟอร์มเข้าสู่ระบบ ---------- */ ?>
        <form class="form-stack" method="post" action="<?= Security::escape((string) env('APP_URL', '')) ?>/login">
            <input type="hidden" name="_csrf" value="<?= Security::escape(Security::csrfToken()) ?>">
            <input type="hidden" name="csrf_token" value="<?= Security::escape(Security::csrfToken()) ?>">
            <div class="field">
                <label class="field__label" for="identifier">อีเมลหรือรหัสนิสิต</label>
                <input
                    class="input"
                    id="identifier"
                    name="identifier"
                    type="text"
                    value="<?= Security::escape($identifier ?? '') ?>"
                    placeholder="อีเมล หรือรหัสนิสิต 8 หลัก"
                    autocomplete="username"
                    required
                    autofocus
                >
            </div>
            <div class="field">
                <label class="field__label" for="password">รหัสผ่าน</label>
                <input class="input" id="password" name="password" type="password" autocomplete="current-password" required>
            </div>
            <button class="btn btn--primary btn--lg btn--block" type="submit">เข้าสู่ระบบ</button>
        </form>

        <?php /* ---------- คำแนะนำการเข้าสู่ระบบ ---------- */ ?>
        <div class="note-box">
            <div><strong>นิสิต</strong> ใช้อีเมลมหาวิทยาลัยหรือรหัสนิสิต 8 หลัก</div>
            <div><strong>อาจารย์และบุคลากร</strong> ใช้อีเมลมหาวิทยาลัย</div>
            <div class="small">ระบบบันทึกการเข้าสู่ระบบทุกครั้ง ทั้งที่สำเร็จและไม่สำเร็จ</div>
        </div>
    </div>
    <p class="blank__foot">เข้าไม่ได้? ติดต่อผู้ดูแลระบบของคณะ</p>
</section>
<?php require dirname(__DIR__).'/includes/footer.php'; ?>
