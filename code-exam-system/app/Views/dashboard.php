<?php
// ---------- ค่าพื้นฐาน ----------
$user      = $user ?? Security::currentUser() ?? [];
$pageTitle = 'หน้าหลัก';
require __DIR__ . '/includes/header.php';

$name          = (string)($user['display_name'] ?? '');
$isTeacher     = in_array('instructor', (array)($user['roles'] ?? []), true);
$currentTerm   = $currentTerm ?? null;
$announcements = (array)($announcements ?? []);
$courses       = array_slice((array)($courses ?? []), 0, 3);
$contents      = array_slice((array)($contents ?? []), 0, 2);
$contentTypes  = ['video' => 'วิดีโอ', 'pdf' => 'ไฟล์ PDF', 'link' => 'ลิงก์', 'file' => 'ไฟล์'];

// ---------- ทางลัด: [ไอคอน, ชื่อ, path หรือ null] ----------
$quick = [
    ['file-plus', 'เพิ่มข้อสอบในคลัง', null],
    ['clip',      'สร้างชุดสอบ',      null],
    ['chart',     'ดูผลสอบ',          null],
];

// ---------- ส่วนประกอบ ----------
$empty = fn (string $title, string $hint = 'จะแสดงเมื่อระบบส่วนนี้เปิดใช้งาน'): string =>
    '<div class="empty empty--sm">' . ui_icon('inbox', 32) . '<b>' . Security::escape($title) . '</b><span>' . Security::escape($hint) . '</span></div>';
$more = fn (string $href, string $text): string =>
    '<a class="home-more" href="' . Security::escape($appUrl . $href) . '">' . Security::escape($text) . ui_icon('arrow', 18) . '</a>';
?>
<?php /* ---------- ทักทาย ---------- */ ?>
<section class="greet greet--art" aria-label="สวัสดี <?= Security::escape($name) ?>">
    <div class="greet__text">
        <h1>สวัสดี, <?= Security::escape($name) ?></h1>
        <p>ติดตามข่าวสาร รายวิชาที่สอน และเนื้อหาการเรียนได้ที่นี่</p>
        <?php if ($currentTerm !== null): ?>
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

<?php /* ---------- ประกาศสำคัญ ---------- */ ?>
<section class="card announce" aria-label="ประกาศสำคัญ">
    <span class="announce__icon"><?= ui_icon('news', 28) ?></span>
    <h2 class="announce__label">ประกาศสำคัญ</h2>
    <?php if ($announcements): $a = $announcements[0]; ?>
        <div class="announce__body">
            <b><?= Security::escape((string)($a['title'] ?? '')) ?></b>
            <?php if (!empty($a['detail'])): ?><span><?= Security::escape((string)$a['detail']) ?></span><?php endif; ?>
        </div>
        <?php if (!empty($a['date'])): ?><span class="announce__date"><?= ui_icon('calendar', 18) ?><?= Security::escape(thai_date((string)$a['date'])) ?></span><?php endif; ?>
    <?php else: ?>
        <div class="announce__body"><span>ยังไม่มีประกาศ</span></div>
    <?php endif; ?>
</section>

<?php if ($isTeacher): ?>
<div class="layout-main">
    <?php /* ---------- รายวิชาที่สอน ---------- */ ?>
    <section class="card" aria-label="รายวิชาที่สอน">
        <div class="sec-title"><?= ui_icon('book') ?><h2>รายวิชาที่สอน</h2></div>
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

    <?php /* ---------- ทางลัด ---------- */ ?>
    <section class="card shortcuts shortcuts--big" aria-label="ทางลัด">
        <div class="shortcuts__head"><?= ui_icon('bolt') ?><h2>ทางลัด</h2></div>
        <ul>
            <?php foreach ($quick as [$icon, $label, $href]): ?>
                <li>
                    <?php if ($href !== null): ?>
                        <a href="<?= Security::escape($appUrl . $href) ?>"><span class="quick__icon"><?= ui_icon($icon) ?></span><?= Security::escape($label) ?><span class="chev"><?= ui_icon('chev', 20) ?></span></a>
                    <?php else: ?>
                        <span class="is-soon"><span class="quick__icon"><?= ui_icon($icon) ?></span><?= Security::escape($label) ?><span class="badge badge--plain">เร็วๆ นี้</span></span>
                    <?php endif; ?>
                </li>
            <?php endforeach; ?>
        </ul>
    </section>
</div>

<?php /* ---------- เนื้อหาการเรียนล่าสุด ---------- */ ?>
<section class="card" aria-label="เนื้อหาการเรียนล่าสุด">
    <div class="sec-title"><?= ui_icon('file') ?><h2>เนื้อหาการเรียนล่าสุด</h2></div>
    <?php if ($contents): ?>
        <ul class="content-cards">
            <?php foreach ($contents as $ct): ?>
                <li class="content-card">
                    <div class="content-card__img"><?= ui_icon(($ct['type'] ?? '') === 'video' ? 'play' : 'file', 30) ?></div>
                    <div class="content-card__body">
                        <span class="tag"><?= Security::escape($contentTypes[$ct['type'] ?? ''] ?? 'เนื้อหา') ?></span>
                        <b><?= Security::escape((string)($ct['title'] ?? '')) ?></b>
                        <span class="muted"><?= Security::escape((string)($ct['course'] ?? '')) ?><?= !empty($ct['date']) ? ' · ' . Security::escape(thai_date((string)$ct['date'])) : '' ?></span>
                        <?php if (!empty($ct['href'])): ?><a class="home-more home-more--end" href="<?= Security::escape($appUrl . $ct['href']) ?>">ดูเนื้อหา<?= ui_icon('arrow', 18) ?></a><?php endif; ?>
                    </div>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php else: ?>
        <?= $empty('ยังไม่มีเนื้อหาการเรียน') ?>
    <?php endif; ?>
</section>
<?php endif; ?>
<?php require __DIR__ . '/includes/footer.php'; ?>
