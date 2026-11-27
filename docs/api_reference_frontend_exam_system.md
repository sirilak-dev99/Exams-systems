# คู่มืออ้างอิง API สำหรับทีม Frontend (Exam System)

เอกสารสรุปรหัสข้อผิดพลาด บทบาทสิทธิ์ และตาราง Endpoint แยกตามโมดูล เพื่อให้ทีม Frontend ใช้เชื่อมหน้าจอและเรียกข้อมูลจาก Backend

* **Base URL (Development):** `http://localhost/code-exam-system-starter/code-exam-system/public`
* **หมายเหตุการเรียกแบบ API:** แนบ Header `Accept: application/json` (หรือ `X-Requested-With: XMLHttpRequest`) เพื่อให้ระบบคืนค่า JSON โครงสร้าง `{ "data": ..., "error": ... }` เสมอ หากเปิดผ่านเบราว์เซอร์โดยตรง ระบบจะแสดงหน้า HTML View ตามปกติ

---

## 3. มาตรฐานรหัสข้อผิดพลาด (HTTP Status & Error Codes)

| HTTP Status | ตัวอย่าง `error.code` | ความหมาย | ตัวอย่างสถานการณ์ในระบบ |
| :---: | :--- | :--- | :--- |
| **400** | `INVALID_ID`, `BAD_REQUEST` | รูปแบบ Request ไม่ถูกต้อง | ID ไม่ใช่จำนวนเต็มบวก หรือ JSON ผิดรูปแบบ |
| **401** | `UNAUTHORIZED`, `INVALID_CREDENTIALS` | ยังไม่ได้เข้าสู่ระบบ / รหัสผ่านผิด | ไม่มี Session, เซสชันหมดอายุ (Idle เกิน 30 นาที), อีเมลหรือรหัสผ่านไม่ถูกต้อง |
| **403** | `FORBIDDEN`, `ACCOUNT_INACTIVE` | ไม่มีสิทธิ์เข้าถึง | `student` เรียก API สร้างข้อสอบ หรือบัญชีถูกระงับ (`active = 0`) |
| **404** | `NOT_FOUND`, `OFFERING_NOT_FOUND`, `GROUP_NOT_FOUND` | ไม่พบทรัพยากร | ไม่พบ ID ที่ระบุ หรือรายการถูก Soft Delete แล้ว |
| **405** | `METHOD_NOT_ALLOWED` | เมธอดไม่ถูกต้อง | เรียก `/logout` ด้วย GET หรือส่งเมธอดที่ไม่รองรับ |
| **409** | `PROGRAM_IN_USE`, `OFFERING_IN_USE`, `EXAM_IN_PROGRESS`, `DUPLICATE_*` | ข้อมูลขัดแย้ง/ซ้ำซ้อน | ลบหลักสูตรที่มีรุ่นผูกอยู่, รหัสวิชาซ้ำ, แก้ข้อสอบขณะมีผู้กำลังสอบ |
| **419** | `CSRF_MISMATCH` | โทเค็นความปลอดภัยหมดอายุ | CSRF Token ไม่ตรงหรือหมดอายุ (เฉพาะการส่งผ่านฟอร์มหน้าเว็บ) |
| **422** | `VALIDATION_ERROR`, `INVALID_TIME_WINDOW`, `INVALID_ACCESS_CODE` | ข้อมูลไม่ผ่านเงื่อนไขตรวจสอบ | `duration_min <= 0`, `close_at <= open_at`, รหัสเข้าสอบไม่ถูกต้อง |
| **500** | `INTERNAL_SERVER_ERROR` | ข้อผิดพลาดฝั่งเซิร์ฟเวอร์ | ปัญหาการเชื่อมต่อฐานข้อมูลหรือข้อผิดพลาดไม่คาดคิด |

---

## 4. บทบาทผู้ใช้งานและสิทธิ์ (Roles & Permissions)

### 4.1 บทบาทผู้ใช้งาน (Roles)
| Role Key | คำอธิบาย |
| :--- | :--- |
| `student` | นิสิตผู้เข้าสอบ |
| `instructor` | อาจารย์ผู้สอน / ผู้ออกข้อสอบ / ผู้ตรวจข้อสอบ |
| `academic_staff` | เจ้าหน้าที่ฝ่ายวิชาการ (จัดการหลักสูตร รายวิชา เปิดสอน) |
| `admin` | ผู้ดูแลระบบ (ได้รับสิทธิ์เต็มทุกโมดูลอัตโนมัติผ่าน `Security::hasPermission()` พร้อมรายการ `permissions` ครบใน Session) |

