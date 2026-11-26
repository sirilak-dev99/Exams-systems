<?php require __DIR__.'/includes/header.php'; ?>
<?php
/**
 * ============================================================
 *  หน้า        : หน้าหลัก (แสดงต่างกันตามบทบาท)
 *  Route       : GET / → HomeController@index
 *  แบบหน้าจอ   : อาจารย์ = Main.html · นิสิต = StudentHomeDesk.html · แอดมิน/ฝ่ายวิชาการ = AdminDashboard.html
 * ------------------------------------------------------------
 *  ข้อมูลที่ได้รับ
 *    $user  ผู้ใช้ที่เข้าสู่ระบบ [display_name, email, student_code, roles]
 *  ข้อมูลอื่น (ประกาศ, การสอบ, ผลสอบ ฯลฯ) HomeController ยังไม่ได้ส่งมา
 *  → แสดงกล่อง "ยังไม่มีข้อมูล" แทน ไม่ใส่ข้อมูลสมมติ
 *
 *  ลำดับในไฟล์
 *    1. เลือกว่าจะแสดงหน้าหลักแบบไหน ($view)
 *    2. เตรียมรายการทางลัดจากเมนู (includes/menu.php)
 *    3. ฟังก์ชันช่วยวาดกล่อง ($empty, $secTitle, $shortcuts)
 *    4. หน้าตาแต่ละบทบาท: อาจารย์ / นิสิต / แอดมิน-ฝ่ายวิชาการ / ยังไม่มีบทบาท
 * ============================================================
 */
$appUrl = rtrim((string) env('APP_URL', ''), '/');
require_once __DIR__ . '/includes/menu.php';

// ---------- 1. เลือกแบบหน้าหลัก: มีหลายบทบาท ให้อาจารย์มาก่อน แล้วแอดมิน/ฝ่ายวิชาการ แล้วนิสิต ----------
$roles = (array)($user['roles'] ?? []);
$view  = match (true) {
    in_array('instructor', $roles, true)                               => 'instructor',
    (bool)array_intersect($roles, ['admin', 'academic_staff'])         => 'staff',
    in_array('student', $roles, true)                                  => 'student',
    default                                                            => 'none',
};

// ---------- 2. ทางลัด = เมนูของผู้ใช้ (ไม่รวมหน้าหลัก) · $liveItems = เฉพาะเมนูที่มี route แล้ว ----------
$menuItems = [];
foreach (app_menu($user) as $items) {
    foreach ($items as $item) {
        if ($item['href'] !== '/') {
            $menuItems[] = $item;
        }
    }
}
$liveItems = array_values(array_filter($menuItems, fn ($i) => $i['href'] !== null));

$roleLabels = ['student' => 'นิสิต', 'instructor' => 'อาจารย์', 'academic_staff' => 'เจ้าหน้าที่ฝ่ายวิชาการ', 'admin' => 'ผู้ดูแลระบบ'];
$roleText   = implode(' · ', array_map(fn ($r) => $roleLabels[$r] ?? $r, $roles));
$name       = (string)($user['display_name'] ?? '');

// ---------- 3. ฟังก์ชันช่วยวาดกล่อง ----------
/** กล่อง "ยังไม่มีข้อมูล" ขนาดเล็ก */
$empty = function (string $title, string $hint = 'จะแสดงเมื่อระบบส่วนนี้เปิดใช้งาน'): string {
    return '<div class="empty empty--sm">' . ui_icon('inbox', 32) . '<b>' . Security::escape($title) . '</b><span>' . Security::escape($hint) . '</span></div>';
};
/** หัวการ์ดแบบมีไอคอน + ลิงก์ (ถ้ามี) */
$secTitle = function (string $icon, string $title, ?string $href = null, string $linkText = '') use ($appUrl): string {
    $link = $href !== null ? '<a href="' . Security::escape($appUrl . $href) . '">' . Security::escape($linkText) . ' ›</a>' : '';
    return '<div class="sec-title">' . ui_icon($icon) . '<h2>' . Security::escape($title) . '</h2>' . $link . '</div>';
};
/** รายการทางลัดจากเมนู */
$shortcuts = function (array $items) use ($appUrl): string {
    $out = '';
    foreach ($items as $i) {
        $out .= $i['href'] !== null
            ? '<li><a href="' . Security::escape($appUrl . $i['href']) . '">' . ui_icon($i['icon']) . Security::escape($i['label']) . '<span class="chev">' . ui_icon('chev', 18) . '</span></a></li>'
            : '<li><span class="is-soon">' . ui_icon($i['icon']) . Security::escape($i['label']) . '<span class="badge badge--plain">เร็วๆ นี้</span></span></li>';
    }
    return $out;
};
$canCourse = Security::hasPermission('course.view');
?>

