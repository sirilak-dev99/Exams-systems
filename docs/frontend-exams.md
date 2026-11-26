# จัดการชุดสอบ — view ที่ทำไว้แล้ว และสิ่งที่ backend ต้องเพิ่ม

> **สถานะ:** view พร้อมใช้ แต่ยังไม่มี route — route/Controller/Model เป็นงานของ backend (จอย) ร่างที่ทดสอบแล้วเก็บไว้ที่ branch `draft/teaching-backend` ในเครื่องของเฟม

หน้าจอทำตาม prototype `Exams.html` อยู่ที่ `code-exam-system/app/Views/exams/index.php`
ใช้แบบเดียวกับหน้าอื่น (`Security::escape`, ตัวกรองเป็นฟอร์ม GET, ไม่ใช้ API)

## 1. Route ที่ต้องเพิ่มใน `routes/web.php`

```php
$router->get('/exams', [ExamController::class, 'index']);   // ใส่ก่อน /exams/{id} ถ้าจะเพิ่มหน้ารายละเอียดภายหลัง
```

สิทธิ์: `Security::requireRole(['instructor', 'admin'])` แบบเดียวกับ `create` / `store`

## 2. ข้อมูลที่ view ต้องการ → `$this->view('exams/index', [...])`

| ตัวแปร | รูปแบบ |
|---|---|
| `pageTitle` | `'จัดการชุดสอบ'` |
| `exams` | list ของ `id_exams, title, kind, mode, status, open_at, close_at, duration_min, course_code, course_name, term_label, section_nos` · ไม่รวม `deleted_at IS NOT NULL` · อาจารย์เห็นเฉพาะของตัวเอง (`created_by`) แบบ `getExamsByCreator()` |
| `courses` | list ของ `id_courses, code, name` สำหรับตัวกรอง |
| `terms` | list ของ `id_academic_terms, academic_year, term_no` สำหรับตัวกรอง |
| `statusCounts` | `['all' => n, 'draft' => n, 'published' => n, 'closed' => n]` (ไม่ส่ง = ไม่แสดงแถบตัวเลข) |
| `total` | จำนวนทั้งหมดก่อนกรอง (ไม่ส่ง = ใช้จำนวนใน `exams`) |

- `term_label` = `term_no/academic_year` เช่น `1/2569` (จาก `course_offerings` → `academic_terms`)
- `section_nos` = กลุ่มเรียนที่สอบได้ รวมเป็นข้อความ เช่น `01, 02` (จาก `exam_eligibilities` → `sections.section_no`)
- ตัวกรอง GET: `courses_id`, `academic_terms_id`, `kind`, `status`, `q` (ค้นในชื่อชุดสอบ)
- สถานะ **กำลังสอบ** view คำนวณเองจาก `status = 'published'` และเวลาปัจจุบันอยู่ระหว่าง `open_at` – `close_at` ไม่ต้องเก็บใน DB

## 3. ลิงก์ในหน้าที่ยังไม่มี route

| ลิงก์ | ใช้ทำอะไร |
|---|---|
| `GET /exams/{id}` | ดูรายละเอียดชุดสอบ (`ExamDetail.html`) — ชื่อชุดสอบและปุ่มดูชี้มาที่นี่ |
| `GET /exams/{id}/edit` | แก้ไขชุดสอบที่เป็นฉบับร่าง |
| เมนู ⋯ | คุมสอบ, ดูผลสอบ, บันทึกคำตอบจากกระดาษ, คัดลอกเป็นชุดใหม่ — ตอนนี้แสดงเป็น "เร็วๆ นี้" |
| ปุ่ม "คัดลอกจากชุดสอบเดิม" | ยังกดไม่ได้ |

## 4. ตอนนี้ (ก่อนมี GET /exams) ใช้โหมดชั่วคราวที่ `/exams/create`

`exams/create.php` เลือกเองว่าจะแสดงอะไร โดยไม่ต้องแก้ `ExamController`:

| URL | แสดง |
|---|---|
| `GET /exams/create` | **รายการชุดสอบ** (include `exams/index.php`) จาก `$exams` ของ `getExamsByCreator()` — กรองสถานะ/ประเภท/ชื่อ และนับจำนวนในหน้า |
| `GET /exams/create?new=1` | ฟอร์มสร้างชุดสอบ |
| `POST /exams/create` ไม่ผ่าน (`$error` / `$old`) | ฟอร์มพร้อมค่าที่กรอกไว้ |
| `POST /exams/create` ผ่าน | `store()` redirect กลับ `/exams/create` → เห็นรายการ |

ข้อจำกัดของโหมดนี้: `getExamsByCreator()` ไม่มีข้อมูลรายวิชา / ภาค / กลุ่มเรียน คอลัมน์รายวิชาจึงแสดง "—" และไม่มีตัวกรองรายวิชา/ภาค · ปุ่มดูรายละเอียด/แก้ไขเป็น "เร็วๆ นี้" เพราะยังไม่มี `/exams/{id}`

## 5. ฝั่ง frontend เมื่อ route พร้อม

1. ลบบล็อกโหมดชั่วคราวด้านบนของ `exams/create.php` (ตั้งแต่ `$showForm = ...` ถึง `return;`)
2. ใน `exams/create.php` เปลี่ยนลิงก์ "← จัดการชุดสอบ" และปุ่ม "ยกเลิก" เป็น `/exams`
3. ใน `app/Views/includes/menu.php`

   เปลี่ยนเมนู "จัดการชุดสอบ" ให้ชี้หน้ารายการ:

```php
$live('จัดการชุดสอบ', 'clip', '/exams', $canExam, 'สร้าง กำหนดเวลา และติดตามการสอบ', ['/exams/create']),
```