### 4.2 รายการรหัสสิทธิ์มาตรฐาน (Permission Codes)
* **การจัดการระบบและผู้ใช้:** `user.manage`, `system.settings`
* **ข้อมูลหลักการศึกษา:** `program.manage`, `course.manage`, `course.view`
* **คลังข้อสอบและชุดสอบ:** `question.manage`, `exam.manage`, `exam.grade`
* **การเข้าสอบของนิสิต:** `attempt.take`, `attempt.view_own`
* **รายงานและสถิติ:** `report.view`

---

## 5. ตาราง Endpoint แยกตามโมดูล

### โมดูล 1: Authentication & Session
| Method | Endpoint | รูปแบบตอบกลับ | สิทธิ์ (Roles / Permission) | คำอธิบาย |
| :---: | :--- | :---: | :--- | :--- |
| `GET` | `/login` | HTML | Public | แสดงหน้าจอเข้าสู่ระบบ |
| `POST` | `/login` | HTML / JSON | Public | เข้าสู่ระบบด้วย `identifier` (`email` หรือ `student_code`) และ `password` |
| `POST` | `/logout` | HTML / JSON | Authenticated | ออกจากระบบและล้าง Session |

### โมดูล 2: หลักสูตร รายวิชา และการเปิดสอน
| Method | Endpoint | รูปแบบตอบกลับ | สิทธิ์ (Roles / Permission) | คำอธิบาย |
| :---: | :--- | :---: | :--- | :--- |
| `GET` | `/programs` | HTML / JSON | `academic_staff`, `admin` | แสดงรายการหลักสูตร |
| `POST` | `/programs` | JSON | `academic_staff`, `admin` | สร้างหลักสูตรใหม่ |
| `POST` | `/programs/{id}` | JSON | `academic_staff`, `admin` | แก้ไขหลักสูตร หรือลบด้วย `_method: "DELETE"` (บล็อก `409 PROGRAM_IN_USE` หากมีรุ่นผูกอยู่) |
| `POST` | `/programs/{id}/delete` | JSON | `academic_staff`, `admin` | ลบหลักสูตร (กรณีไม่มีรุ่นผูกอยู่) |
| `GET` / `POST` | `/programs/{id}/versions` | JSON | `academic_staff`, `admin` | ดูและสร้างรุ่นหลักสูตร |
| `GET` / `POST` | `/course-groups` | JSON | `academic_staff`, `admin` | ดูและสร้างกลุ่มวิชา |
| `GET` / `POST` | `/courses` | JSON | `academic_staff`, `admin` | ดูและสร้างรายวิชา |
| `GET` | `/course-offerings` | JSON | `instructor`, `academic_staff`, `admin` | ดึงรายการวิชาที่เปิดสอน |

### โมดูล 3: ชุดข้อสอบ (`/exams`)
| Method | Endpoint | รูปแบบตอบกลับ | สิทธิ์ (Roles / Permission) | คำอธิบาย |
| :---: | :--- | :---: | :--- | :--- |
| `GET` | `/exams` | HTML / JSON | `exam.manage` | ดึงรายการชุดข้อสอบ (รองรับกรอง `courses_id`, `course_offerings_id`) |
| `GET` | `/exams/create` | HTML / JSON | `exam.manage` | แสดงหน้าฟอร์มสร้างชุดข้อสอบ |
| `POST` | `/exams` หรือ `/exams/create` | JSON | `exam.manage` | สร้างชุดข้อสอบใหม่ |
| `GET` | `/exams/{id}` | JSON | `exam.manage` | ดูรายละเอียดชุดข้อสอบ |
| `POST` | `/exams/{id}` | JSON | `exam.manage` | ลบชุดข้อสอบแบบ Soft Delete ด้วย `_method: "DELETE"` |
| `POST` | `/exams/{id}/sections` | JSON | `exam.manage` | ผูกกลุ่มเรียนเข้ากับชุดข้อสอบ |
| `POST` | `/exams/{id}/questions` | JSON | `exam.manage` | ผูก/ปรับรายการคำถามในชุดข้อสอบ (คืน `409 EXAM_IN_PROGRESS` หากมีผู้กำลังสอบ) |

