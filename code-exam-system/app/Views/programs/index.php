<?php
// ---------- ค่าพื้นฐาน ----------
$programs    = $programs ?? [];
$crumbTitle  = 'หลักสูตรและรายวิชา';
$pageScripts = ['/js/pages/programs.js'];
require dirname(__DIR__) . '/includes/header.php';

// ---------- ข้อความ ----------
$statusLabels = ['active' => 'ใช้งาน', 'inactive' => 'ปิดใช้งาน', 'archived' => 'จัดเก็บแล้ว'];

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
$termId   = (int)($_GET['term'] ?? 0);
$cardUrl  = static function (int $programId) use ($appUrl, $termId): string {
    return $appUrl . '/programs?' . http_build_query(array_filter(['program' => $programId, 'term' => $termId ?: null]));
};
?>
<?php /* ---------- แบนเนอร์ ---------- */ ?>
<section class="greet hub-hero" aria-label="หลักสูตรและรายวิชา">
    <svg class="hub-hero__deco" aria-hidden="true" width="240" height="150" viewBox="180 20 280 160"><path d="M418 34h10v12h12v10h-12v12h-10V56h-12V46h12z" fill="#CDB9F0"/><path d="M120 40h7v9h9v7h-9v9h-7v-9h-9v-7h9z" fill="#D8C8F4"/></svg>
    <span class="dash-hero__icon"><?= ui_icon('school', 64) ?></span>
    <div class="greet__text">
        <h1>หลักสูตรและรายวิชา</h1>
        <p>ทุกหลักสูตรของคณะ · เปิดภาคการศึกษา จัดรายวิชา กลุ่มเรียน ผู้สอน และรายชื่อนิสิต</p>
    </div>
    <div class="hub-hero__actions">
        <button class="btn btn--primary" type="button" data-program-new>+ เพิ่มหลักสูตร</button>
    </div>
</section>

<?php /* ---------- ภาคการศึกษา ---------- */ ?>
<section class="card pill-bar" aria-label="ภาคการศึกษา">
    <span class="pill-bar__label">ภาคการศึกษา</span>
    <div class="pills" role="radiogroup" aria-label="เลือกภาคการศึกษา" id="termPills" data-term="<?= $termId ?: '' ?>">
        <span class="muted">กำลังโหลด…</span>
    </div>
    <div class="pill-bar__actions">
        <a class="btn btn--secondary" href="<?= Security::escape($appUrl) ?>/academic-terms">เปิดภาค / ปีการศึกษาใหม่</a>
        <a class="btn btn--danger-outline" href="<?= Security::escape($appUrl) ?>/academic-terms">ปิดภาคการศึกษา</a>
    </div>
</section>

<?php if (!$programs): ?>
    <section class="card">
        <div class="empty"><?= ui_icon('inbox', 40) ?><b>ยังไม่มีหลักสูตร</b></div>
    </section>
