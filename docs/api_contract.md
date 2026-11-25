# API Contract — ระบบจัดการคลังข้อสอบและทดสอบออนไลน์กลางของคณะ (Exam System)

เอกสารข้อตกลงรูปแบบการรับ-ส่งข้อมูลระหว่าง **Backend** และ **Frontend** สำหรับระบบ `exam_system` เพื่อใช้ตรวจสอบร่วมกันก่อนพัฒนาหน้าจอและเชื่อมต่อ API

---

## 1. ข้อกำหนดทั่วไป (General Conventions)

* **Base URL (Development):** `http://localhost/code-exam-system-starter/code-exam-system/public/api/v1` *(หรือปรับตาม Routing ที่ตกลงในระบบจริง)*
* **Content-Type:** `application/json; charset=utf-8`
* **Timezone & Datetime Format:** ใช้เขตเวลา `Asia/Bangkok` (+07:00) และรูปแบบ 24 ชั่วโมง `YYYY-MM-DD HH:MM:SS` (เช่น `2026-11-25 10:06:00`)
* **Authentication:** Session Cookie (`HttpOnly`, `SameSite=Lax`) หรือ Bearer Token ตามที่กำหนดใน Middleware
* **ค่า Enum และคีย์ในฐานข้อมูล:** ส่งและรับเป็น**ภาษาอังกฤษ**ตาม Schema ส่วนข้อความภาษาไทยให้ฝั่ง Frontend แปลงแสดงผล (UI Label)

---

## 2. รูปแบบ Response มาตรฐาน (`{ data, error }`)

ทุก Endpoint จะตอบกลับในโครงสร้าง JSON เดียวกันเสมอ:

### กรณีสำเร็จ (`200 OK`, `201 Created`)
```json
{
  "data": {
    "id_exams": 1,
    "title": "ทดสอบชุดที่ 1",
    "status": "draft"
  },
  "error": null
}
```

### กรณีรายการข้อมูลพร้อมแบ่งหน้า (`200 OK`)
```json
{
  "data": {
    "items": [
      { "id_exams": 1, "title": "สอบกลางภาค 88734465", "kind": "midterm" }
    ],
    "pagination": {
      "page": 1,
      "per_page": 20,
      "total": 1
    }
  },
  "error": null
}
```

### กรณีเกิดข้อผิดพลาด (`4xx`, `5xx`)
```json
{
  "data": null,
  "error": {
    "code": "VALIDATION_ERROR",
    "message": "กรุณากรอกข้อมูลให้ครบถ้วนและถูกต้อง",
    "details": {
      "title": "กรุณากรอกชื่อชุดข้อสอบ",
      "close_at": "เวลาปิดสอบต้องมากกว่าเวลาเปิดสอบ"
    }
  }
}
```

---

## 3. มาตรฐานรหัสข้อผิดพลาด (HTTP Status & Error Codes)

| HTTP Status | `error.code` | ความหมาย | ตัวอย่างสถานการณ์ในระบบ |
| :---: | :--- | :--- | :--- |
| **400** | `BAD_REQUEST` | รูปแบบ Request ไม่ถูกต้อง | JSON ผิดรูปแบบ หรือพารามิเตอร์ไม่ถูกต้อง |
| **401** | `UNAUTHORIZED` | ยังไม่ได้เข้าสู่ระบบ | ไม่มี Session/Token หรือหมดอายุ |
| **403** | `FORBIDDEN` | ไม่มีสิทธิ์เข้าถึง | `student` เรียก API สร้างข้อสอบ หรือบัญชีถูกปิด (`active = 0`) |
| **404** | `NOT_FOUND` | ไม่พบทรัพยากร | ไม่พบ `id_exams` หรือรายการถูกลบ (`deleted_at IS NOT NULL`) |
| **409** | `CONFLICT` | ข้อมูลขัดแย้ง/ซ้ำซ้อน | รหัสวิชาซ้ำ, ส่งข้อสอบซ้ำ, หรือลบคำถามขณะมีนิสิตกำลังสอบ (`in_progress`) |
| **419** | `CSRF_MISMATCH` | โทเค็นความปลอดภัยหมดอายุ | CSRF Token ไม่ตรงหรือหมดอายุ (กรณีส่งผ่าน Session Form) |
| **422** | `VALIDATION_ERROR` | ข้อมูลไม่ผ่านเงื่อนไขตรวจสอบ | `duration_min <= 0`, `close_at <= open_at`, รหัสเข้าสอบไม่ครบ 5 หลัก |
| **500** | `INTERNAL_SERVER_ERROR` | ข้อผิดพลาดฝั่งเซิร์ฟเวอร์ | ปัญหาการเชื่อมต่อฐานข้อมูลหรือข้อผิดพลาดไม่คาดคิด |