<?php /* ==================== 4.1 อาจารย์ (Main.html): ทักทาย · ประกาศ · รายวิชาที่สอน + ทางลัด · เนื้อหาล่าสุด ==================== */ ?>
<?php if ($view === 'instructor'): ?>
    <section class="greet" aria-label="สวัสดี <?= Security::escape($name) ?>">
        <div class="greet__text">
            <h1>สวัสดี, <?= Security::escape($name) ?></h1>
            <p>ติดตามข่าวสาร รายวิชาที่สอน และเนื้อหาการเรียนได้ที่นี่</p>
        </div>
        <div class="greet__meta"><span class="chip"><?= ui_icon('user', 18) ?><?= Security::escape($roleText) ?></span><span class="chip"><?= Security::escape($user['email'] ?? '') ?></span></div>
    </section>

    <section class="card" aria-label="ประกาศสำคัญ">
        <?= $secTitle('news', 'ประกาศสำคัญ') ?>
        <?= $empty('ยังไม่มีประกาศ') ?>
    </section>

    <div class="layout-main">
        <section class="card" aria-label="รายวิชาที่สอน">
            <?= $secTitle('book', 'รายวิชาที่สอน', $canCourse ? '/courses' : null, 'ดูรายวิชาทั้งหมด') ?>
            <?= $empty('ยังไม่มีข้อมูลรายวิชาที่สอน', 'จะแสดงเมื่อมีการกำหนดผู้สอนในกลุ่มเรียน') ?>
        </section>
        <section class="card shortcuts" aria-label="ทางลัด">
            <div class="shortcuts__head"><h2>ทางลัด</h2></div>
            <ul><?= $shortcuts($menuItems) ?></ul>
        </section>
    </div>

    <section class="card" aria-label="เนื้อหาการเรียนล่าสุด">
        <?= $secTitle('video', 'เนื้อหาการเรียนล่าสุด') ?>
        <?= $empty('ยังไม่มีเนื้อหาการเรียน') ?>
    </section>

<?php /* ==================== 4.2 นิสิต (StudentHomeDesk.html): ทักทาย · ประกาศ · สอบได้ตอนนี้ · กำลังจะเปิด · ผลสอบล่าสุด ==================== */ ?>
<?php elseif ($view === 'student'): ?>
    <section class="greet" aria-label="สวัสดี <?= Security::escape($name) ?>">
        <div class="greet__text">
            <h1>สวัสดี <?= Security::escape($name) ?></h1>
            <p>ดูการสอบที่เปิดอยู่ ประกาศ และผลสอบล่าสุดได้ที่นี่</p>
        </div>
        <div class="greet__meta">
            <?php if (!empty($user['student_code'])): ?><span class="chip">รหัสนิสิต <?= Security::escape($user['student_code']) ?></span><?php endif; ?>
            <span class="chip"><?= Security::escape($user['email'] ?? '') ?></span>
        </div>
    </section>

    <section class="card" aria-label="ประกาศจากคณะ">
        <?= $secTitle('news', 'ประกาศจากคณะ') ?>
        <?= $empty('ยังไม่มีประกาศ') ?>
    </section>

    <div class="layout-main">
        <section class="card" aria-label="สอบได้ตอนนี้">
            <?= $secTitle('play', 'สอบได้ตอนนี้') ?>
            <?= $empty('ยังไม่มีชุดสอบที่เปิดให้สอบ', 'เมื่ออาจารย์เปิดสอบ ชุดสอบจะแสดงที่นี่ พร้อมปุ่มเริ่มทำข้อสอบ') ?>
        </section>
        <div class="form-stack">
            <section class="card" aria-label="กำลังจะเปิด">
                <?= $secTitle('calendar', 'กำลังจะเปิด') ?>
                <?= $empty('ยังไม่มีการสอบที่กำลังจะเปิด') ?>
            </section>
            <section class="card" aria-label="ผลสอบล่าสุด">
                <?= $secTitle('chart', 'ผลสอบล่าสุด') ?>
                <?= $empty('ยังไม่มีผลสอบ') ?>
            </section>
        </div>
    </div>

