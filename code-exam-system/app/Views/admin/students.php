<?php
// ---------- ค่าพื้นฐาน ----------
$crumbTitle  = 'จัดการนิสิต';
$pageScripts = ['/js/pages/admin-students.js'];
require dirname(__DIR__) . '/includes/header.php';
?>
<?php /* ---------- หัวข้อ ---------- */ ?>
<div class="page-head">
    <div class="page-title">
        <h1><?= Security::escape($pageTitle) ?></h1>
        <p>รายชื่อนิสิตในแต่ละรายวิชาและกลุ่มเรียน</p>
    </div>
    <a class="btn btn--secondary" href="<?= Security::escape($appUrl) ?>/import/students"><?= ui_icon('file-plus', 20) ?> นำเข้านิสิตจาก Excel</a>
</div>

<?php /* ---------- รายชื่อ ---------- */ ?>
<section class="card card--flush">
    <div class="toolbar">
        <div class="field field--sm">
            <label class="field__label" for="sTerm">ภาคการศึกษา</label>
            <select class="select" id="sTerm"><option value="">กำลังโหลด…</option></select>
        </div>
        <div class="field">
            <label class="field__label" for="sOffering">รายวิชา</label>
            <select class="select" id="sOffering" disabled><option value="">เลือกภาคการศึกษาก่อน</option></select>
        </div>
        <div class="field field--sm">
            <label class="field__label" for="sSection">กลุ่มเรียน</label>
            <select class="select" id="sSection" disabled><option value="">ทุกกลุ่ม</option></select>
        </div>
        <div class="field field--sm">
            <label class="field__label" for="sSearch">ค้นหา</label>
            <input class="input" id="sSearch" type="search" placeholder="ชื่อหรือรหัสนิสิต" autocomplete="off">
        </div>
    </div>
    <div id="studentList" aria-live="polite"><div class="empty"><?= ui_icon('users', 40) ?><b>เลือกรายวิชาเพื่อดูรายชื่อนิสิต</b></div></div>
</section>
<?php require dirname(__DIR__) . '/includes/footer.php'; ?>