<?php else: ?>
    <?php /* ---------- การ์ดหลักสูตร ---------- */ ?>
    <div class="program-cards" role="list" aria-label="หลักสูตรทั้งหมด">
        <?php foreach ($programs as $p): ?>
            <a role="listitem" class="program-card" href="<?= Security::escape($cardUrl((int)$p['id_programs'])) ?>" data-program-card="<?= (int)$p['id_programs'] ?>" data-status="<?= Security::escape((string)$p['status']) ?>"<?= (int)$p['id_programs'] === (int)$selected['id_programs'] ? ' aria-current="page"' : '' ?>>
                <b><?= Security::escape((string)$p['name']) ?></b>
                <small><?= (int)$p['versions_count'] ?> ฉบับ · <?= Security::escape($statusLabels[$p['status']] ?? (string)$p['status']) ?></small>
            </a>
        <?php endforeach; ?>
    </div>

    <?php /* ---------- หลักสูตรที่เลือก ---------- */ ?>
    <section class="card panel" id="programPanel"
             data-id="<?= (int)$selected['id_programs'] ?>"
             data-code="<?= Security::escape((string)$selected['code']) ?>"
             data-name="<?= Security::escape((string)$selected['name']) ?>"
             data-status="<?= Security::escape((string)$selected['status']) ?>">
        <div class="panel__head">
            <h2><?= Security::escape((string)$selected['name']) ?></h2>
            <?php if ($selected['status'] === 'active'): ?>
                <span class="badge badge--solid-success">แสดงอยู่</span>
            <?php else: ?>
                <span class="badge badge--<?= Security::escape((string)$selected['status']) ?>"><?= Security::escape($statusLabels[$selected['status']] ?? (string)$selected['status']) ?></span>
            <?php endif; ?>
        </div>
        <span class="panel__sub" id="programSub">รหัส <?= Security::escape((string)$selected['code']) ?> · <?= Security::escape($statusLabels[$selected['status']] ?? (string)$selected['status']) ?></span>

        <div class="subtabs" role="tablist" aria-label="ส่วนของหลักสูตร">
            <button type="button" role="tab" id="tabCourses" aria-controls="paneCourses" aria-selected="true">รายวิชา <span id="courseTabCount"></span></button>
            <button type="button" role="tab" id="tabCohorts" aria-controls="paneCohorts" aria-selected="false" tabindex="-1">นิสิตตามรุ่น</button>
            <button type="button" role="tab" id="tabSettings" aria-controls="paneSettings" aria-selected="false" tabindex="-1">ตั้งค่าหลักสูตร</button>
        </div>

        <?php /* ---------- แท็บรายวิชา ---------- */ ?>
        <div role="tabpanel" id="paneCourses" aria-labelledby="tabCourses">
            <div class="panel__bar">
                <div class="row">
                    <label class="sr-only" for="courseYear">ชั้นปี</label>
                    <select class="select" id="courseYear"><option value="">ทุกชั้นปี</option></select>
                    <label class="sr-only" for="courseSearch">ค้นหารายวิชา</label>
                    <input class="input" type="search" id="courseSearch" placeholder="ค้นหารหัสหรือชื่อวิชา">
                </div>
                <div class="btn-row">
                    <button class="btn btn--secondary" type="button" disabled>นำเข้าจาก Excel <small>เร็วๆ นี้</small></button>
                    <button class="btn btn--primary" type="button" id="courseAddBtn" disabled>+ เพิ่มรายวิชา</button>
                </div>
            </div>
            <p class="panel__count" id="courseCount" aria-live="polite"></p>
            <div id="courseTable"><p class="muted">กำลังโหลด…</p></div>
        </div>

        <?php /* ---------- แท็บนิสิตตามรุ่น ---------- */ ?>
        <div role="tabpanel" id="paneCohorts" aria-labelledby="tabCohorts" hidden>
            <div class="panel__bar">
                <span class="muted">นิสิตลงทะเบียนเป็นรุ่น · ชั้นปีเลื่อนให้เองเมื่อเริ่มปีการศึกษาใหม่</span>
                <div class="btn-row">
                    <button class="btn btn--secondary" type="button" disabled>นำเข้ารายชื่อจากงานทะเบียน</button>
                    <button class="btn btn--primary" type="button" disabled>+ เพิ่มรุ่น</button>
                </div>
            </div>
            <div class="empty"><?= ui_icon('users', 40) ?><b>ส่วนนิสิตตามรุ่นยังไม่พร้อมใช้งาน</b><span class="muted">รอระบบหลังบ้านเปิดข้อมูลรุ่นนิสิต · เร็วๆ นี้</span></div>
        </div>

        <?php /* ---------- แท็บตั้งค่าหลักสูตร ---------- */ ?>
        <div role="tabpanel" id="paneSettings" aria-labelledby="tabSettings" hidden>
            <form class="settings-form" id="settingsForm" novalidate>
                <div class="alert alert--danger" id="settingsFormError" role="alert" hidden><b></b></div>
                <div class="form-grid form-grid--4">
                    <div class="field">
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
                    <div class="field">
                        <label class="field__label" for="sVersion">ฉบับหลักสูตร (ฉบับที่)</label>
                        <select class="select" id="sVersion" disabled><option value="">ยังไม่มีฉบับ</option></select>
                        <button class="link-btn link-btn--start" type="button" id="versionAddBtn">+ เพิ่มฉบับใหม่</button>
                    </div>
                </div>
                <div class="btn-row btn-row--end settings-form__foot">
                    <button class="btn btn--ghost" type="reset">คืนค่าเดิม</button>
                    <button class="btn btn--primary" type="submit">บันทึกการเปลี่ยนแปลง</button>
                </div>
            </form>

            <div class="danger-zone">
                <div>
                    <b>ลบหลักสูตร</b>
                    <p>ลบได้เฉพาะหลักสูตรที่ยังไม่มีฉบับหลักสูตร · ถ้ามีแล้วให้เปลี่ยนสถานะเป็น "ปิดใช้งาน" แทน</p>
                    <p class="field__error" id="deleteError" role="alert" hidden></p>
                </div>
                <button class="btn btn--danger-outline" type="button" id="programDeleteBtn">ลบหลักสูตร</button>
            </div>
        </div>
    </section>

    <?php /* ---------- ฟอร์มเพิ่มฉบับ ---------- */ ?>
    <div class="modal" id="versionModal" role="dialog" aria-modal="true" aria-labelledby="versionModalTitle">
        <form class="modal__box" id="versionForm" novalidate>
            <div class="modal__head">
                <div class="grow">
                    <h2 id="versionModalTitle">เพิ่มฉบับหลักสูตร</h2>
                    <p class="muted"><?= Security::escape((string)$selected['name']) ?></p>
                </div>
                <button class="modal__close" type="button" data-modal-close aria-label="ปิด">✕</button>
            </div>
            <div class="alert alert--danger" id="versionFormError" role="alert" hidden><b></b></div>
            <div id="versionList" aria-live="polite"></div>
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
            <div class="modal__foot">
                <button class="btn btn--ghost" type="button" data-modal-close>ยกเลิก</button>
                <button class="btn btn--primary" type="submit">เพิ่มฉบับ</button>
            </div>
        </form>
    </div>
