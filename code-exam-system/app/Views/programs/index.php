<?php
/**
 * ============================================================
 *  หน้า        : หลักสูตร (แท็บแรกของเมนู "หลักสูตรและรายวิชา")
 *  Route       : GET /programs → ProgramController@index
 *  สิทธิ์       : program.manage (ฝ่ายวิชาการ, แอดมิน)
 *  แบบหน้าจอ   : prototype AdminPrograms.html
 * ------------------------------------------------------------
 *  ข้อมูลที่ได้รับ
 *    $programs  รายการหลักสูตร [id_programs, code, name, status, versions_count]
 *  ตัวกรอง (GET): q = ค้นหารหัส/ชื่อ · status = active | inactive | archived
 *  ⚠️ ค้นหา (?q=) ยัง error 500 จากบั๊ก :q ซ้ำใน ProgramModel (docs/api-check.md ข้อ 1)
 * ============================================================
 */
require dirname(__DIR__) . '/includes/header.php';

$programs     = $programs ?? [];
$q            = trim((string)($_GET['q'] ?? ''));
$status       = (string)($_GET['status'] ?? '');
$filtered     = $q !== '' || $status !== '';
$statusLabels = ['active' => 'ใช้งาน', 'inactive' => 'ปิดใช้งาน', 'archived' => 'จัดเก็บแล้ว'];
$appUrl       = rtrim((string)env('APP_URL', ''), '/');
?>
<?php $catalogTab = 'programs'; $pageHeading = 'หลักสูตร'; $pageDesc = 'รายการหลักสูตรทั้งหมดและจำนวนรุ่นหลักสูตร'; require dirname(__DIR__) . '/includes/catalog-tabs.php'; ?>

<section class="card card--flush">
    <form class="toolbar" method="get" action="<?= Security::escape($appUrl) ?>/programs" role="search">
        <div class="field">
            <label class="field__label" for="q">ค้นหา</label>
            <div class="search"><?= ui_icon('search', 20) ?><input class="input" id="q" name="q" type="search" value="<?= Security::escape($q) ?>" placeholder="รหัสหรือชื่อหลักสูตร"></div>
        </div>
        <div class="field field--sm">
            <label class="field__label" for="status">สถานะ</label>
            <select class="select" id="status" name="status">
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
    </form>

    <?php if (empty($programs)): ?>
        <div class="empty">
            <?= ui_icon('inbox', 40) ?>
            <b><?= $filtered ? 'ไม่พบหลักสูตรที่ตรงกับเงื่อนไข' : 'ยังไม่มีหลักสูตร' ?></b>
            <span><?= $filtered ? 'ลองเปลี่ยนคำค้นหาหรือสถานะ' : 'เมื่อเพิ่มหลักสูตรแล้วจะแสดงที่นี่' ?></span>
        </div>
    <?php else: ?>
        <div class="table-wrap">
            <table class="table">
                <caption class="sr-only">รายการหลักสูตร</caption>
                <thead>
                    <tr>
                        <th scope="col">รหัสหลักสูตร</th>
                        <th scope="col">ชื่อหลักสูตร</th>
                        <th scope="col" class="num">จำนวนรุ่น</th>
                        <th scope="col">สถานะ</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($programs as $p): ?>
                        <tr>
                            <td class="code nowrap"><?= Security::escape((string)$p['code']) ?></td>
                            <td><?= Security::escape((string)$p['name']) ?></td>
                            <td class="num"><?= (int)$p['versions_count'] ?></td>
                            <td><span class="badge badge--<?= Security::escape((string)$p['status']) ?>"><?= Security::escape($statusLabels[$p['status']] ?? (string)$p['status']) ?></span></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <div class="table-foot">ทั้งหมด <?= count($programs) ?> หลักสูตร</div>
    <?php endif; ?>
</section>
<?php require dirname(__DIR__) . '/includes/footer.php'; ?>
