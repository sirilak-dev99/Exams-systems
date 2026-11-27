<?php
// ---------- ค่าพื้นฐาน ----------
$courseOfferings = $courseOfferings ?? [];
$crumbTitle  = 'หลักสูตรและรายวิชา';
$pageScripts = ['/js/pages/course-offerings.js'];
require dirname(__DIR__) . '/includes/header.php';

// ---------- รายวิชาที่เลือก ----------
$offeringId = (int)($_GET['offering'] ?? 0);
$detail     = null;
foreach ($courseOfferings as $o) {
    if ((int)$o['id_course_offerings'] === $offeringId) {
        $detail = $o;
        break;
    }
}
$canManage = Security::hasPermission('course.manage');

// ---------- ตัวกรอง ----------
$termId   = trim((string)($_GET['academic_terms_id'] ?? ''));
$courseId = trim((string)($_GET['courses_id'] ?? ''));
$status   = (string)($_GET['status'] ?? '');
$filtered = $termId !== '' || $courseId !== '' || $status !== '';

$termOptions   = [];
$courseOptions = [];
foreach ($courseOfferings as $o) {
    $termOptions[(int)$o['academic_terms_id']] = (int)$o['term_no'] . '/' . (int)$o['academic_year'];
    $courseOptions[(int)$o['courses_id']]      = $o['course_code'] . ' ' . $o['course_name'];
}
asort($courseOptions);

// ---------- ข้อความ ----------
$statusLabels  = ['open' => 'เปิดสอน', 'completed' => 'สิ้นสุดภาค', 'closed' => 'ปิดแล้ว'];
$statusFilters = ['open' => 'เปิดสอน', 'closed' => 'ปิดแล้ว'];
$percent = function ($value): string {
    if ($value === null || $value === '') {
        return '—';
    }
    return rtrim(rtrim(number_format((float)$value, 2, '.', ''), '0'), '.') . '%';
};
?>
<?php if ($detail): ?>
<?php /* ---------- เส้นทาง ---------- */ ?>
<nav class="trail" aria-label="ตำแหน่งของหน้า">
    <a class="trail__back" href="<?= Security::escape($appUrl) ?>/course-offerings?<?= Security::escape(http_build_query(['academic_terms_id' => (int)$detail['academic_terms_id']])) ?>">← กลับ</a>
    <a href="<?= Security::escape($appUrl) ?>/programs">หลักสูตรและรายวิชา</a>
    <span aria-hidden="true">›</span>
    <a href="<?= Security::escape($appUrl) ?>/course-offerings?<?= Security::escape(http_build_query(['academic_terms_id' => (int)$detail['academic_terms_id']])) ?>">เปิดสอน <?= (int)$detail['term_no'] ?>/<?= (int)$detail['academic_year'] ?></a>
    <span aria-hidden="true">›</span>
    <b aria-current="page">กลุ่มเรียนและผู้สอน</b>
</nav>

<?php /* ---------- หัวข้อ ---------- */ ?>
<div class="page-title">
    <h1><?= Security::escape((string)$detail['course_code']) ?> <?= Security::escape((string)$detail['course_name']) ?></h1>
    <p>ภาคการศึกษา <?= (int)$detail['term_no'] ?>/<?= (int)$detail['academic_year'] ?> · <?= Security::escape($statusLabels[$detail['status']] ?? (string)$detail['status']) ?> · ผู้สอนเห็นข้อสอบและผลสอบเฉพาะกลุ่มของตัวเอง</p>
</div>

<?php /* ---------- กลุ่มเรียนและผู้สอน ---------- */ ?>
<section class="card groups" id="groupsPanel" data-offering="<?= (int)$detail['id_course_offerings'] ?>" data-can-manage="<?= $canManage ? '1' : '0' ?>">
    <div class="field groups__coord">
        <label class="field__label" for="coordSelect">ผู้ประสานงานรายวิชา</label>
        <select class="select" id="coordSelect" disabled><option value="">กำลังโหลด…</option></select>
        <span class="field__hint">ผู้ประสานงานจัดหัวข้อ ออกข้อสอบ และเห็นผลสอบทุกกลุ่ม</span>
        <span class="field__error" id="coordError" role="alert" hidden></span>
    </div>

    <div class="alert alert--danger" id="groupsError" role="alert" hidden><b></b></div>
    <div id="groupsTable" aria-live="polite"><p class="muted">กำลังโหลด…</p></div>

    <?php if ($canManage): ?>
        <div class="btn-row">
            <button class="btn btn--secondary" type="button" id="sectionAddBtn">+ เพิ่มกลุ่มเรียน</button>
            <button class="btn btn--primary" type="button" disabled title="รอระบบรายชื่อนิสิต">ลงทะเบียนนิสิตในวิชานี้ <small>เร็วๆ นี้</small></button>
        </div>
    <?php endif; ?>
</section>

