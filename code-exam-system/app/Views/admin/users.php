<?php
// ---------- ค่าพื้นฐาน ----------
$crumbTitle = 'ผู้ใช้และสิทธิ์';
require dirname(__DIR__) . '/includes/header.php';
?>
<?php /* ---------- หัวข้อ ---------- */ ?>
<div class="page-head">
    <div class="page-title">
        <h1><?= Security::escape($pageTitle) ?></h1>
        <p>บัญชีผู้ใช้ บทบาท และการเปิดหรือปิดการใช้งาน</p>
    </div>
    <button class="btn btn--primary" type="button" disabled title="รอ API ผู้ใช้">+ เพิ่มผู้ใช้ <small>เร็วๆ นี้</small></button>
</div>

<?php /* ---------- แท็บ ---------- */ ?>
<nav class="tabs" aria-label="ผู้ใช้และสิทธิ์">
    <a href="<?= Security::escape($appUrl) ?>/admin/users" aria-current="page">ผู้ใช้</a>
    <a href="<?= Security::escape($appUrl) ?>/admin/roles">บทบาทและสิทธิ์</a>
</nav>

<?php /* ---------- รายการ ---------- */ ?>
<section class="card card--flush">
    <div class="toolbar" role="search">
        <div class="field">
            <label class="field__label" for="uSearch">ค้นหา</label>
            <input class="input" id="uSearch" type="search" placeholder="ชื่อ อีเมล หรือรหัสนิสิต" disabled>
        </div>
        <div class="field field--sm">
            <label class="field__label" for="uRole">บทบาท</label>
            <select class="select" id="uRole" disabled>
                <option>ทุกบทบาท</option>
            </select>
        </div>
    </div>
    <div class="card__body">
        <?php $pendingApi = 'ตาราง users และ user_roles มีในฐานข้อมูลแล้ว แต่ยังไม่มี endpoint รายการผู้ใช้ (สิทธิ์ user.manage)'; require dirname(__DIR__) . '/includes/api-pending.php'; ?>
    </div>
    <div class="table-wrap">
        <table class="table">
            <thead>
                <tr>
                    <th scope="col">ชื่อ</th>
                    <th scope="col">อีเมล</th>
                    <th scope="col">รหัสนิสิต</th>
                    <th scope="col">บทบาท</th>
                    <th scope="col">สถานะ</th>
                </tr>
            </thead>
        </table>
    </div>
    <div class="empty"><?= ui_icon('users', 40) ?><b>ยังไม่มีข้อมูลผู้ใช้ที่แสดง</b></div>
</section>
<?php require dirname(__DIR__) . '/includes/footer.php'; ?>
