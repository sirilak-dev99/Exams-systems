<?php
$kindLabels = [
    'quiz'     => 'สอบย่อย (Quiz)',
    'midterm'  => 'สอบกลางภาค',
    'final'    => 'สอบปลายภาค',
    'pre_post' => 'สอบก่อนเรียน / หลังเรียน',
];

$modeLabels = [
    'fixed'     => 'กำหนดข้อสอบด้วยตนเอง',
    'blueprint' => 'สุ่มข้อสอบตามสัดส่วนที่กำหนด',
];

$statusLabels = [
    'draft'     => 'ฉบับร่าง',
    'published' => 'เผยแพร่แล้ว',
    'closed'    => 'ปิดรับการเข้าสอบ',
    'archived'  => 'จัดเก็บเข้าคลังแล้ว',
];
?>

<?php require dirname(__DIR__) . '/includes/header.php'; ?>

<div class="container" style="max-width: 720px; margin: 2rem auto;">
    <h2>สร้างชุดข้อสอบ</h2>

    <?php if (!empty($error)): ?>
        <div class="alert alert-danger"><?= Security::escape((string)$error) ?></div>
    <?php endif; ?>

    <form method="POST" action="/code-exam-system-starter/code-exam-system/public/exams/create">
        <input type="hidden" name="csrf_token" value="<?= Security::escape(Security::csrfToken()) ?>">

        <div style="margin-bottom: 1rem;">
            <label>ชื่อชุดข้อสอบ</label><br>
            <input type="text" name="title" required value="<?= Security::escape((string)($old['title'] ?? '')) ?>" placeholder="เช่น สอบกลางภาค 88734465">
        </div>

        <div style="margin-bottom: 1rem;">
            <label>ประเภทการสอบ</label><br>
            <select name="kind">
                <?php foreach ($kindLabels as $value => $label): ?>
                    <option value="<?= $value ?>" <?= (($old['kind'] ?? 'quiz') === $value) ? 'selected' : '' ?>>
                        <?= Security::escape($label) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div style="margin-bottom: 1rem;">
            <label>โหมดข้อสอบ</label><br>
            <select name="mode">
                <?php foreach ($modeLabels as $value => $label): ?>
                    <option value="<?= $value ?>" <?= (($old['mode'] ?? 'fixed') === $value) ? 'selected' : '' ?>>
                        <?= Security::escape($label) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div style="margin-bottom: 1rem;">
            <label>ระยะเวลาสอบ (นาที)</label><br>
            <input type="number" name="duration_min" min="1" required value="<?= Security::escape((string)($old['duration_min'] ?? '60')) ?>">
        </div>

        <div style="margin-bottom: 1rem;">
            <label>จำนวนครั้งที่สอบได้สูงสุด</label><br>
            <input type="number" name="max_attempts" min="1" required value="<?= Security::escape((string)($old['max_attempts'] ?? '1')) ?>">
        </div>

        <div style="margin-bottom: 1rem;">
            <label>วัน-เวลาเปิดสอบ</label><br>
            <input type="text" name="open_at" class="datetime-24h" required
                value="<?= Security::escape((string)($old['open_at'] ?? date('Y-m-d H:i'))) ?>">
        </div>

        <div style="margin-bottom: 1rem;">
            <label>วัน-เวลาปิดสอบ</label><br>
            <input type="text" name="close_at" class="datetime-24h" required
                value="<?= Security::escape((string)($old['close_at'] ?? date('Y-m-d H:i', strtotime('+7 days')))) ?>">
        </div>

        <button type="submit">บันทึกชุดข้อสอบ</button>
    </form>

    <hr style="margin: 2rem 0;">

    <h3>ชุดข้อสอบที่สร้างไว้</h3>
    <?php if (empty($exams)): ?>
        <p>ยังไม่มีชุดข้อสอบ</p>
    <?php else: ?>
        <table border="1" cellpadding="8" cellspacing="0" style="width: 100%; border-collapse: collapse;">
            <thead>
                <tr>
                    <th>ชื่อชุดข้อสอบ</th>
                    <th>ประเภท</th>
                    <th>เวลา (นาที)</th>
                    <th>สถานะ</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($exams as $exam): ?>
                    <tr>
                        <td><?= Security::escape((string)$exam['title']) ?></td>
                        <td><?= Security::escape($kindLabels[$exam['kind']] ?? (string)$exam['kind']) ?></td>
                        <td><?= Security::escape((string)$exam['duration_min']) ?></td>
                        <td><?= Security::escape($statusLabels[$exam['status']] ?? (string)$exam['status']) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>

</div>

<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
<script>
    flatpickr(".datetime-24h", {
        enableTime: true,
        time_24hr: true,
        dateFormat: "Y-m-d H:i",
    });
</script>

<?php require dirname(__DIR__) . '/includes/footer.php'; ?>