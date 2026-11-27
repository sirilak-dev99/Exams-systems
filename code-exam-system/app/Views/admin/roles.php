<?php
// ---------- ค่าพื้นฐาน ----------
$crumbTitle = 'ผู้ใช้และสิทธิ์';
require dirname(__DIR__) . '/includes/header.php';

// ---------- บทบาทและสิทธิ์ ----------
$roles = [
    'student'        => ['นิสิต', 'นิสิตผู้เข้าสอบ'],
    'instructor'     => ['อาจารย์ผู้สอน', 'ผู้สอน ผู้ออกข้อสอบ และผู้ตรวจข้อสอบ'],
    'academic_staff' => ['เจ้าหน้าที่ฝ่ายวิชาการ', 'จัดการหลักสูตร รายวิชา และการเปิดสอน'],
    'admin'          => ['ผู้ดูแลระบบ', 'ได้รับสิทธิ์ทุกโมดูลโดยอัตโนมัติ'],
];
$permissionGroups = [
    'การจัดการระบบและผู้ใช้' => [
        'user.manage'     => 'จัดการบัญชีผู้ใช้และบทบาท',
        'system.settings' => 'ตั้งค่าระบบ',
    ],
    'ข้อมูลหลักการศึกษา' => [
        'program.manage' => 'จัดการหลักสูตรและฉบับหลักสูตร',
        'course.manage'  => 'จัดการรายวิชา การเปิดสอน และกลุ่มเรียน',
        'course.view'    => 'ดูรายวิชาและรายชื่อในกลุ่มเรียน',
    ],
    'คลังข้อสอบและชุดสอบ' => [
        'question.manage' => 'สร้างและแก้ไขข้อสอบในคลัง',
        'exam.manage'     => 'สร้างและเผยแพร่ชุดสอบ',
        'exam.grade'      => 'ตรวจและให้คะแนนข้อสอบ',
    ],
    'การเข้าสอบของนิสิต' => [
        'attempt.take'     => 'เข้าสอบ บันทึกคำตอบ และส่งข้อสอบ',
        'attempt.view_own' => 'ดูผลสอบของตัวเอง',
    ],
    'รายงานและสถิติ' => [
        'report.view' => 'ดูรายงานคะแนน สถิติข้อสอบ และส่งออกไฟล์',
    ],
];
?>
<?php /* ---------- หัวข้อ ---------- */ ?>
<div class="page-title">
    <h1><?= Security::escape($pageTitle) ?></h1>
    <p>บทบาทในระบบและรหัสสิทธิ์ที่ใช้ตรวจการเข้าถึงแต่ละส่วน</p>
</div>

<?php /* ---------- แท็บ ---------- */ ?>
<nav class="tabs" aria-label="ผู้ใช้และสิทธิ์">
    <a href="<?= Security::escape($appUrl) ?>/admin/users">ผู้ใช้</a>
    <a href="<?= Security::escape($appUrl) ?>/admin/roles" aria-current="page">บทบาทและสิทธิ์</a>
</nav>

<?php /* ---------- บทบาท ---------- */ ?>
<section class="card card--flush">
    <div class="card__head"><h2>บทบาท</h2><p>ผู้ใช้หนึ่งคนมีได้หลายบทบาท</p></div>
    <div class="table-wrap">
        <table class="table">
            <thead>
                <tr>
                    <th scope="col">บทบาท</th>
                    <th scope="col">รหัส</th>
                    <th scope="col">หน้าที่</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($roles as $key => [$name, $desc]): ?>
                    <tr>
                        <td class="nowrap"><b><?= Security::escape($name) ?></b></td>
                        <td class="code nowrap"><?= Security::escape($key) ?></td>
                        <td><?= Security::escape($desc) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>

<?php /* ---------- สิทธิ์ ---------- */ ?>
<section class="card card--flush">
    <div class="card__head"><h2>รหัสสิทธิ์</h2><p>การกำหนดสิทธิ์ให้แต่ละบทบาทจะเปิดใช้เมื่อมี API บทบาทและสิทธิ์</p></div>
    <div class="table-wrap">
        <table class="table">
            <thead>
                <tr>
                    <th scope="col">รหัสสิทธิ์</th>
                    <th scope="col">ใช้ทำอะไร</th>
                </tr>
            </thead>
            <?php foreach ($permissionGroups as $group => $items): ?>
                <tbody>
                    <tr class="table__group"><th scope="rowgroup" colspan="2"><?= Security::escape($group) ?></th></tr>
                    <?php foreach ($items as $code => $desc): ?>
                        <tr>
                            <td class="code nowrap"><?= Security::escape($code) ?></td>
                            <td><?= Security::escape($desc) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            <?php endforeach; ?>
        </table>
    </div>
</section>
<?php require dirname(__DIR__) . '/includes/footer.php'; ?>
