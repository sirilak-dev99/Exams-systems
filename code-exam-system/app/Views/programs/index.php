<?php
// ---------- ค่าพื้นฐาน ----------
$programs    = $programs ?? [];
$crumbTitle  = 'หลักสูตรและรายวิชา';
$pageScripts = ['/js/pages/programs.js'];
require dirname(__DIR__) . '/includes/header.php';

// ---------- ข้อความ ----------
$statusLabels = ['active' => 'ใช้งาน', 'inactive' => 'ปิดใช้งาน', 'archived' => 'จัดเก็บแล้ว'];

// ---------- ตัวกรอง ----------
$status   = (string)($_GET['status'] ?? '');
$status   = isset($statusLabels[$status]) ? $status : '';
$pageUrl  = static function (array $query) use ($appUrl): string {
    $query = array_filter($query, static fn ($v) => $v !== '' && $v !== null);
    return $appUrl . '/programs' . ($query ? '?' . http_build_query($query) : '');
};

// ---------- หลักสูตรที่เลือก ----------
$selectedId = (int)($_GET['program'] ?? 0);
$selected   = null;
foreach ($programs as $p) {
    if ((int)$p['id_programs'] === $selectedId) {
        $selected = $p;
        break;
    }
}
$selected = $selected ?? ($programs[0] ?? null);

// ---------- ลิงก์หน้าอื่น ----------
$links = array_filter([
    ['/course-groups', 'กลุ่มวิชา', Security::hasPermission('course.manage')],
    ['/courses', 'รายวิชาทั้งหมด', Security::hasPermission('course.view')],
    ['/academic-terms', 'ภาคการศึกษา', Security::hasPermission('course.view')],
    ['/course-offerings', 'เปิดสอนรายวิชา', Security::hasPermission('course.view')],
], static fn ($l) => $l[2]);
?>
<?php /* ---------- แบนเนอร์ ---------- */ ?>
<section class="greet hub-hero" aria-label="หลักสูตรและรายวิชา">
    <span class="dash-hero__icon"><?= ui_icon('school', 64) ?></span>
    <div class="greet__text">
        <h1>หลักสูตรและรายวิชา</h1>
        <p>ทุกหลักสูตรของคณะ · เปิดภาคการศึกษา จัดรายวิชา กลุ่มเรียน และผู้สอน</p>
        <?php if ($links): ?>
            <nav class="hub-hero__links" aria-label="หน้าที่เกี่ยวข้อง">
                <?php foreach ($links as [$href, $label]): ?>
                    <a href="<?= Security::escape($appUrl . $href) ?>"><?= Security::escape($label) ?></a>
                <?php endforeach; ?>
            </nav>
        <?php endif; ?>
    </div>
    <div class="hub-hero__actions">
        <button class="btn btn--primary" type="button" data-program-new>+ เพิ่มหลักสูตร</button>
    </div>
</section>

<?php /* ---------- ตัวกรองสถานะ ---------- */ ?>
<section class="card pill-bar" aria-label="สถานะหลักสูตร">
    <span class="pill-bar__label">สถานะ</span>
    <div class="pills">
        <a class="pill" href="<?= Security::escape($pageUrl([])) ?>"<?= $status === '' ? ' aria-current="page"' : '' ?>>ทุกสถานะ</a>
        <?php foreach ($statusLabels as $value => $label): ?>
            <a class="pill" href="<?= Security::escape($pageUrl(['status' => $value])) ?>"<?= $status === $value ? ' aria-current="page"' : '' ?>><?= Security::escape($label) ?></a>
        <?php endforeach; ?>
    </div>
</section>

<?php if (!$programs): ?>
    <section class="card">
        <div class="empty"><?= ui_icon('inbox', 40) ?><b><?= $status !== '' ? 'ไม่พบหลักสูตรที่ตรงกับเงื่อนไข' : 'ยังไม่มีหลักสูตร' ?></b></div>
    </section>