---

## 4. บทบาทผู้ใช้งาน (Roles)

| Role Key | คำอธิบาย |
| :--- | :--- |
| `student` | นิสิตผู้เข้าสอบ |
| `instructor` | อาจารย์ผู้สอน / ผู้ออกข้อสอบ / ผู้ตรวจข้อสอบ |
| `academic_staff` | เจ้าหน้าที่ฝ่ายวิชาการ (จัดการหลักสูตร รายวิชา เปิดสอน) |
| `admin` | ผู้ดูแลระบบ (สิทธิ์เต็มทุกโมดูล) |

---

## 5. ตาราง Endpoint แยกตามโมดูล

### โมดูล 1: Authentication & Session (`/auth`)
| Method | Endpoint | สิทธิ์ (Roles) | คำอธิบาย |
| :---: | :--- | :--- | :--- |
| `POST` | `/auth/login` | Public | เข้าสู่ระบบด้วย `email` หรือ `student_code` และ `password` (บันทึก `login_attempt_logs`) |
| `POST` | `/auth/logout` | Authenticated | ออกจากระบบและล้าง Session |
| `GET` | `/auth/me` | Authenticated | ดึงข้อมูลโปรไฟล์และบทบาท (`roles`) ของผู้ใช้ปัจจุบัน |

### โมดูล 2: หลักสูตรและรายวิชาที่เปิดสอน (`/courses`, `/course-offerings`)
| Method | Endpoint | สิทธิ์ (Roles) | คำอธิบาย |
| :---: | :--- | :--- | :--- |
| `GET` | `/course-offerings` | `instructor`, `academic_staff`, `admin` | ดึงรายการวิชาที่เปิดสอน (`course_offerings` + `courses`) สำหรับ Dropdown สร้างชุดข้อสอบ |
| `GET` | `/courses/{id}/chapters` | `instructor`, `academic_staff`, `admin` | ดึงบทเรียน (`chapters`) ภายใต้รายวิชา |

### โมดูล 3: ชุดข้อสอบ (`/exams`)
| Method | Endpoint | สิทธิ์ (Roles) | คำอธิบาย |
| :---: | :--- | :--- | :--- |
| `GET` | `/exams` | `instructor`, `admin` | ดึงรายการชุดข้อสอบที่ผู้สอนสร้างไว้ (`created_by`) |
| `POST` | `/exams` | `instructor`, `admin` | สร้างชุดข้อสอบใหม่ (สถานะเริ่มต้น `draft`) |
| `GET` | `/exams/{id}` | `instructor`, `admin` | ดูรายละเอียดชุดข้อสอบ |
| `PUT` | `/exams/{id}` | `instructor`, `admin` | แก้ไขข้อมูลชุดข้อสอบ |
| `PATCH` | `/exams/{id}/status` | `instructor`, `admin` | เปลี่ยนสถานะ (`draft` / `published` / `closed` / `archived`) |
| `DELETE` | `/exams/{id}` | `instructor`, `admin` | ลบชุดข้อสอบแบบ Soft Delete (`deleted_at = NOW()`) |
| `POST` | `/exams/{id}/access-codes` | `instructor`, `admin` | สร้างรหัสเข้าสอบ 5 หลัก (`exam_access_codes`) |

### โมดูล 4: คลังคำถาม (`/questions`)
| Method | Endpoint | สิทธิ์ (Roles) | คำอธิบาย |
| :---: | :--- | :--- | :--- |
| `GET` | `/questions` | `instructor`, `admin` | ค้นหาและดึงรายการคำถามจากคลัง (กรองตามวิชา, บทเรียน, Bloom Level) |
| `POST` | `/questions` | `instructor`, `admin` | สร้างคำถามใหม่ พร้อม `question_versions` และ `question_options` |
| `POST` | `/exams/{id}/questions` | `instructor`, `admin` | เพิ่ม/ผูกคำถามเข้าชุดข้อสอบ (`exam_questions`) |
| `DELETE` | `/exams/{id}/questions/{eqId}` | `instructor`, `admin` | ถอดคำถามออกจากชุดข้อสอบ (คืน `409` หากมีผู้สอบกำลังทำอยู่) |