<?php /* ==================== 4.3 แอดมิน / ฝ่ายวิชาการ (AdminDashboard.html): ทางลัด · ประกาศ · กิจกรรม · กำลังสอบตอนนี้ ==================== */ ?>
<?php elseif ($view === 'staff'): ?>
    <div class="page-head">
        <div class="page-head__text">
            <h1>Dashboard</h1>
            <p>ภาพรวมระบบ · <?= Security::escape($name) ?> (<?= Security::escape($roleText) ?>)</p>
        </div>
    </div>

    <?php if ($liveItems): ?>
        <section aria-labelledby="quick-title">
            <h2 id="quick-title" class="sr-only">เมนูที่ใช้งานได้</h2>
            <div class="tiles">
                <?php foreach ($liveItems as $t): ?>
                    <a class="tile" href="<?= Security::escape($appUrl . $t['href']) ?>">
                        <span class="tile__icon"><?= ui_icon($t['icon'], 24) ?></span>
                        <span class="tile__text"><b><?= Security::escape($t['label']) ?></b><span><?= Security::escape($t['desc']) ?></span></span>
                    </a>
                <?php endforeach; ?>
            </div>
        </section>
    <?php endif; ?>

    <div class="layout-main">
        <section class="card" aria-label="ประกาศล่าสุด">
            <?= $secTitle('news', 'ประกาศล่าสุด') ?>
            <?= $empty('ยังไม่มีประกาศ') ?>
        </section>
        <section class="card" aria-label="กิจกรรมล่าสุดในระบบ">
            <?= $secTitle('log', 'กิจกรรมล่าสุดในระบบ') ?>
            <?= $empty('ยังไม่มีข้อมูล') ?>
        </section>
    </div>

    <section class="card card--flush" aria-label="กำลังสอบตอนนี้">
        <div class="card__head"><div class="sec-title"><?= ui_icon('monitor') ?><h2>กำลังสอบตอนนี้</h2></div></div>
        <div class="table-wrap">
            <table class="table">
                <thead><tr><th scope="col">ชุดสอบ</th><th scope="col">รายวิชา</th><th scope="col">ปิดรับ</th><th scope="col" class="num">ส่งแล้ว</th></tr></thead>
            </table>
        </div>
        <?= $empty('ไม่มีชุดสอบที่กำลังสอบอยู่ตอนนี้') ?>
    </section>

<?php /* ==================== 4.4 ยังไม่มีบทบาท ==================== */ ?>
<?php else: ?>
    <section class="greet">
        <div class="greet__text"><h1>สวัสดี <?= Security::escape($name) ?></h1><p><?= Security::escape($user['email'] ?? '') ?></p></div>
    </section>
    <div class="alert" role="status">
        <?= ui_icon('alert') ?>
        <div><b>ยังไม่มีเมนูที่ใช้งานได้</b>บัญชีนี้ยังไม่ได้รับบทบาทหรือสิทธิ์ใช้งาน กรุณาติดต่อผู้ดูแลระบบ</div>
    </div>
<?php endif; ?>
<?php require __DIR__.'/includes/footer.php'; ?>
