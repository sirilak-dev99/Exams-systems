# ผลตรวจ API (26 พ.ย. 2569 · branch feature/frontend-starter หลังดึง dev ถึง PR #5)

ทดสอบทุก route ใน `routes/web.php` (45 route) ด้วยบัญชี guest / นิสิต / อาจารย์ / ฝ่ายวิชาการ / แอดมิน
ส่ง `Accept: application/json` · POST ทดสอบด้วยข้อมูลว่าง `{}` หรือ id ที่ไม่มีจริง (99999) เท่านั้น — ไม่ได้ลบหรือแก้ข้อมูลจริง

## ผลรวม

- ✅ สิทธิ์ถูกทุก route: ไม่ login → 401 · ไม่มีสิทธิ์ → 403 · id ไม่มีจริง → 404 · ข้อมูลไม่ครบ → 422
- ✅ ทุก endpoint JSON ตอบรูปแบบ `{ data, error }` ตาม contract
- ⚠️ พบ 5 เรื่องด้านล่าง

## เรื่องที่พบ

| # | เรื่อง | ผลกระทบ | วิธีแก้ที่เสนอ |
|---|---|---|---|
| 1 | **ค้นหาแล้ว 500** — `GET /programs?q=` และ `GET /courses?q=` ได้ `PDOException SQLSTATE[HY093]` | ค้นหาในหน้าหลักสูตร/รายวิชาใช้ไม่ได้ | `ProgramModel::getAll()` และ `CourseModel::getAllCourses()` ใช้ `:q` ซ้ำ 2 ครั้ง (PDO ปิด emulate prepares) → เปลี่ยนเป็น `:q_code` / `:q_name` |
| 2 | **419 กลายเป็น 500** — CSRF ไม่ผ่านทุกที่ (`/login`, `/questions`, ฯลฯ) ได้ HTTP 500 แต่ข้อความยังเป็น "419 Page Expired" | contract ข้อ 3 กำหนด 419 `CSRF_MISMATCH` · เบราว์เซอร์/frontend แยกกรณีไม่ได้ | Apache ไม่รู้จักรหัส 419 → ใช้ `header('HTTP/1.1 419 Page Expired')` แทน `http_response_code(419)` หรือเปลี่ยนเป็น 403 + code `CSRF_MISMATCH` |
| 3 | **`/exams/create` ไม่รองรับ JSON** — GET ได้ 302, POST JSON ได้หน้า HTML | ใช้ผ่าน API ไม่ได้ (หน้าเว็บใช้ได้ปกติ) | เพิ่ม `Security::wantsJson()` แบบ Controller อื่น หรือรวมเข้ากับ `POST /exams` ในอนาคต |
| 4 | **404 / 500 ตอบเป็น HTML** แม้ขอ JSON (`Router` และ `public/index.php`) | frontend อ่าน error ไม่ได้ | ใน Router / index.php ถ้า `Security::wantsJson()` ให้ตอบ `{ data: null, error: { code: 'NOT_FOUND' / 'INTERNAL_SERVER_ERROR' } }` |
| 5 | **error code นอก contract** — `INVALID_YEAR`, `PROGRAM_VERSION_NOT_FOUND`, `OFFERING_NOT_FOUND`, `GROUP_NOT_FOUND`, `DUPLICATE_*`, `*_IN_USE` | contract ข้อ 3 ไม่ได้ระบุ | เพิ่มลงตารางใน `docs/api_contract.md` |

## route ที่หน้าฝั่งอาจารย์ต้องใช้ (ข้อเสนอให้ backend · ยังไม่ได้เพิ่มใน `routes/web.php`)

> **สถานะ:** frontend ทำแค่ view ไว้ ส่วน route/Controller/Model เป็นงานของ backend (ข้อ 22, 23, 27)
> ร่างที่เคยเขียนและทดสอบไว้เก็บที่ branch `draft/teaching-backend` ในเครื่องของเฟม (ยังไม่ได้ push) — ใช้ต่อ แก้ หรือเขียนใหม่ได้ตามสะดวก