### โมดูล 5: การเข้าสอบของนิสิต (`/attempts`)
| Method | Endpoint | สิทธิ์ (Roles) | คำอธิบาย |
| :---: | :--- | :--- | :--- |
| `GET` | `/student/exams` | `student` | ดึงรายการชุดข้อสอบที่นิสิตมีสิทธิ์เข้าสอบ (`exam_eligibilities`) |
| `POST` | `/exams/{id}/attempts` | `student` | เริ่มทำข้อสอบ (ตรวจสอบสิทธิ์, ช่วงเวลา, โควตา, รหัสเข้าสอบ) |
| `PUT` | `/attempts/{id}/answers` | `student` | บันทึกคำตอบ / Autosave ระหว่างสอบ (`attempt_answers`, `attempt_answer_drafts`) |
| `POST` | `/attempts/{id}/submit` | `student` | ส่งข้อสอบและปิด Attempt |

### โมดูล 6: การตรวจให้คะแนนและสถิติ (`/grading`, `/reports`)
| Method | Endpoint | สิทธิ์ (Roles) | คำอธิบาย |
| :---: | :--- | :--- | :--- |
| `GET` | `/exams/{id}/submissions` | `instructor`, `admin` | ดูรายการส่งข้อสอบของนิสิตในชุดสอบ |
| `PATCH` | `/attempt-answers/{id}/score` | `instructor`, `admin` | ให้คะแนน/แก้ไขคะแนนข้อสอบอัตนัย (บันทึกประวัติลง `score_versions`) |
| `GET` | `/exams/{id}/statistics` | `instructor`, `academic_staff`, `admin` | ดูสถิติภาพรวมและรายข้อ (`exam_statistics`, `exam_item_statistics`) |

---

## 6. ตัวอย่าง Request / Response ของ Endpoint สำคัญ

### 6.1 เข้าสู่ระบบ (`POST /auth/login`)
**Request Body:**
```json
{
  "identifier": "prof.test@buu.ac.th",
  "password": "your-password"
}
```
*(รองรับทั้ง `email` และ `student_code` ในฟิลด์ `identifier`)*

**Response (`200 OK`):**
```json
{
  "data": {
    "user": {
      "id_users": 3,
      "email": "prof.test@buu.ac.th",
      "student_code": null,
      "display_name": "อาจารย์ทดสอบ",
      "roles": ["instructor"]
    }
  },
  "error": null
}
```

**Response กรณีบัญชีถูกปิดใช้งาน (`403 Forbidden`):**
```json
{
  "data": null,
  "error": {
    "code": "ACCOUNT_INACTIVE",
    "message": "บัญชีผู้ใช้นี้ถูกระงับการใช้งาน กรุณาติดต่อผู้ดูแลระบบ",
    "details": null
  }
}
```

---

### 6.2 สร้างชุดข้อสอบ (`POST /exams`)
**Request Body:**
```json
{
  "course_offerings_id": 1,
  "courses_id": 1,
  "title": "ทดสอบชุดที่ 1",
  "kind": "midterm",
  "mode": "fixed",
  "duration_min": 60,
  "grace_period_min": 5,
  "max_attempts": 2,
  "open_at": "2026-11-25 10:06:00",
  "close_at": "2026-12-02 11:06:00",
  "show_answer": "never",
  "shuffle_questions": 0,
  "require_exam_code": 0,
  "require_location_consent": 0
}
```

**Response (`201 Created`):**
```json
{
  "data": {
    "id_exams": 1,
    "course_offerings_id": 1,
    "courses_id": 1,
    "created_by": 3,
    "title": "ทดสอบชุดที่ 1",
    "kind": "midterm",
    "mode": "fixed",
    "duration_min": 60,
    "max_attempts": 2,
    "open_at": "2026-11-25 10:06:00",
    "close_at": "2026-12-02 11:06:00",
    "status": "draft",
    "created_at": "2026-11-25 10:07:16"
  },
  "error": null
}
```

---

## 7. ตารางจับคู่ค่า Enum กับข้อความภาษาไทย (สำหรับ Frontend)

| ฟิลด์ | ค่าในฐานข้อมูล / API | ข้อความแนะนำบนหน้าจอ (UI) |
| :--- | :--- | :--- |
| `exams.kind` | `quiz` | สอบย่อย (Quiz) |
| `exams.kind` | `midterm` | สอบกลางภาค |
| `exams.kind` | `final` | สอบปลายภาค |
| `exams.kind` | `pre_post` | สอบก่อนเรียน / หลังเรียน |
| `exams.mode` | `fixed` | กำหนดข้อสอบเอง (ชุดคงที่) |
| `exams.mode` | `blueprint` | สุ่มข้อสอบตามโครงสร้าง (Blueprint) |
| `exams.status` | `draft` | ฉบับร่าง |
| `exams.status` | `published` | เผยแพร่แล้ว |
| `exams.status` | `closed` | ปิดรับข้อสอบ |
| `exams.status` | `archived` | จัดเก็บแล้ว |