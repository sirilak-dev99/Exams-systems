# API Contract — ระบบจัดการคลังข้อสอบและทดสอบออนไลน์กลางของคณะ (Exam System)

เอกสารข้อตกลงรูปแบบการรับ-ส่งข้อมูลระหว่าง **Backend** และ **Frontend** สำหรับระบบ `exam_system` (อัปเดตล่าสุดให้สอดคล้องกับโค้ดจริงและรูปแบบ Hybrid View + JSON API)

---

## 1. ข้อกำหนดทั่วไป (General Conventions)

* **Base URL (Development):** `http://localhost/code-exam-system-starter/code-exam-system/public`
* **รูปแบบการตอบกลับแบบ Hybrid (Content Negotiation):**
  * **เรียกผ่าน JS Fetch / Postman (API):** ต้องส่ง Header `Accept: application/json` (หรือ `X-Requested-With: XMLHttpRequest`) ระบบจะตอบกลับเป็น JSON โครงสร้าง `{ data, error }` เสมอ (`Content-Type: application/json; charset=utf-8`)
  * **เปิดผ่านเบราว์เซอร์โดยตรง (Web View):** Endpoint ที่เป็นหน้าเว็บจะคืนค่าเป็น HTML View โดยอัตโนมัติ (ตรวจสอบผ่าน `Security::wantsJson()`)
* **การลบข้อมูล (Method Override):** เพื่อรองรับทั้ง HTML Form และ API การลบข้อมูลใช้ `POST` พร้อมส่ง `"_method": "DELETE"` ใน Request Body (หรือใช้ Endpoint `/delete` เฉพาะจุดที่ระบุ)
* **Timezone & Datetime Format:** ใช้เขตเวลา `Asia/Bangkok` (+07:00) รูปแบบ 24 ชั่วโมง `YYYY-MM-DD HH:MM:SS` (เช่น `2026-11-25 10:06:00`)
* **Authentication & CSRF:**
  * ใช้ Session Cookie (`HttpOnly`, `SameSite=Lax`, Idle Timeout 30 นาที)
  * กรณีส่งฟอร์มผ่านหน้าเว็บ (ไม่ส่ง `Accept: application/json`) ต้องแนบ `csrf_token` หรือ `_csrf`
* **ค่า Enum และคีย์ในฐานข้อมูล:** ส่งและรับเป็น**ภาษาอังกฤษ**ตาม Schema ส่วนข้อความภาษาไทยให้ฝั่ง Frontend แปลงแสดงผล (UI Label)

---

## 2. รูปแบบ Response มาตรฐาน (`{ data, error }`)

ทุก Endpoint ที่เรียกด้วย `Accept: application/json` จะตอบกลับในโครงสร้าง JSON เดียวกันเสมอ:

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

กรณีรายการข้อมูลพร้อมแบ่งหน้า (200 OK)
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

กรณีเกิดข้อผิดพลาด (4xx, 5xx)
{
  "data": null,
  "error": {
    "code": "VALIDATION_ERROR",
    "message": "กรุณากรอกข้อมูลให้ครบถ้วนและถูกต้อง"
  }
}

ตัวอย่าง Request / Response ของ Endpoint สำคัญ
6.1 เข้าสู่ระบบ (POST /login)
Request Body:
{
  "identifier": "admin@buu.ac.th",
  "password": "your-password"
}

(รองรับทั้ง email และ student_code ในฟิลด์ identifier)
Response (200 OK):
{
  "data": {
    "user": {
      "id_users": 2,
      "email": "admin@buu.ac.th",
      "student_code": null,
      "display_name": "ผู้ดูแลระบบทดสอบ",
      "roles": ["admin"],
      "permissions": [
        "user.manage",
        "system.settings",
        "program.manage",
        "course.manage",
        "course.view",
        "question.manage",
        "exam.manage",
        "exam.grade",
        "attempt.take",
        "attempt.view_own",
        "report.view"
      ]
    }
  },
  "error": null
}


Response กรณีบัญชีถูกปิดใช้งาน (403 Forbidden):
{
  "data": null,
  "error": {
    "code": "ACCOUNT_INACTIVE",
    "message": "บัญชีผู้ใช้นี้ถูกระงับการใช้งาน กรุณาติดต่อผู้ดูแลระบบ"
  }
}


6.2 สร้างชุดข้อสอบ (POST /exams)
Request Body:
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

Response (201 Created):
{
  "data": {
    "exam": {
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
      "status": "draft"
    }
  },
  "error": null
}
