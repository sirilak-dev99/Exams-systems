<?php
/**
 * ============================================================
 *  หน้า        : ภาคการศึกษา — แผนงานข้อ 16
 *  Route       : GET /academic-terms → AcademicTermController@index
 *  สิทธิ์       : course.view (อาจารย์, ฝ่ายวิชาการ, แอดมิน)
 * ------------------------------------------------------------
 *  ข้อมูลที่ได้รับ
 *    $academicTerms  [id_academic_terms, academic_year, term_no, start_date, end_date, status]
 *  ตัวกรอง (GET): academic_year = ปี พ.ศ. · status = active | upcoming | inactive | archived
 *  ตอนนี้แสดงอย่างเดียว · ปุ่มเพิ่ม/แก้ไขจะเพิ่มทีหลัง (ต้องใช้ JS เพราะ POST /academic-terms ตอบ JSON)
 * ============================================================
 */
$academicTerms = $academicTerms ?? [];
require dirname(__DIR__) . '/includes/header.php';

$year         = trim((string)($_GET['academic_year'] ?? ''));
$status       = (string)($_GET['status'] ?? '');
$filtered     = $year !== '' || $status !== '';
$statusLabels = ['active' => 'ภาคปัจจุบัน', 'upcoming' => 'ภาคถัดไป', 'inactive' => 'ปิดใช้งาน', 'archived' => 'จัดเก็บแล้ว'];
$appUrl       = rtrim((string)env('APP_URL', ''), '/');
?>
<div class="page-head">
    <div class="page-head__text">
        <h1>ภาคการศึกษา</h1>
        <p>ปีการศึกษา ภาคเรียน และช่วงวันเปิด–ปิดภาค</p>
    </div>
</div>

<section class="card card--flush">
    <form class="toolbar" method="get" action="<?= Security::escape($appUrl) ?>/academic-terms" role="search">
        <div class="field field--sm">
            <label class="field__label" for="fYear">ปีการศึกษา (พ.ศ.)</label>
            <input class="input" id="fYear" name="academic_year" type="number" min="2500" step="1" inputmode="numeric"
                   value="<?= Security::escape($year) ?>" placeholder="เช่น 2569">
        </div>
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
            <?php if ($filtered): ?><a class="btn btn--ghost" href="<?= Security::escape($appUrl) ?>/academic-terms">ล้างตัวกรอง</a><?php endif; ?>
        </div>
    </form>

    <?php if (!$academicTerms): ?>
        <div class="empty">
            <?= ui_icon('inbox', 40) ?>
            <b><?= $filtered ? 'ไม่พบภาคการศึกษาที่ตรงกับเงื่อนไข' : 'ยังไม่มีภาคการศึกษา' ?></b>
            <span><?= $filtered ? 'ลองเปลี่ยนปีหรือสถานะ' : 'เมื่อฝ่ายวิชาการเพิ่มภาคการศึกษาแล้วจะแสดงที่นี่' ?></span>
        </div>
    <?php else: ?>
        <div class="table-wrap">
            <table class="table">
                <caption class="sr-only">รายการภาคการศึกษา</caption>
                <thead>
                    <tr>
                        <th scope="col">ภาค/ปีการศึกษา</th>
                        <th scope="col">วันเปิดภาค</th>
                        <th scope="col">วันปิดภาค</th>
                        <th scope="col">สถานะ</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($academicTerms as $t): ?>
                        <tr>
                            <td class="nowrap"><b>ภาค <?= (int)$t['term_no'] ?>/<?= (int)$t['academic_year'] ?></b></td>
                            <td class="nowrap"><?= Security::escape(thai_date($t['start_date'] ?? null, false)) ?></td>
                            <td class="nowrap"><?= Security::escape(thai_date($t['end_date'] ?? null, false)) ?></td>
                            <td><span class="badge badge--<?= Security::escape((string)$t['status']) ?>"><?= Security::escape($statusLabels[$t['status']] ?? (string)$t['status']) ?></span></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <div class="table-foot">ทั้งหมด <?= count($academicTerms) ?> ภาคการศึกษา</div>
    <?php endif; ?>
</section>
<?php require dirname(__DIR__) . '/includes/footer.php'; ?>
