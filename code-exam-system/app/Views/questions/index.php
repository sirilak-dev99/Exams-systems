<?php
/**
 * ============================================================
 *  หน้า        : คลังข้อสอบ (รายการ + ตัวกรอง)
 *  สถานะ       : view พร้อมใช้ — รอ backend เพิ่ม route/Controller (ร่างเก็บไว้ที่ branch draft/teaching-backend)
 *  Route       : GET /questions → QuestionController@index
 *  สิทธิ์       : question.manage (อาจารย์, แอดมิน)
 *  แบบหน้าจอ   : prototype QuestionBank.html
 * ------------------------------------------------------------
 *  ข้อมูลที่ได้รับ (QuestionBankModel)
 *    $questions     [id_questions, text, type, bloom_levels_code, bloom_name_th, status,
 *                    courses_id, course_code, course_name, chapters_id, chapters_no, chapter_name,
 *                    scenario_id, scenario_title, manual_max_score, updated_at]
 *    $courses       รายวิชาสำหรับตัวกรอง [id_courses, code, name]
 *    $chapters      บทของรายวิชาที่กำลังกรอง [id_chapters, chapters_no, name]
 *    $bloomLevels   ระดับ Bloom [code, name_th]
 *    $statusCounts  จำนวนตามสถานะ ['all', 'draft', 'published', 'archived']
 *  ตัวกรอง (GET): q (ข้อความในโจทย์ หรือรหัส เช่น Q-0001) · courses_id ('none' = คลังกลาง)
 *                 chapters_id · type · bloom · status
 * ============================================================
 */
require dirname(__DIR__) . '/includes/header.php';
require __DIR__ . '/_labels.php';

$questions    = $questions ?? [];
$courses      = $courses ?? [];
$chapters     = $chapters ?? [];
$bloomLevels  = $bloomLevels ?? [];
$statusCounts = $statusCounts ?? null;
$appUrl       = rtrim((string)env('APP_URL', ''), '/');

// ---------- ค่าตัวกรองจาก URL ----------
$f = [
    'q'           => trim((string)($_GET['q'] ?? '')),
    'courses_id'  => (string)($_GET['courses_id'] ?? ''),
    'chapters_id' => (string)($_GET['chapters_id'] ?? ''),
    'type'        => (string)($_GET['type'] ?? ''),
    'bloom'       => (string)($_GET['bloom'] ?? ''),
    'status'      => (string)($_GET['status'] ?? ''),
];
$filtered  = implode('', $f) !== '';
$bloomName = array_column($bloomLevels, 'name_th', 'code');
$sel = fn (string $key, string $value) => $f[$key] === $value ? ' selected' : '';
?>
<?php /* ==================== หัวหน้า + ปุ่มเพิ่มข้อสอบ ==================== */ ?>
<div class="page-head">
    <div class="page-head__text">
        <h1>คลังข้อสอบ</h1>
        <p>จัดเก็บ ค้นหา และจัดการข้อสอบแยกตามรายวิชาและบท</p>
    </div>
    <div class="btn-row">
        <?php /* นำเข้าจากไฟล์ (Import.html) ยังไม่มี route */ ?>
        <a class="btn btn--primary" href="<?= Security::escape($appUrl) ?>/questions/create"><?= ui_icon('pen', 20) ?>เพิ่มข้อสอบ</a>
    </div>
</div>

<?php /* ==================== ปุ่มสรุปจำนวนตามสถานะ (กดแล้วกรอง) ==================== */ ?>
<?php if ($statusCounts !== null): ?>
    <nav class="stats" aria-label="จำนวนข้อสอบตามสถานะ">
        <?php foreach (['' => 'ข้อสอบทั้งหมด', 'draft' => 'ฉบับร่าง', 'published' => 'เผยแพร่', 'archived' => 'เก็บถาวร'] as $st => $label): ?>
            <a class="stat" href="<?= Security::escape($appUrl . '/questions' . ($st !== '' ? '?status=' . $st : '')) ?>"<?= $f['status'] === $st ? ' aria-current="page"' : '' ?>>
                <?= Security::escape($label) ?> <b><?= (int)($statusCounts[$st === '' ? 'all' : $st] ?? 0) ?></b> ข้อ
            </a>
        <?php endforeach; ?>
    </nav>
<?php endif; ?>

