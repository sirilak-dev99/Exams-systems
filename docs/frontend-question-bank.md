# คลังข้อสอบ — view ที่ทำไว้แล้ว และสิ่งที่ backend ต้องเพิ่ม

> **สถานะ:** view พร้อมใช้ แต่ยังไม่มี route — route/Controller/Model เป็นงานของ backend (จอย) ร่างที่ทดสอบแล้วเก็บไว้ที่ branch `draft/teaching-backend` ในเครื่องของเฟม

หน้าจอทำตาม prototype `QuestionBank.html`, `QuestionView.html`, `QuestionForm.html`
view อยู่ใน `code-exam-system/app/Views/questions/` ใช้แบบเดียวกับหน้าอื่นของโปรเจกต์ (`Security::escape`, ฟอร์ม POST + `csrf_token`, ไม่ใช้ API)
ชื่อตัวแปรและชื่อฟิลด์ตั้งตามคอลัมน์ในตาราง `questions`, `question_versions`, `question_options`, `chapters`, `bloom_levels`, `learning_outcomes`

| ไฟล์ | หน้า |
|---|---|
| `questions/index.php` | คลังข้อสอบ (รายการ + ตัวกรอง) |
| `questions/show.php` | ดูรายละเอียดข้อสอบ |
| `questions/form.php` | เพิ่ม / แก้ไขข้อสอบ (ใช้ไฟล์เดียวทั้งสองแบบ) |
| `questions/_labels.php` | ข้อความไทยของ type / status (ใช้ร่วมกัน) |

## 1. Route ที่ต้องเพิ่มใน `routes/web.php`

```php
// คลังข้อสอบ — ใส่ /questions/create ก่อน /questions/{id} เพราะ Router จับคู่ตามลำดับ
$router->get('/questions', [QuestionController::class, 'index']);
$router->get('/questions/create', [QuestionController::class, 'create']);
$router->post('/questions', [QuestionController::class, 'store']);
$router->get('/questions/{id}', [QuestionController::class, 'show']);
$router->get('/questions/{id}/edit', [QuestionController::class, 'edit']);
$router->post('/questions/{id}', [QuestionController::class, 'update']);
```

ทุก method: `Security::requirePermission('question.manage')` (instructor และ admin มีสิทธิ์นี้ใน seed) และตรวจ `csrf_token` ใน POST

## 2. ข้อมูลที่แต่ละ view ต้องการ

ไม่ส่งตัวแปรไหนมา view ก็ยังแสดงได้ (เป็นหน้าว่าง / "ยังไม่มีข้อมูล")

### `index` → `$this->view('questions/index', [...])`

| ตัวแปร | รูปแบบ |
|---|---|
| `pageTitle` | `'คลังข้อสอบ'` |
| `questions` | list ของ `id_questions, text, type, bloom_levels_code, bloom_name_th, status, courses_id, course_code, course_name, chapters_id, chapters_no, chapter_name, scenario_id, scenario_title, manual_max_score, updated_at` (ไม่รวม `deleted_at IS NOT NULL`) |
| `courses` | list ของ `id_courses, code, name` สำหรับตัวกรอง |
| `chapters` | list ของ `id_chapters, chapters_no, name` ของรายวิชาที่กรองอยู่ (ไม่กรองวิชา = ส่ง `[]`) |
| `bloomLevels` | list ของ `code, name_th` จาก `bloom_levels` เรียงตาม `sort_order` |
| `statusCounts` | `['all' => n, 'draft' => n, 'published' => n, 'archived' => n]` (ไม่ส่ง = ไม่แสดงแถบตัวเลข) |

ตัวกรอง GET ที่หน้าส่งมา: `q`, `courses_id` (`''` = ทั้งหมด, `'none'` = คลังกลาง `courses_id IS NULL`), `chapters_id`, `type`, `bloom`, `status`

### `show` → `$this->view('questions/show', [...])`

| ตัวแปร | รูปแบบ |
|---|---|
| `question` | ข้อมูลข้อสอบ + `bloom_name_th, course_code, course_name, chapters_no, chapter_name, scenario_title, scenario_content, created_by_name` · ไม่พบ = ส่ง `null` (หน้าแสดง "ไม่พบข้อสอบ") |
| `options` | ตัวเลือกของฉบับล่าสุด: `label, text, is_correct, sort_order, image` เรียงตาม `sort_order` |
| `versions` | `id_question_versions, version_no, created_at, created_by_name` เรียงใหม่ → เก่า |
| `learningOutcomes` | CLO ของฉบับล่าสุด: `code, description` |
| `concepts` | (ไม่บังคับ) `main_code, main_name, sub_code, sub_name` |

### `create` / `edit` → `$this->view('questions/form', [...])`

