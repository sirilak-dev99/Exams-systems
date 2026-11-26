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

<?php /* ==================== 4.1 อาจารย์ (design-exams/หน้าหลักของอาจารย์.png) ====================
 *  ทักทาย + ภาคการศึกษา · แถบประกาศสำคัญ · รายวิชาที่สอน (การ์ด) + ทางลัด · เนื้อหาการเรียนล่าสุด (การ์ด)
 *  ข้อมูลที่ "รับได้" (ไม่บังคับ — HomeController ยังไม่ส่ง ส่วนที่ไม่มีข้อมูลจะแสดงกล่องว่าง):
 *    $currentTerm      ข้อความภาคการศึกษา เช่น '1/2569'
 *    $announcements    [ ['title','detail','date'(Y-m-d)] ... ]  แสดงรายการแรก · $announcementCount จำนวนทั้งหมด
 *    $announcementsHref  path หน้าประกาศทั้งหมด (มีเมื่อไรจึงแสดงลิงก์ "ดูประกาศทั้งหมด")
 *    $courses          [ ['code','name','sections'(เช่น '01, 02'),'is_current'(bool),'href'(ไม่บังคับ)] ... ]  แสดง 3 รายการแรก
 *    $contents         [ ['type'(video|pdf|link|file),'title','course'(เช่น '100113 การพยาบาลผู้ใหญ่ 1'),'date','href'(ไม่บังคับ)] ... ]  แสดง 2 รายการแรก
 * ========================================================================================== */ ?>
<?php if ($view === 'instructor'):
    $announcements = (array)($announcements ?? []);
    $courses       = array_slice((array)($courses ?? []), 0, 3);
    $contents      = array_slice((array)($contents ?? []), 0, 2);
    $annCount      = (int)($announcementCount ?? count($announcements));
    $contentTypes  = ['video' => 'วิดีโอ', 'pdf' => 'ไฟล์ PDF', 'link' => 'ลิงก์', 'file' => 'ไฟล์'];
    // ทางลัด 3 ปุ่มตามดีไซน์ · href = null คือยังไม่มี route (แสดง "เร็วๆ นี้")
    $quick = [
        ['icon' => 'file-plus', 'label' => 'เพิ่มข้อสอบในคลัง', 'href' => null],   // รอ GET /questions/create (ข้อ 22)
        ['icon' => 'clip',      'label' => 'สร้างชุดสอบ',      'href' => Security::hasAnyRole(['instructor', 'admin']) ? '/exams/create' : null],
        ['icon' => 'chart',     'label' => 'ดูผลสอบ',          'href' => null],   // รอหน้าผลสอบ
    ];
    // ลิงก์ "ดู...ทั้งหมด ›" แบบมีลูกศร
    $more = fn (string $href, string $text): string =>
        '<a class="home-more" href="' . Security::escape($appUrl . $href) . '">' . Security::escape($text) . ui_icon('arrow', 18) . '</a>';
