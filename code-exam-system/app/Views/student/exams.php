<?php
// ---------- ค่าพื้นฐาน ----------
$crumbTitle = 'การสอบของฉัน';
require dirname(__DIR__) . '/includes/header.php';
?>
<?php /* ---------- หัวข้อ ---------- */ ?>
<div class="page-title">
    <h1><?= Security::escape($pageTitle) ?></h1>
    <p>การสอบที่เปิดให้เข้าสอบ และกำหนดการสอบที่จะมาถึง</p>
</div>

<?php /* ---------- ขั้นตอนเข้าสอบ ---------- */ ?>
<section class="card">
    <div class="card__head"><h2>ก่อนเข้าสอบ</h2></div>
    <ol class="steps">
        <li><b>ตรวจช่วงเวลาสอบ</b><span>เข้าสอบได้เฉพาะช่วงเวลาที่อาจารย์เปิดไว้</span></li>
        <li><b>รับรหัสเข้าสอบ 5 ตัวอักษร</b><span>บางชุดสอบต้องใช้รหัสจากผู้คุมสอบ</span></li>
        <li><b>ทำข้อสอบและส่ง</b><span>ระบบบันทึกคำตอบอัตโนมัติ หากหลุดการเชื่อมต่อสามารถกลับมาทำต่อได้</span></li>
    </ol>
</section>

<?php /* ---------- รายการ ---------- */ ?>
<section class="card">
    <div class="card__head"><h2>ชุดสอบของฉัน</h2></div>
    <?php $pendingApi = 'การเริ่มสอบ บันทึกคำตอบ และส่งข้อสอบมี API แล้ว (/exams/{id}/attempts/start, /attempts/{id}) แต่ยังไม่มี endpoint รายการชุดสอบที่นิสิตมีสิทธิ์สอบ'; require dirname(__DIR__) . '/includes/api-pending.php'; ?>
    <div class="empty"><?= ui_icon('clip', 40) ?><b>ยังไม่มีการสอบที่เปิดให้เข้าสอบ</b></div>
</section>
<?php require dirname(__DIR__) . '/includes/footer.php'; ?>