| ตัวแปร | รูปแบบ |
|---|---|
| `question` | `create` = `null` · `edit` = `id_questions, type, courses_id, chapters_id, bloom_levels_code, scenario_id, text, explanation, manual_max_score, shuffle_options, student_visible, status` |
| `options` | ตัวเลือกของฉบับล่าสุด `label, text, is_correct` |
| `selectedLoIds` | list ของ `id_learning_outcomes` ที่ผูกอยู่ |
| `latestVersionNo` | เลขฉบับล่าสุด |
| `courses` | `id_courses, code, name` |
| `chapters` | **ทุกบท**ของทุกวิชาใน `courses`: `id_chapters, courses_id, chapters_no, name` (หน้ากรองตามวิชาที่เลือกเอง ไม่ต้องมี API) |
| `learningOutcomes` | **ทุก CLO** ของทุกวิชาใน `courses`: `id_learning_outcomes, courses_id, code, description` |
| `bloomLevels` | `code, name_th` |
| `scenarios` | (ไม่บังคับ) `id_question_scenarios, title` |
| `old`, `error`, `errors` | เมื่อบันทึกไม่ผ่าน แบบ `ExamController@store` · `errors` = `['text' => 'กรุณากรอกโจทย์', ...]` แสดงใต้ช่องนั้น |

## 3. ค่าที่ฟอร์มส่งมา (POST `/questions` และ `/questions/{id}`)

| ฟิลด์ | ใช้กับ | ค่า |
|---|---|---|
| `csrf_token` | ทุกชนิด | `Security::csrfToken()` |
| `type` | ทุกชนิด | `multiple_choice` / `true_false` / `short_answer` / `essay` / `matching` |
| `courses_id` | ทุกชนิด | id หรือ `''` = คลังกลาง (NULL) |
| `chapters_id` | ทุกชนิด | id หรือ `''` |
| `bloom_levels_code` | ทุกชนิด | **บังคับ** (คอลัมน์ NOT NULL) |
| `scenario_id` | ทุกชนิด | id หรือ `''` (มีเมื่อส่ง `scenarios` มา) |
| `text` | ทุกชนิด | **บังคับ** |
| `explanation` | ทุกชนิด | ไม่บังคับ |
| `options[i][label]`, `options[i][text]`, `options[i][is_correct]=1` | ปรนัย | ติ๊กถูกได้หลายข้อ |
| `options[i][label]`, `options[i][text]` | ตอบสั้น | ทุกแถวคือคำตอบที่ยอมรับ → บันทึก `is_correct = 1` |
| `tf_answer` | ถูก/ผิด | `true` / `false` → สร้าง option `ถูก` / `ผิด` 2 แถว |
| `manual_max_score` | อัตนัย | ตัวเลข ≥ 0 |
| `learning_outcome_ids[]` | ทุกชนิด | ไม่บังคับ |
| `shuffle_options` | ปรนัย | `1` เมื่อติ๊ก (ไม่ติ๊ก = ไม่ส่ง → 0) |
| `student_visible` | ทุกชนิด | `1` เมื่อติ๊ก |
| `save_action` | ทุกชนิด | `draft` (บันทึกร่าง) / `draft_add` (บันทึกแล้วกลับมาฟอร์มเพิ่มข้อใหม่) / `publish` (สถานะ `published`) |

- ช่องของชนิดที่ไม่ได้เลือกจะถูกปิด (disabled) จึงไม่ถูกส่ง แต่ Controller ควรอ่านเฉพาะฟิลด์ของ `type` ที่เลือกเสมอ
- ตาม prototype: **แก้ไขแล้วเก็บเป็นฉบับใหม่** — สร้างแถวใน `question_versions` (`version_no` + 1) และ `question_options` / `question_learning_outcomes` ผูกกับฉบับใหม่
- บันทึกสำเร็จ: redirect ไป `/questions/{id}` (หรือ `/questions/create` เมื่อ `save_action = draft_add`)

## 4. เรื่องที่ต้องตัดสินใจ

1. **ตาราง `bloom_levels` ยังว่าง** — ต้องใส่ข้อมูลก่อน (ฟอร์มบังคับเลือก Bloom) และตกลง `code` ของแต่ละระดับ
2. **ข้อสอบจับคู่ (`matching`)** — `question_options` มีแค่ `label` (VARCHAR 20) กับ `text` ยังไม่มีที่เก็บข้อความฝั่งที่จับคู่ ตอนนี้ฟอร์มแสดงข้อความว่ายังบันทึกไม่ได้
3. **`questions.scoring_logic`** — NOT NULL แต่ยังไม่มี CHECK ว่าใช้ค่าอะไร ฟอร์มไม่ได้ส่งค่านี้ ให้ Controller ตั้งตาม `type`
4. **ยังไม่ได้ทำ** (มีใน prototype แต่ต้องมี route เพิ่ม): นำเข้าจากไฟล์, เลือกหลายข้อแล้วใส่ชุดสอบ / ย้ายหัวข้อ / เผยแพร่-เก็บถาวรทีละหลายข้อ, คัดลอก, ประวัติการใช้งาน, ค่าความยาก/อำนาจจำแนก

## 5. ฝั่ง frontend เมื่อ route พร้อม

แก้บรรทัดเดียวใน `app/Views/includes/menu.php` ให้เมนู "คลังข้อสอบ" ของอาจารย์กดได้:

```php
$soon('คลังข้อสอบ', 'bank'),
// เป็น
$live('คลังข้อสอบ', 'bank', '/questions', Security::hasPermission('question.manage'), 'จัดเก็บ ค้นหา และจัดการข้อสอบ', ['/questions/create']),
```