<?php else: ?>
    <?php /* ---------- การ์ดหลักสูตร ---------- */ ?>
    <ul class="program-cards" aria-label="หลักสูตรทั้งหมด">
        <?php foreach ($programs as $p): ?>
            <li>
                <a class="program-card" href="<?= Security::escape($pageUrl(['status' => $status, 'program' => (int)$p['id_programs']])) ?>"<?= (int)$p['id_programs'] === (int)$selected['id_programs'] ? ' aria-current="page"' : '' ?>>
                    <b><?= Security::escape((string)$p['name']) ?></b>
                    <small><?= Security::escape((string)$p['code']) ?> · <?= (int)$p['versions_count'] ?> ฉบับ · <?= Security::escape($statusLabels[$p['status']] ?? (string)$p['status']) ?></small>
                </a>
            </li>
        <?php endforeach; ?>
    </ul>

    <?php /* ---------- หลักสูตรที่เลือก ---------- */ ?>
    <section class="card panel" id="programPanel"
             data-id="<?= (int)$selected['id_programs'] ?>"
             data-code="<?= Security::escape((string)$selected['code']) ?>"
             data-name="<?= Security::escape((string)$selected['name']) ?>"
             data-status="<?= Security::escape((string)$selected['status']) ?>">
        <div class="panel__head">
            <h2><?= Security::escape((string)$selected['name']) ?></h2>
            <span class="badge badge--<?= Security::escape((string)$selected['status']) ?>"><?= Security::escape($statusLabels[$selected['status']] ?? (string)$selected['status']) ?></span>
        </div>
        <span class="panel__sub">รหัส <?= Security::escape((string)$selected['code']) ?> · <?= (int)$selected['versions_count'] ?> ฉบับ</span>

        <div class="subtabs" role="tablist" aria-label="ส่วนของหลักสูตร">
            <button type="button" role="tab" id="tabCourses" aria-controls="paneCourses" aria-selected="true">รายวิชา <span id="courseTabCount"></span></button>
            <button type="button" role="tab" id="tabVersions" aria-controls="paneVersions" aria-selected="false" tabindex="-1">ฉบับหลักสูตร (<?= (int)$selected['versions_count'] ?>)</button>
            <button type="button" role="tab" id="tabSettings" aria-controls="paneSettings" aria-selected="false" tabindex="-1">ตั้งค่าหลักสูตร</button>
        </div>

        <?php /* ---------- แท็บรายวิชา ---------- */ ?>
        <div role="tabpanel" id="paneCourses" aria-labelledby="tabCourses">
            <div class="panel__bar">
                <div class="row">
                    <label class="sr-only" for="courseVersion">ฉบับหลักสูตร</label>
                    <select class="select" id="courseVersion" disabled><option>ฉบับหลักสูตร</option></select>
                    <label class="sr-only" for="courseYear">ชั้นปี</label>
                    <select class="select" id="courseYear"><option value="">ทุกชั้นปี</option></select>
                    <label class="sr-only" for="courseSearch">ค้นหารายวิชา</label>
                    <input class="input" type="search" id="courseSearch" placeholder="ค้นหารหัสหรือชื่อวิชา">
                </div>
                <button class="btn btn--primary" type="button" id="courseAddBtn" disabled>+ เพิ่มรายวิชา</button>
            </div>
            <p class="panel__count" id="courseCount" aria-live="polite"></p>
            <div id="courseTable"><p class="muted">กำลังโหลด…</p></div>
        </div>

        <?php /* ---------- แท็บฉบับหลักสูตร ---------- */ ?>
        <div role="tabpanel" id="paneVersions" aria-labelledby="tabVersions" hidden>
            <div id="versionList" aria-live="polite"><p class="muted">กำลังโหลด…</p></div>
            <form class="form-section" id="versionForm" novalidate>
                <div class="form-section__head"><h3>เพิ่มฉบับใหม่</h3></div>
                <div class="alert alert--danger" id="versionFormError" role="alert" hidden><b></b></div>
                <div class="form-grid">
                    <div class="field">
                        <label class="field__label" for="vNo">ฉบับที่<span class="req">*</span></label>
                        <input class="input" id="vNo" name="version_no" type="number" min="1" step="1" required aria-describedby="vNoError">
                        <span class="field__error" id="vNoError" hidden></span>
                    </div>
                    <div class="field">
                        <label class="field__label" for="vStatus">สถานะ</label>
                        <select class="select" id="vStatus" name="status">
                            <?php foreach ($statusLabels as $value => $label): ?>
                                <option value="<?= Security::escape($value) ?>"><?= Security::escape($label) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="field">
                        <label class="field__label" for="vFrom">เริ่มใช้</label>
                        <input class="input" id="vFrom" name="effective_from" type="date">
                    </div>
                    <div class="field">
                        <label class="field__label" for="vTo">สิ้นสุด</label>
                        <input class="input" id="vTo" name="effective_to" type="date" aria-describedby="vToError">
                        <span class="field__error" id="vToError" hidden></span>
                    </div>
                </div>
                <div class="btn-row btn-row--end">
                    <button class="btn btn--primary" type="submit">เพิ่มฉบับ</button>
                </div>
            </form>
        </div>

        <?php /* ---------- แท็บตั้งค่าหลักสูตร ---------- */ ?>
        <div role="tabpanel" id="paneSettings" aria-labelledby="tabSettings" hidden>
            <form class="form-section" id="settingsForm" novalidate>
                <div class="alert alert--danger" id="settingsFormError" role="alert" hidden><b></b></div>
                <div class="form-grid">
                    <div class="field span-2">
                        <label class="field__label" for="sName">ชื่อหลักสูตร<span class="req">*</span></label>
                        <input class="input" id="sName" name="name" maxlength="150" required value="<?= Security::escape((string)$selected['name']) ?>" aria-describedby="sNameError">
                        <span class="field__error" id="sNameError" hidden></span>
                    </div>
                    <div class="field">
                        <label class="field__label" for="sCode">รหัสหลักสูตร<span class="req">*</span></label>
                        <input class="input" id="sCode" name="code" maxlength="20" required value="<?= Security::escape((string)$selected['code']) ?>" aria-describedby="sCodeError">
                        <span class="field__error" id="sCodeError" hidden></span>
                    </div>
                    <div class="field">
                        <label class="field__label" for="sStatus">สถานะหลักสูตร</label>
                        <select class="select" id="sStatus" name="status">
                            <?php foreach ($statusLabels as $value => $label): ?>
                                <option value="<?= Security::escape($value) ?>"<?= $selected['status'] === $value ? ' selected' : '' ?>><?= Security::escape($label) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="btn-row btn-row--end">
                    <button class="btn btn--primary" type="submit">บันทึกการแก้ไข</button>
                </div>
            </form>
        </div>
    </section>