### โมดูล 4: คลังคำถาม (`/questions`)
| Method | Endpoint | รูปแบบตอบกลับ | สิทธิ์ (Roles / Permission) | คำอธิบาย |
| :---: | :--- | :---: | :--- | :--- |
| `GET` | `/questions` | JSON | `question.manage` | ค้นหาและดึงรายการคำถามจากคลัง |
| `GET` | `/questions/create` | HTML / JSON | `question.manage` | แสดงหน้าฟอร์มสร้างคำถามใหม่ |
| `POST` | `/questions` | JSON | `question.manage` | สร้างคำถามใหม่พร้อมเวอร์ชันและตัวเลือก |

### โมดูล 5: การเข้าสอบของนิสิต (`/attempts`)
| Method | Endpoint | รูปแบบตอบกลับ | สิทธิ์ (Roles / Permission) | คำอธิบาย |
| :---: | :--- | :---: | :--- | :--- |
| `POST` | `/exams/{id}/attempts` | JSON | `student` | เริ่มทำข้อสอบ (ตรวจสอบสิทธิ์, ช่วงเวลา, โควตา, รหัสเข้าสอบ) |
| `PUT` | `/attempts/{id}/answers` | JSON | `student` | บันทึกคำตอบ / Autosave ระหว่างสอบ |
| `POST` | `/attempts/{id}/submit` | JSON | `student` | ส่งข้อสอบและปิด Attempt |

### โมดูล 6: การตรวจให้คะแนน รายงาน สถิติ และ Audit Logs
| Method | Endpoint | รูปแบบตอบกลับ | สิทธิ์ (Roles / Permission) | คำอธิบาย |
| :---: | :--- | :---: | :--- | :--- |
| `GET` | `/exams/{id}/submissions` | JSON | `exam.grade` | ดูรายการส่งข้อสอบของนิสิตในชุดสอบ |
| `PATCH` | `/attempt-answers/{id}/score` | JSON | `exam.grade` | ให้คะแนน/แก้ไขคะแนนข้อสอบอัตนัย (บันทึกประวัติลง `score_versions`) |
| `GET` | `/exams/{id}/statistics` | JSON | `report.view` | ดูสถิติภาพรวมและรายข้อ (KR-20, Cronbach's Alpha) |
| `POST` | `/exam-statistics/{id}/set-current` | JSON | `report.view` | กำหนดรอบผลสถิติปัจจุบัน (`is_current`) |
| `GET` | `/reports/exams/{id}/export` | File (CSV/Excel) | `report.view` | ส่งออกรายงานคะแนนสอบเป็นไฟล์ CSV หรือ Excel |
| `GET` | `/audit-logs` | JSON | `admin` | ดูประวัติการใช้งานระบบ (`audit_logs`) |

### โมดูล 7: หน้าเว็บตามบทบาท (Hybrid Page Routes)
> หากเปิดผ่านเบราว์เซอร์จะคืนหน้า HTML View จาก `app/Views/` และหากส่ง `Accept: application/json` จะคืน `{ "data": { "page": "...", "title": "..." }, "error": null }`

| กลุ่ม | Method & Endpoint | สิทธิ์ (Roles) | ไฟล์ View (`app/Views/...`) |
| :--- | :--- | :--- | :--- |
| **แอดมิน** | `GET /admin/users` | `admin` | `admin/users.php` |
| **แอดมิน** | `GET /admin/roles` | `admin` | `admin/roles.php` |
| **แอดมิน** | `GET /admin/students` | `admin`, `academic_staff` | `admin/students.php` |
| **แอดมิน** | `GET /admin/news` | `admin`, `academic_staff` | `admin/news.php` |
| **แอดมิน** | `GET /admin/settings` | `admin` | `admin/settings.php` |
| **นิสิต** | `GET /student/courses` | `student` | `student/courses.php` |
| **นิสิต** | `GET /student/exams` | `student` | `student/exams.php` |
| **นิสิต** | `GET /student/results` | `student` | `student/results.php` |
| **นิสิต** | `GET /student/news` | `student` | `student/news.php` |
| **นิสิต** | `GET /student/profile` | `student` | `student/profile.php` |
| **นำเข้าข้อมูล** | `GET /import` | `admin`, `academic_staff`, `instructor` | `import/index.php` |
| **นำเข้าข้อมูล** | `GET /import/courses` | `admin`, `academic_staff` | `import/courses.php` |
| **นำเข้าข้อมูล** | `GET /import/students` | `admin`, `academic_staff` | `import/students.php` |