<?php /* ---------- ฟอร์มกลุ่มเรียน ---------- */ ?>
<div class="modal" id="sectionModal" role="dialog" aria-modal="true" aria-labelledby="sectionModalTitle">
    <form class="modal__box modal__box--sm" id="sectionForm" novalidate>
        <div class="modal__head">
            <h2 id="sectionModalTitle" data-title-new="เพิ่มกลุ่มเรียน" data-title-edit="แก้ไขกลุ่มเรียน">เพิ่มกลุ่มเรียน</h2>
            <button class="modal__close" type="button" data-modal-close aria-label="ปิด">✕</button>
        </div>
        <div class="alert alert--danger" id="sectionFormError" role="alert" hidden><b></b></div>
        <input type="hidden" name="id" value="">
        <div class="field">
            <label class="field__label" for="sectionNo">เลขกลุ่ม<span class="req">*</span></label>
            <input class="input" id="sectionNo" name="section_no" maxlength="10" autocomplete="off" placeholder="เช่น 01" aria-describedby="sectionNoError">
            <span class="field__error" id="sectionNoError" hidden></span>
        </div>
        <div class="modal__foot">
            <button class="btn btn--ghost" type="button" data-modal-close>ยกเลิก</button>
            <button class="btn btn--primary" type="submit">บันทึก</button>
        </div>
    </form>
</div>

<?php /* ---------- รายชื่อนิสิต ---------- */ ?>
<div class="modal" id="membersModal" role="dialog" aria-modal="true" aria-labelledby="membersModalTitle">
    <div class="modal__box modal__box--lg">
        <div class="modal__head">
            <div class="grow">
                <h2 id="membersModalTitle">รายชื่อนิสิต</h2>
                <p class="muted" id="membersModalSub"></p>
            </div>
            <button class="modal__close" type="button" data-modal-close aria-label="ปิด">✕</button>
        </div>
        <div class="alert alert--danger" id="membersError" role="alert" hidden><b></b></div>
        <div id="membersList" aria-live="polite"></div>
        <p class="muted small">นิสิตที่นำออกยังเห็นผลสอบเดิมของตัวเอง</p>
    </div>
</div>
<?php else: ?>
<?php $catalogTab = '/course-offerings'; require dirname(__DIR__) . '/includes/catalog-tabs.php'; ?>

<section class="card card--flush">
    <?php /* ---------- ตัวกรอง ---------- */ ?>
    <form class="toolbar" method="get" action="<?= Security::escape($appUrl) ?>/course-offerings" role="search">
        <div class="field field--sm">
            <label class="field__label" for="fTerm">ภาคการศึกษา</label>
            <select class="select" id="fTerm" name="academic_terms_id">
                <option value="">ทุกภาค</option>
                <?php foreach ($termOptions as $id => $label): ?>
                    <option value="<?= $id ?>"<?= $termId === (string)$id ? ' selected' : '' ?>><?= Security::escape($label) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="field">
            <label class="field__label" for="fCourse">รายวิชา</label>
            <select class="select" id="fCourse" name="courses_id">
                <option value="">ทุกรายวิชา</option>
                <?php foreach ($courseOptions as $id => $label): ?>
                    <option value="<?= $id ?>"<?= $courseId === (string)$id ? ' selected' : '' ?>><?= Security::escape($label) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="field field--sm">
            <label class="field__label" for="fStatus">สถานะ</label>
            <select class="select" id="fStatus" name="status">
                <option value="">ทุกสถานะ</option>
                <?php foreach ($statusFilters as $value => $label): ?>
                    <option value="<?= Security::escape($value) ?>"<?= $status === $value ? ' selected' : '' ?>><?= Security::escape($label) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="btn-row">
            <button class="btn btn--primary" type="submit">ค้นหา</button>
            <?php if ($filtered): ?><a class="btn btn--ghost" href="<?= Security::escape($appUrl) ?>/course-offerings">ล้างตัวกรอง</a><?php endif; ?>
        </div>
    </form>

    <?php /* ---------- ตาราง ---------- */ ?>
    <?php if (!$courseOfferings): ?>
        <div class="empty"><?= ui_icon('inbox', 40) ?><b><?= $filtered ? 'ไม่พบรายวิชาที่เปิดสอนตามเงื่อนไข' : 'ยังไม่มีรายวิชาที่เปิดสอน' ?></b></div>
    <?php else: ?>
        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr>
                        <th scope="col">รหัสวิชา</th>
                        <th scope="col">ชื่อรายวิชา</th>
                        <th scope="col">ภาค/ปี</th>
                        <th scope="col" class="num">เกณฑ์ผ่าน</th>
                        <th scope="col" class="num">กลุ่มเรียน</th>
                        <th scope="col">สถานะ</th>
                        <th scope="col"><span class="sr-only">จัดการ</span></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($courseOfferings as $o): ?>
                        <tr>
                            <td class="code nowrap"><?= Security::escape((string)$o['course_code']) ?></td>
                            <td><?= Security::escape((string)$o['course_name']) ?></td>
                            <td class="nowrap"><?= (int)$o['term_no'] ?>/<?= (int)$o['academic_year'] ?></td>
                            <td class="num"><?= Security::escape($percent($o['pass_percent'] ?? null)) ?></td>
                            <td class="num"><?= (int)$o['sections_count'] ?></td>
                            <td><span class="badge badge--offering-<?= Security::escape((string)$o['status']) ?>"><?= Security::escape($statusLabels[$o['status']] ?? (string)$o['status']) ?></span></td>
                            <td class="nowrap"><a class="btn btn--secondary btn--sm" href="<?= Security::escape($appUrl) ?>/course-offerings?offering=<?= (int)$o['id_course_offerings'] ?>" aria-label="กลุ่มเรียนและผู้สอนของ <?= Security::escape((string)$o['course_name']) ?>">กลุ่มเรียน · ผู้สอน</a></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <div class="table-foot">ทั้งหมด <?= count($courseOfferings) ?> รายการ</div>
    <?php endif; ?>
</section>
<?php endif; ?>
<?php require dirname(__DIR__) . '/includes/footer.php'; ?>