<?php endif; ?>

<?php /* ---------- ฟอร์มเพิ่มหลักสูตร ---------- */ ?>
<div class="modal" id="programModal" role="dialog" aria-modal="true" aria-labelledby="programModalTitle">
    <form class="modal__box" id="programForm" novalidate>
        <div class="modal__head">
            <h2 id="programModalTitle">เพิ่มหลักสูตรใหม่</h2>
            <button class="modal__close" type="button" data-modal-close aria-label="ปิด">✕</button>
        </div>
        <div class="alert alert--danger" id="programFormError" role="alert" hidden><b></b></div>
        <div class="form-stack">
            <div class="field">
                <label class="field__label" for="pName">ชื่อหลักสูตร<span class="req">*</span></label>
                <input class="input" id="pName" name="name" maxlength="150" required autocomplete="off" placeholder="เช่น หลักสูตรพยาบาลศาสตรบัณฑิต" aria-describedby="pNameError">
                <span class="field__error" id="pNameError" hidden></span>
            </div>
            <div class="form-grid">
                <div class="field">
                    <label class="field__label" for="pCode">รหัสหลักสูตร<span class="req">*</span></label>
                    <input class="input" id="pCode" name="code" maxlength="20" required autocomplete="off" aria-describedby="pCodeError">
                    <span class="field__error" id="pCodeError" hidden></span>
                </div>
                <div class="field">
                    <label class="field__label" for="pStatus">สถานะหลักสูตร</label>
                    <select class="select" id="pStatus" name="status">
                        <?php foreach ($statusLabels as $value => $label): ?>
                            <option value="<?= Security::escape($value) ?>"><?= Security::escape($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
        </div>
        <div class="modal__foot">
            <button class="btn btn--ghost" type="button" data-modal-close>ยกเลิก</button>
            <button class="btn btn--primary" type="submit">บันทึกหลักสูตร</button>
        </div>
    </form>
</div>

<?php /* ---------- ฟอร์มรายวิชา ---------- */ ?>
<div class="modal" id="courseModal" role="dialog" aria-modal="true" aria-labelledby="courseModalTitle">
    <form class="modal__box modal__box--lg" id="courseForm" novalidate>
        <div class="modal__head">
            <div class="grow">
                <h2 id="courseModalTitle">เพิ่มรายวิชา</h2>
                <p class="muted" id="courseModalSub"></p>
            </div>
            <button class="modal__close" type="button" data-modal-close aria-label="ปิด">✕</button>
        </div>
        <div class="alert alert--danger" id="courseFormError" role="alert" hidden><b></b></div>
        <input type="hidden" name="binding_id" value="">
        <input type="hidden" name="course_id" value="">

        <fieldset class="pills" id="courseModeGroup">
            <legend class="sr-only">วิธีเพิ่มรายวิชา</legend>
            <label class="pill"><input type="radio" name="mode" value="new" checked> สร้างรายวิชาใหม่</label>
            <label class="pill"><input type="radio" name="mode" value="existing"> เลือกรายวิชาที่มีในระบบ</label>
        </fieldset>

        <div class="field" id="courseExistingField" hidden>
            <label class="field__label" for="cExisting">รายวิชา<span class="req">*</span></label>
            <select class="select" id="cExisting" name="existing" aria-describedby="cExistingError"></select>
            <span class="field__error" id="cExistingError" hidden></span>
        </div>

        <div class="form-grid" id="courseNewFields">
            <div class="field">
                <label class="field__label" for="cCode">รหัสวิชา<span class="req">*</span></label>
                <input class="input" id="cCode" name="code" maxlength="20" autocomplete="off" aria-describedby="cCodeError">
                <span class="field__error" id="cCodeError" hidden></span>
            </div>
            <div class="field">
                <label class="field__label" for="cName">ชื่อวิชา<span class="req">*</span></label>
                <input class="input" id="cName" name="name" maxlength="150" autocomplete="off" aria-describedby="cNameError">
                <span class="field__error" id="cNameError" hidden></span>
            </div>
            <div class="field span-2">
                <label class="field__label" for="cGroup">กลุ่มวิชา<span class="req">*</span></label>
                <select class="select" id="cGroup" name="course_groups_id" aria-describedby="cGroupError"></select>
                <span class="field__error" id="cGroupError" hidden></span>
            </div>
        </div>

        <div class="form-grid">
            <div class="field">
                <label class="field__label" for="cYear">ชั้นปี</label>
                <select class="select" id="cYear" name="year_level">
                    <option value="">ไม่ระบุ</option>
                    <?php for ($y = 1; $y <= 6; $y++): ?><option value="<?= $y ?>">ปี <?= $y ?></option><?php endfor; ?>
                </select>
            </div>
            <div class="field">
                <label class="field__label" for="cSemester">ภาคเรียน</label>
                <select class="select" id="cSemester" name="semester_no">
                    <option value="">ไม่ระบุ</option>
                    <?php for ($s = 1; $s <= 3; $s++): ?><option value="<?= $s ?>">ภาค <?= $s ?></option><?php endfor; ?>
                </select>
            </div>
        </div>

        <div class="modal__foot">
            <button class="btn btn--ghost" type="button" data-modal-close>ยกเลิก</button>
            <button class="btn btn--primary" type="submit">บันทึกรายวิชา</button>
        </div>
    </form>
</div>
<?php require dirname(__DIR__) . '/includes/footer.php'; ?>
