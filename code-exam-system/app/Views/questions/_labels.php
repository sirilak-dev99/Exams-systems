<?php
/**
 * ============================================================
 *  _labels.php — ข้อความไทยและฟังก์ชันช่วยของคลังข้อสอบ (questions/index · show · form ใช้ร่วมกัน)
 * ------------------------------------------------------------
 *  ค่าตรงกับ CHECK constraint ใน database/exam_system_db2.sql
 *    questions.type   : multiple_choice, true_false, short_answer, essay, matching
 *    questions.status : draft, published, archived
 *  ฟังก์ชัน
 *    question_code(1)        → 'Q-0001'  (รหัสที่แสดงบนหน้าจอ)
 *    question_score('10.00') → '10'      (NULL = 1 คะแนน ตามค่าเริ่มต้นของ prototype)
 * ============================================================
 */
$questionTypeLabels = [
    'multiple_choice' => 'ปรนัย',
    'true_false'      => 'ถูก/ผิด',
    'short_answer'    => 'ตอบสั้น',
    'essay'           => 'อัตนัย',
    'matching'        => 'จับคู่',
];
$questionTypeHints = [
    'multiple_choice' => 'เลือกคำตอบจากตัวเลือก (ถูกได้มากกว่า 1 ข้อ)',
    'true_false'      => 'ตอบว่าถูกหรือผิด',
    'short_answer'    => 'พิมพ์คำตอบสั้น ระบบตรวจเทียบคำตอบที่ยอมรับ',
    'essay'           => 'เขียนตอบ อาจารย์ให้คะแนนในหน้าตรวจข้อเขียน',
    'matching'        => 'จับคู่ข้อความสองฝั่ง',
];
$questionStatusLabels = [
    'draft'     => 'ฉบับร่าง',
    'published' => 'เผยแพร่แล้ว',
    'archived'  => 'เก็บถาวร',
];
// ระดับ Bloom ใช้ชื่อจากตาราง bloom_levels (name_th) ที่ Controller ส่งมา — ยังไม่มีข้อมูลในตาราง จึงไม่ตั้งค่าไว้ล่วงหน้า

if (!function_exists('question_score')) {
    /** manual_max_score → ข้อความคะแนน (NULL = 1 คะแนนตามค่าเริ่มต้นของ prototype), '2.50' → '2.5', '10.00' → '10' */
    function question_score(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '1';
        }
        return rtrim(rtrim(number_format((float)$value, 2, '.', ''), '0'), '.');
    }
}

if (!function_exists('question_code')) {
    /** id_questions → รหัสที่แสดงบนหน้าจอ เช่น 1 → Q-0001 (ตาม prototype) */
    function question_code(int|string $id): string
    {
        return 'Q-' . str_pad((string)(int)$id, 4, '0', STR_PAD_LEFT);
    }
}