<?php endif; ?>

<?php /* ---------- ฟอร์มเพิ่มหลักสูตร ---------- */ ?>
<div class="modal" id="programModal" role="dialog" aria-modal="true" aria-labelledby="programModalTitle" aria-describedby="programModalSub">
    <form class="modal__box sheet" id="programForm" novalidate>
        <div class="sheet__head">
            <span class="sheet__icon" aria-hidden="true"><?= ui_icon('school', 28) ?></span>
            <div class="grow">
                <h2 id="programModalTitle">เพิ่มหลักสูตรใหม่</h2>
                <p id="programModalSub">สร้างหลักสูตรพร้อมฉบับแรก แล้วเลือกว่าจะเริ่มรายวิชาอย่างไร</p>
            </div>
            <button class="modal__close" type="button" data-modal-close aria-label="ปิด">✕</button>
        </div>

        <div class="sheet__body">
            <div class="alert alert--danger" id="programFormError" role="alert" hidden><b></b></div>

            <?php /* ---------- 1. ข้อมูลหลักสูตร ---------- */ ?>
            <section class="sheet__section" aria-labelledby="pSecInfo">
                <div class="sheet__section-head">
                    <span class="form-section__num" aria-hidden="true">1</span>
                    <h3 id="pSecInfo">ข้อมูลหลักสูตร</h3>
                </div>
                <div class="field">
                    <label class="field__label" for="pName">ชื่อหลักสูตร<span class="req">*</span></label>
                    <input class="input" id="pName" name="name" maxlength="150" required autocomplete="off" placeholder="เช่น หลักสูตรพยาบาลศาสตรบัณฑิต (หลักสูตรปรับปรุง พ.ศ. 2570)" aria-describedby="pNameError">
                    <span class="field__error" id="pNameError" hidden></span>
                </div>
                <div class="sheet__grid">
                    <div class="field">
                        <label class="field__label" for="pCode">รหัสหลักสูตร<span class="req">*</span></label>
                        <input class="input input--code" id="pCode" name="code" maxlength="20" required autocomplete="off" placeholder="เช่น BNS" aria-describedby="pCodeHint pCodeError">
                        <span class="field__hint" id="pCodeHint">ไม่ซ้ำกับหลักสูตรอื่น</span>
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
                    <div class="field">
                        <label class="field__label" for="pVersionNo">ฉบับหลักสูตร (ฉบับที่)<span class="req">*</span></label>
                        <input class="input" id="pVersionNo" name="version_no" type="number" min="1" step="1" value="1" required aria-describedby="pVersionNoError">
                        <span class="field__error" id="pVersionNoError" hidden></span>
                    </div>
                </div>
            </section>

            <?php /* ---------- 2. รายวิชาเริ่มต้น ---------- */ ?>
            <section class="sheet__section" aria-labelledby="pSecCourses">
                <div class="sheet__section-head">
                    <span class="form-section__num" aria-hidden="true">2</span>
                    <h3 id="pSecCourses">รายวิชาเริ่มต้น</h3>
                </div>
                <fieldset class="picks">
                    <legend class="sr-only">รายวิชาเริ่มต้น</legend>
                    <label class="pick">
                        <input type="radio" name="start" value="empty" checked>
                        <span class="pick__icon" aria-hidden="true"><?= ui_icon('file-plus', 24) ?></span>
                        <span class="pick__body">
                            <b>เริ่มจากหลักสูตรว่าง</b>
                            <small>เพิ่มรายวิชาเองทีหลัง หรือนำเข้าจาก Excel</small>
                        </span>
                    </label>
                    <label class="pick">
                        <input type="radio" name="start" value="copy"<?= $programs ? '' : ' disabled' ?>>
                        <span class="pick__icon" aria-hidden="true"><?= ui_icon('layers', 24) ?></span>
                        <span class="pick__body">
                            <b>คัดลอกรายวิชาจากหลักสูตรเดิม</b>
                            <small><?= $programs ? 'เหมาะกับหลักสูตรปรับปรุง · แก้ไขรายวิชาได้ภายหลัง' : 'ยังไม่มีหลักสูตรเดิมให้คัดลอก' ?></small>
                        </span>
                    </label>
                </fieldset>
                <div class="sheet__panel" id="pCopyFields" hidden>
                    <div class="form-grid">
                        <div class="field">
                            <label class="field__label" for="pSrcProgram">คัดลอกจากหลักสูตร<span class="req">*</span></label>
                            <select class="select" id="pSrcProgram" aria-describedby="pSrcProgramError">
                                <option value="">เลือกหลักสูตร</option>
                                <?php foreach ($programs as $p): ?>
                                    <option value="<?= (int)$p['id_programs'] ?>"><?= Security::escape((string)$p['code']) ?> · <?= Security::escape((string)$p['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <span class="field__error" id="pSrcProgramError" hidden></span>
                        </div>
                        <div class="field">
                            <label class="field__label" for="pSrcVersion">ฉบับที่ใช้คัดลอก<span class="req">*</span></label>
                            <select class="select" id="pSrcVersion" disabled aria-describedby="pSrcVersionHint pSrcVersionError">
                                <option value="">เลือกหลักสูตรก่อน</option>
                            </select>
                            <span class="field__error" id="pSrcVersionError" hidden></span>
                        </div>
                    </div>
                    <p class="sheet__note" id="pSrcVersionHint" aria-live="polite"></p>
                </div>
            </section>
        </div>

        <div class="sheet__foot">
            <span class="sheet__req"><span class="req">*</span> จำเป็นต้องกรอก</span>
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

        <div class="form-grid course-form__top" id="courseNewFields">
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
        </div>

        <div class="form-grid form-grid--4">
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
            <div class="field span-2" id="courseGroupField">
                <label class="field__label" for="cGroup">กลุ่มวิชา<span class="req">*</span></label>
                <select class="select" id="cGroup" name="course_groups_id" aria-describedby="cGroupError"></select>
                <span class="field__error" id="cGroupError" hidden></span>
            </div>
        </div>

        <div class="modal__foot modal__foot--split">
            <button class="btn btn--danger-outline" type="button" id="courseRemoveBtn" hidden>นำออกจากหลักสูตร</button>
            <span class="grow"></span>
            <button class="btn btn--ghost" type="button" data-modal-close>ยกเลิก</button>
            <button class="btn btn--primary" type="submit">บันทึกรายวิชา</button>
        </div>
    </form>
</div>
<?php require dirname(__DIR__) . '/includes/footer.php'; ?>