?>
    <?php /* ---------- ทักทาย + ภาคการศึกษา + ภาพประกอบ ---------- */ ?>
    <section class="greet greet--art" aria-label="สวัสดี <?= Security::escape($name) ?>">
        <div class="greet__text">
            <h1>สวัสดี, <?= Security::escape($name) ?></h1>
            <p>ติดตามข่าวสาร รายวิชาที่สอน และเนื้อหาการเรียนได้ที่นี่</p>
            <?php if (!empty($currentTerm)): ?>
                <p><span class="chip chip--lg"><?= ui_icon('calendar', 18) ?>ภาคการศึกษา <?= Security::escape((string)$currentTerm) ?> (ปัจจุบัน)</span></p>
            <?php endif; ?>
        </div>
        <svg class="greet__art" viewBox="0 0 390 160" aria-hidden="true" focusable="false">
            <path d="M262 22v20M252 32h20M362 14v30M347 29h30" stroke="#C4B0EE" stroke-width="8" stroke-linecap="round"/>
            <path d="M70 135c-12-40 8-72 46-82-4 40-18 66-46 82z" fill="#C9B5F0"/>
            <path d="M92 140c6-34 34-52 66-48-14 30-36 46-66 48z" fill="#B79DEB"/>
            <path d="M180 140V88c40-10 70-6 90 10 20-16 50-20 90-10v52c-40-8-70-4-90 10-20-14-50-18-90-10z" fill="#FFFFFF"/>
            <path d="M270 98v50" stroke="#D9CCF5" stroke-width="3"/>
            <path d="M176 142h188" stroke="#D9CCF5" stroke-width="6" stroke-linecap="round"/>
        </svg>
    </section>

    <?php /* ---------- แถบประกาศสำคัญ (แสดงรายการล่าสุด 1 รายการ) ---------- */ ?>
    <section class="card announce" aria-label="ประกาศสำคัญ">
        <span class="announce__icon"><?= ui_icon('news', 28) ?></span>
        <h2 class="announce__label">ประกาศสำคัญ</h2>
        <?php if ($announcements): $a = $announcements[0]; ?>
            <div class="announce__body">
                <b><?= Security::escape((string)($a['title'] ?? '')) ?></b>
                <?php if (!empty($a['detail'])): ?><span><?= Security::escape((string)$a['detail']) ?></span><?php endif; ?>
            </div>
            <?php if (!empty($a['date'])): ?><span class="announce__date"><?= ui_icon('calendar', 18) ?><?= Security::escape(thai_date((string)$a['date'], false)) ?></span><?php endif; ?>
            <?php if (!empty($announcementsHref)): ?><?= $more((string)$announcementsHref, 'ดูประกาศทั้งหมด (' . $annCount . ')') ?><?php endif; ?>
        <?php else: ?>
            <div class="announce__body"><span>ยังไม่มีประกาศ — จะแสดงเมื่อระบบประกาศเปิดใช้งาน</span></div>
        <?php endif; ?>
    </section>

    <div class="layout-main">
        <?php /* ---------- รายวิชาที่สอน (การ์ด 3 ใบ) ---------- */ ?>
        <section class="card" aria-label="รายวิชาที่สอน">
            <div class="sec-title"><?= ui_icon('book') ?><h2>รายวิชาที่สอน</h2><?= $canCourse ? $more('/courses', 'ดูรายวิชาทั้งหมด') : '' ?></div>
            <?php if ($courses): ?>
                <ul class="course-cards">
                    <?php foreach ($courses as $c): ?>
                        <li class="course-card">
                            <div class="course-card__img"><?= ui_icon('book', 30) ?></div>
                            <div class="course-card__body">
                                <b><?= Security::escape((string)($c['code'] ?? '')) ?><br><?= Security::escape((string)($c['name'] ?? '')) ?></b>
                                <div class="course-card__tags">
                                    <?php if (!empty($c['sections'])): ?><span class="tag">กลุ่ม <?= Security::escape((string)$c['sections']) ?></span><?php endif; ?>
                                    <?php if (!empty($c['is_current'])): ?><span class="tag tag--warm">ภาคเรียนปัจจุบัน</span><?php endif; ?>
                                </div>
                                <?php if (!empty($c['href'])): ?>
                                    <a class="home-more home-more--center" href="<?= Security::escape($appUrl . $c['href']) ?>">เข้าสู่รายวิชา<?= ui_icon('arrow', 18) ?></a>
                                <?php endif; ?>
                            </div>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php else: ?>
                <?= $empty('ยังไม่มีข้อมูลรายวิชาที่สอน', 'จะแสดงเมื่อมีการกำหนดผู้สอนในกลุ่มเรียน') ?>
            <?php endif; ?>
        </section>

        <?php /* ---------- ทางลัด (3 ปุ่มใหญ่) ---------- */ ?>
        <section class="card shortcuts shortcuts--big" aria-label="ทางลัด">
            <div class="shortcuts__head"><?= ui_icon('bolt') ?><h2>ทางลัด</h2></div>
            <ul>
                <?php foreach ($quick as $q): ?>
                    <li>
                        <?php if ($q['href'] !== null): ?>
                            <a href="<?= Security::escape($appUrl . $q['href']) ?>"><span class="quick__icon"><?= ui_icon($q['icon']) ?></span><?= Security::escape($q['label']) ?><span class="chev"><?= ui_icon('chev', 20) ?></span></a>
                        <?php else: ?>
                            <span class="is-soon"><span class="quick__icon"><?= ui_icon($q['icon']) ?></span><?= Security::escape($q['label']) ?><span class="badge badge--plain">เร็วๆ นี้</span></span>
                        <?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ul>
        </section>
    </div>

    <?php /* ---------- เนื้อหาการเรียนล่าสุด (การ์ดแนวนอน 2 ใบ) ---------- */ ?>
    <section class="card" aria-label="เนื้อหาการเรียนล่าสุด">
        <div class="sec-title"><?= ui_icon('file') ?><h2>เนื้อหาการเรียนล่าสุด</h2></div>
        <?php if ($contents): ?>
            <ul class="content-cards">
                <?php foreach ($contents as $ct): ?>
                    <li class="content-card">
                        <div class="content-card__img"><?= ui_icon($ct['type'] === 'video' ? 'video' : 'file', 30) ?></div>
                        <div class="content-card__body">
                            <span class="tag"><?= Security::escape($contentTypes[$ct['type'] ?? ''] ?? 'เนื้อหา') ?></span>
                            <b><?= Security::escape((string)($ct['title'] ?? '')) ?></b>
                            <span class="muted"><?= Security::escape((string)($ct['course'] ?? '')) ?><?= !empty($ct['date']) ? ' · ' . Security::escape(thai_date((string)$ct['date'], false)) : '' ?></span>
                            <?php if (!empty($ct['href'])): ?><a class="home-more home-more--end" href="<?= Security::escape($appUrl . $ct['href']) ?>">ดูเนื้อหา<?= ui_icon('arrow', 18) ?></a><?php endif; ?>
                        </div>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php else: ?>
            <?= $empty('ยังไม่มีเนื้อหาการเรียน') ?>
        <?php endif; ?>
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