| Method | Path | Handler | สิทธิ์ | JSON |
|---|---|---|---|---|
| GET | `/my-courses` | `TeachingController@myCourses` | instructor, admin | `{ offerings }` |
| GET | `/my-courses/{id}` | `TeachingController@courseShow` (id = `id_course_offerings`) | ผู้สอนของรายวิชา, admin | `{ offering, stats, staff, sections, students, chapters, concepts, learningOutcomes, contents, questions, exams }` |
| GET | `/exams` | `TeachingController@examIndex` | instructor, admin | `{ exams, status_counts }` |
| GET | `/questions` | `QuestionController@index` | `question.manage` | `{ questions }` |
| GET | `/questions/create` | `QuestionController@create` | `question.manage` | (หน้าฟอร์ม) |
| POST | `/questions` | `QuestionController@store` | `question.manage` | 201 `{ question }` · 422 มี `error.details` รายช่อง |
| GET | `/questions/{id}` | `QuestionController@show` | `question.manage` | `{ question, options, versions, learningOutcomes, concepts }` |
| GET | `/questions/{id}/edit` | `QuestionController@edit` | `question.manage` | (หน้าฟอร์ม) |
| POST | `/questions/{id}` | `QuestionController@update` | `question.manage` | `{ question, version_no }` — แก้ไขแล้วสร้างฉบับใหม่ใน `question_versions` |

ไฟล์ในร่าง: `app/Controllers/TeachingController.php`, `app/Controllers/QuestionController.php`, `app/Models/TeachingModel.php`, `app/Models/QuestionBankModel.php`
ทุก SQL ใช้ชื่อพารามิเตอร์ไม่ซ้ำกันในคำสั่งเดียว · บันทึกข้อสอบอยู่ใน transaction

ทดสอบการบันทึกข้อสอบจริงแล้ว (เพิ่มปรนัย / ถูก-ผิด / อัตนัย, ส่งไม่ครบได้ 422 พร้อมข้อความรายช่อง, แก้ไขได้ฉบับที่ 2 ที่อ้างฉบับเดิม) แล้วลบข้อมูลทดสอบออก

## ข้อที่ต้องตัดสินใจร่วมกัน

- `questions.scoring_logic` — ตอนนี้ตั้ง `auto` (ตรวจอัตโนมัติ) และ `manual` (อัตนัย) ถ้ามีรายการค่าที่ตั้งใจไว้ ให้แจ้ง
- ข้อสอบจับคู่ (`matching`) — `question_options` ยังไม่มีที่เก็บข้อความฝั่งที่จับคู่ ตอนนี้ระบบตอบ 422 ว่ายังบันทึกไม่ได้

## ขอแก้ฝั่ง backend 2 จุด (งานข้อ 7 และ 9 ของ Frontend)

### 1. หน้า 403 (ข้อ 9) — `core/Security.php`
view `app/Views/errors/403.php` พร้อมแล้ว ใน `requireRole()` และ `requirePermission()` เปลี่ยนส่วนที่ตอบหน้าเว็บจาก

```php
http_response_code(403);
echo '403 Forbidden - คุณไม่มีสิทธิ์เข้าถึงหน้านี้';
exit;
```

เป็น

```php
http_response_code(403);
$message = 'คุณไม่มีสิทธิ์เข้าถึงหน้านี้';          // หรือข้อความเดิมของ requirePermission
require dirname(__DIR__) . '/app/Views/errors/403.php';
exit;
```

(คำขอ JSON ยังตอบ `{ error: { code: 'FORBIDDEN' } }` เหมือนเดิม)

### 2. ✅ แก้แล้ว — ออกจากระบบแล้วไปหน้า 404 (ข้อ 7)

- `app/Controllers/AuthController.php` บรรทัดท้ายของ `logout()`: `$this->redirect('auth/login')` → `$this->redirect('/login')` (แก้ 1 บรรทัดนี้เท่านั้น)
- เพิ่ม view `app/Views/errors/419.php` ที่ `logout()` เรียกเมื่อ CSRF ไม่ผ่าน (เดิมไม่มีไฟล์ → ขึ้น "ไม่พบหน้าที่ต้องการ") มีปุ่มออกจากระบบอีกครั้งด้วย token ใหม่
- ทดสอบแล้ว: ออกจากระบบทั้ง 4 บทบาท → หน้า Login · หลังออกเปิดหน้าที่ต้อง login → ถูกส่งไป Login · CSRF หมดอายุ → หน้า 419 แล้วออกได้ · JSON → 200
