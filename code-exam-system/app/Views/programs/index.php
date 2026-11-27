<?php
// ---------- ค่าพื้นฐาน ----------
$programs    = $programs ?? [];
$crumbTitle  = 'หลักสูตรและรายวิชา';
$pageScripts = ['/js/pages/programs.js'];
require dirname(__DIR__) . '/includes/header.php';

// ---------- ตัวกรอง ----------
$status   = (string)($_GET['status'] ?? '');
$filtered = $status !== '';

// ---------- ข้อความ ----------
$statusLabels = ['active' => 'ใช้งาน', 'inactive' => 'ปิดใช้งาน', 'archived' => 'จัดเก็บแล้ว'];
?>
<?php $catalogTab = '/programs'; require dirname(__DIR__) . '/includes/catalog-tabs.php'; ?>

<section class="card card--flush">
    <?php /* ---------- ตัวกรอง ---------- */ ?>
    <form class="toolbar" method="get" action="<?= Security::escape($appUrl) ?>/programs" role="search">
        <div class="field field--sm">
            <label class="field__label" for="fStatus">สถานะ</label>
            <select class="select" id="fStatus" name="status">
                <option value="">ทุกสถานะ</option>
                <?php foreach ($statusLabels as $value => $label): ?>
                    <option value="<?= Security::escape($value) ?>"<?= $status === $value ? ' selected' : '' ?>><?= Security::escape($label) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="btn-row">
            <button class="btn btn--primary" type="submit">ค้นหา</button>
            <?php if ($filtered): ?><a class="btn btn--ghost" href="<?= Security::escape($appUrl) ?>/programs">ล้างตัวกรอง</a><?php endif; ?>
        </div>
        <span class="grow"></span>
        <button class="btn btn--secondary" type="button" data-program-new>+ เพิ่มหลักสูตร</button>
    </form>

    <?php /* ---------- ตาราง ---------- */ ?>
    <?php if (!$programs): ?>
        <div class="empty"><?= ui_icon('inbox', 40) ?><b><?= $filtered ? 'ไม่พบหลักสูตรที่ตรงกับเงื่อนไข' : 'ยังไม่มีหลักสูตร' ?></b></div>
    <?php else: ?>
        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr>
                        <th scope="col">รหัสหลักสูตร</th>
                        <th scope="col">ชื่อหลักสูตร</th>
                        <th scope="col" class="num">จำนวนรุ่น</th>
                        <th scope="col">สถานะ</th>
                        <th scope="col"><span class="sr-only">จัดการ</span></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($programs as $p): ?>
                        <tr>
                            <td class="code nowrap"><?= Security::escape((string)$p['code']) ?></td>
                            <td><?= Security::escape((string)$p['name']) ?></td>
                            <td class="num"><?= (int)$p['versions_count'] ?></td>
                            <td><span class="badge badge--<?= Security::escape((string)$p['status']) ?>"><?= Security::escape($statusLabels[$p['status']] ?? (string)$p['status']) ?></span></td>
                            <td class="nowrap">
                                <button class="btn btn--ghost btn--sm" type="button" data-program-edit
                                        data-id="<?= (int)$p['id_programs'] ?>"
                                        data-code="<?= Security::escape((string)$p['code']) ?>"
                                        data-name="<?= Security::escape((string)$p['name']) ?>"
                                        data-status="<?= Security::escape((string)$p['status']) ?>"
                                        aria-label="แก้ไข <?= Security::escape((string)$p['name']) ?>">แก้ไข</button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <div class="table-foot">ทั้งหมด <?= count($programs) ?> หลักสูตร</div>
    <?php endif; ?>
</section>

<?php /* ---------- ฟอร์มหลักสูตร ---------- */ ?>
<div class="modal" id="programModal" role="dialog" aria-modal="true" aria-labelledby="programModalTitle">
    <form class="modal__box" id="programForm" novalidate>
        <div class="modal__head">
            <h2 id="programModalTitle" data-title-new="เพิ่มหลักสูตร" data-title-edit="แก้ไขหลักสูตร">เพิ่มหลักสูตร</h2>
            <button class="modal__close" type="button" data-modal-close aria-label="ปิด">✕</button>
        </div>

        <div class="alert alert--danger" id="programFormError" role="alert" hidden><b></b></div>

        <input type="hidden" name="id" value="">
        <div class="form-stack">
            <div class="field">
                <label class="field__label" for="pCode">รหัสหลักสูตร<span class="req">*</span></label>
                <input class="input" id="pCode" name="code" maxlength="20" required autocomplete="off" aria-describedby="pCodeError">
                <span class="field__error" id="pCodeError" hidden></span>
            </div>
            <div class="field">
                <label class="field__label" for="pName">ชื่อหลักสูตร<span class="req">*</span></label>
                <input class="input" id="pName" name="name" maxlength="150" required autocomplete="off" aria-describedby="pNameError">
                <span class="field__error" id="pNameError" hidden></span>
            </div>
            <div class="field">
                <label class="field__label" for="pStatus">สถานะ</label>
                <select class="select" id="pStatus" name="status">
                    <?php foreach ($statusLabels as $value => $label): ?>
                        <option value="<?= Security::escape($value) ?>"><?= Security::escape($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <div class="modal__foot">
            <button class="btn btn--ghost" type="button" data-modal-close>ยกเลิก</button>
            <button class="btn btn--primary" type="submit">บันทึก</button>
        </div>
    </form>
</div>
<?php require dirname(__DIR__) . '/includes/footer.php'; ?>