<?php /* ==================== ตัวกรอง 6 ช่อง + ตารางข้อสอบ ==================== */ ?>
<section class="card card--flush">
    <form class="filters" method="get" action="<?= Security::escape($appUrl) ?>/questions" role="search">
        <div class="field field--search">
            <label class="field__label" for="q">ค้นหา</label>
            <div class="search"><?= ui_icon('search', 20) ?><input class="input" id="q" name="q" type="search" value="<?= Security::escape($f['q']) ?>" placeholder="ข้อความในโจทย์ หรือรหัสข้อสอบ"></div>
        </div>
        <div class="field">
            <label class="field__label" for="f-course">รายวิชา</label>
            <select class="select" id="f-course" name="courses_id">
                <option value="">ทั้งหมด</option>
                <option value="none"<?= $sel('courses_id', 'none') ?>>คลังกลาง (ยังไม่ผูกวิชา)</option>
                <?php foreach ($courses as $c): ?>
                    <option value="<?= (int)$c['id_courses'] ?>"<?= $sel('courses_id', (string)(int)$c['id_courses']) ?>><?= Security::escape($c['code'] . ' ' . $c['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="field">
            <label class="field__label" for="f-chapter">บท</label>
            <select class="select" id="f-chapter" name="chapters_id"<?= $chapters ? '' : ' disabled' ?>>
                <option value=""><?= $chapters ? 'ทั้งหมด' : 'เลือกรายวิชาก่อน' ?></option>
                <?php foreach ($chapters as $ch): ?>
                    <option value="<?= (int)$ch['id_chapters'] ?>"<?= $sel('chapters_id', (string)(int)$ch['id_chapters']) ?>>บทที่ <?= (int)$ch['chapters_no'] ?> <?= Security::escape($ch['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="field">
            <label class="field__label" for="f-type">ประเภท</label>
            <select class="select" id="f-type" name="type">
                <option value="">ทั้งหมด</option>
                <?php foreach ($questionTypeLabels as $value => $label): ?>
                    <option value="<?= $value ?>"<?= $sel('type', $value) ?>><?= Security::escape($label) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="field">
            <label class="field__label" for="f-bloom">ระดับ Bloom</label>
            <select class="select" id="f-bloom" name="bloom"<?= $bloomLevels ? '' : ' disabled' ?>>
                <option value="">ทั้งหมด</option>
                <?php foreach ($bloomLevels as $b): ?>
                    <option value="<?= Security::escape($b['code']) ?>"<?= $sel('bloom', (string)$b['code']) ?>><?= Security::escape($b['name_th']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="field">
            <label class="field__label" for="f-status">สถานะ</label>
            <select class="select" id="f-status" name="status">
                <option value="">ทั้งหมด</option>
                <?php foreach ($questionStatusLabels as $value => $label): ?>
                    <option value="<?= $value ?>"<?= $sel('status', $value) ?>><?= Security::escape($label) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="btn-row">
            <button class="btn btn--primary" type="submit">ค้นหา</button>
            <?php if ($filtered): ?><a class="btn btn--ghost" href="<?= Security::escape($appUrl) ?>/questions">ล้างตัวกรอง</a><?php endif; ?>
        </div>
    </form>

    <?php if (empty($questions)): ?>
        <div class="empty">
            <?= ui_icon('bank', 40) ?>
            <b><?= $filtered ? 'ไม่พบข้อสอบที่ตรงกับเงื่อนไข' : 'ยังไม่มีข้อสอบในคลัง' ?></b>
            <span><?= $filtered ? 'ลองเปลี่ยนตัวกรอง หรือกดล้างตัวกรอง' : 'เริ่มจากปุ่ม "เพิ่มข้อสอบ" ด้านบน' ?></span>
        </div>
    <?php else: ?>
        <div class="table-wrap">
            <table class="table">
                <caption class="sr-only">รายการข้อสอบในคลัง</caption>
                <thead>
                    <tr>
                        <th scope="col">รหัสข้อสอบ</th>
                        <th scope="col">ตัวอย่างข้อความโจทย์</th>
                        <th scope="col">รายวิชา / บท</th>
                        <th scope="col">ประเภท</th>
                        <th scope="col">ระดับ Bloom</th>
                        <th scope="col" class="num">คะแนน</th>
                        <th scope="col">สถานะ</th>
                        <th scope="col" class="num">จัดการ</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($questions as $q): ?>
                        <?php $id = (int)$q['id_questions']; ?>
                        <tr>
                            <td class="q-code"><?= Security::escape(question_code($id)) ?></td>
                            <td>
                                <?php if (!empty($q['scenario_id'])): ?><span class="q-scn">สถานการณ์ <?= Security::escape((string)($q['scenario_title'] ?? '#' . (int)$q['scenario_id'])) ?></span><?php endif; ?>
                                <span class="q-stem"><?= Security::escape((string)$q['text']) ?></span>
                            </td>
                            <td>
                                <?php if (!empty($q['courses_id'])): ?>
                                    <b><?= Security::escape((string)($q['course_code'] ?? '')) ?></b>
                                    <span class="q-where"><?= !empty($q['chapters_id']) ? 'บทที่ ' . (int)($q['chapters_no'] ?? 0) . ' ' . Security::escape((string)($q['chapter_name'] ?? '')) : 'ยังไม่ระบุบท' ?></span>
                                <?php else: ?>
                                    <span class="q-where">คลังกลาง</span>
                                <?php endif; ?>
                            </td>
                            <td class="nowrap"><?= Security::escape($questionTypeLabels[$q['type']] ?? (string)$q['type']) ?></td>
                            <td class="nowrap"><?= Security::escape((string)($q['bloom_name_th'] ?? $bloomName[$q['bloom_levels_code']] ?? $q['bloom_levels_code'])) ?></td>
                            <td class="num"><?= Security::escape(question_score($q['manual_max_score'] ?? null)) ?></td>
                            <td><span class="badge badge--<?= Security::escape((string)$q['status']) ?>"><?= Security::escape($questionStatusLabels[$q['status']] ?? (string)$q['status']) ?></span></td>
                            <td class="num nowrap">
                                <a class="icon-btn" href="<?= Security::escape($appUrl . '/questions/' . $id) ?>" title="ดูข้อสอบ" aria-label="ดูข้อสอบ <?= Security::escape(question_code($id)) ?>"><?= ui_icon('file', 20) ?></a>
                                <a class="icon-btn" href="<?= Security::escape($appUrl . '/questions/' . $id . '/edit') ?>" title="แก้ไขข้อสอบ" aria-label="แก้ไขข้อสอบ <?= Security::escape(question_code($id)) ?>"><?= ui_icon('pen', 20) ?></a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <div class="table-foot">แสดง <?= count($questions) ?> ข้อ</div>
    <?php endif; ?>
</section>
<?php require dirname(__DIR__) . '/includes/footer.php'; ?>
