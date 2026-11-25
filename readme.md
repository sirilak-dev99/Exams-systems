# ระบบจัดการคลังข้อสอบและทดสอบออนไลน์กลางของคณะ (Exam System)

ระบบเว็บแอปพลิเคชันสำหรับจัดการคลังข้อสอบและจัดสอบออนไลน์ พัฒนาด้วย **PHP (MVC Pattern)** และ **MySQL / MariaDB** บนสภาพแวดล้อมพัฒนา **XAMPP (Apache + phpMyAdmin)**

---

## สภาพแวดล้อมและเทคโนโลยีที่ใช้ (Tech Stack)
- **Backend:** PHP 8.x (Custom MVC, PDO Prepared Statements)
- **Database:** MySQL / MariaDB (XAMPP) ชื่อฐานข้อมูล `exam_system`
- **Web Server:** Apache (XAMPP) พร้อมเปิดใช้งาน `mod_rewrite` ผ่าน `.htaccess`
- **Frontend (ปัจจุบัน):** PHP Views (HTML/CSS) + Flatpickr (เลือกวัน-เวลารูปแบบ 24 ชั่วโมง)
- **Timezone:** `Asia/Bangkok` (+07:00)

---

## โครงสร้างโปรเจกต์และหน้าที่ของไฟล์

```text
code-exam-system/
├── app/
│   ├── Controllers/          # รับ Request ตรวจสอบสิทธิ์/ข้อมูล และประสาน Model กับ View
│   │   ├── AuthController.php    # จัดการ Login, Logout และ Session ผู้ใช้
│   │   ├── ExamController.php    # จัดการสร้างชุดข้อสอบ (/exams/create) และบันทึกข้อมูล
│   │   └── HomeController.php    # แสดงหน้าหลัก / Dashboard
│   ├── Models/               # ติดต่อฐานข้อมูลด้วย PDO (SQL Queries)
│   │   ├── ExamModel.php         # จัดการตาราง exams (สร้างชุดข้อสอบ, ดึงรายการตามผู้สร้าง)
│   │   ├── QuestionModel.php     # จัดการคลังคำถามและตัวเลือก
│   │   └── UserModel.php         # ดึงข้อมูลผู้ใช้และบทบาท (users, roles, user_roles)
│   └── Views/                # หน้าจอแสดงผล (HTML + PHP)
│       ├── auth/                 # หน้าเข้าสู่ระบบ (Login)
│       ├── exams/                # หน้าจัดการชุดข้อสอบ (เช่น create.php)
│       ├── includes/             # ส่วนประกอบร่วม (header.php, footer.php)
│       ├── errors/               # หน้าแสดงข้อผิดพลาด (403, 404 ฯลฯ)
│       ├── attempts/             # หน้าการทำข้อสอบของนิสิต
│       ├── courses/              # หน้าจัดการรายวิชา
│       ├── questions/            # หน้าจัดการคำถาม
│       ├── reports/              # หน้ารายงานและสถิติผลสอบ
│       ├── users/                # หน้าจัดการผู้ใช้
│       └── dashboard.php         # หน้าแดชบอร์ดหลังเข้าสู่ระบบ
├── config/                   # ไฟล์ตั้งค่าระบบและฐานข้อมูล
│   ├── app.php                   # ค่าคอนฟิกแอปพลิเคชัน (ชื่อแอป, URL, Debug, Timezone)
│   ├── database.php              # ค่าเชื่อมต่อฐานข้อมูล (อ่านค่าจาก .env)
│   └── env.php                   # ตัวโหลดค่าตัวแปรสภาพแวดล้อมจากไฟล์ .env
├── core/                     # คลาสแกนกลางของเฟรมเวิร์ก MVC
│   ├── App.php                   # บูตระบบ โหลดคอนฟิก และ Autoload คลาส
│   ├── Controller.php            # คลาสแม่ของ Controller (เมธอด view(), redirect())
│   ├── Database.php              # จัดการการเชื่อมต่อ PDO แบบ Singleton
│   ├── Logger.php                # บันทึก Log ข้อผิดพลาดลงโฟลเดอร์ logs/
│   ├── Router.php                # จับคู่ HTTP Method + URL Path เข้ากับ Controller
│   └── Security.php              # Session, CSRF Token, Escape HTML และ RBAC (requireLogin/requireRole)
├── database/                 # สคริปต์ฐานข้อมูลและข้อมูลตั้งต้น
│   ├── exam_system_db2.sql       # โครงสร้างตาราง, Foreign Keys, Checks และ Triggers
│   └── seed_users.php            # สคริปต์สร้างบัญชีผู้ใช้ทดสอบ
├── logs/                     # ไฟล์ Log การทำงานและ Error (ไม่ commit ขึ้น Git)
├── public/                   # Document Root ที่เปิดให้เข้าถึงจากเบราว์เซอร์
│   ├── assets/                   # ไฟล์ CSS, JS, รูปภาพ
│   ├── .htaccess                 # ส่งต่อ URL ทั้งหมดเข้า index.php (Clean URLs)
│   └── index.php                 # จุดเริ่มต้น (Entry Point) ของทุก Request
├── routes/
│   └── web.php                   # ไฟล์กำหนดเส้นทาง URL (GET / POST) ทั้งหมดของระบบ
├── .env                      # ค่าตั้งค่าลับเฉพาะเครื่อง (ห้าม commit ขึ้น Git)
├── .gitignore                # รายชื่อไฟล์/โฟลเดอร์ที่ยกเว้นไม่ส่งขึ้น Git
└── README.md                 # เอกสารคู่มือโปรเจกต์
```

---

## การตั้งค่าเซิร์ฟเวอร์และแอปพลิเคชัน (Server & App Setup)

### 1. ตั้งค่า XAMPP (Apache & MySQL)
1. วางโฟลเดอร์โปรเจกต์ไว้ใน `htdocs` ของ XAMPP (เช่น `D:\XAMPP\htdocs\code-exam-system-starter\code-exam-system`)
2. เปิด **XAMPP Control Panel** แล้วกด **Start** ทั้ง **Apache** และ **MySQL**
3. ตรวจสอบพอร์ต MySQL ที่ใช้งาน (ในทีมใช้พอร์ต **`3307`** หรือค่าเริ่มต้น `3306` — ต้องตั้งให้ตรงกับไฟล์ `.env`)
4. ตรวจสอบว่า Apache เปิดใช้งาน `mod_rewrite` และโฟลเดอร์ `public/.htaccess` ทำงานปกติ เพื่อให้เข้า URL แบบ `/public/login` หรือ `/public/exams/create` ได้โดยไม่ติด 404

### 2. ตั้งค่าไฟล์ `.env`
สร้างไฟล์ `.env` ไว้ที่โฟลเดอร์ `code-exam-system/` (คัดลอกจาก `.env.example` หากมี) และปรับค่าให้ตรงกับเครื่องของตนเอง:

```env
APP_NAME="ระบบสอบกลางคณะ"
APP_ENV=development
APP_DEBUG=true
APP_URL=http://localhost/code-exam-system-starter/code-exam-system/public
APP_TIMEZONE=Asia/Bangkok

DB_HOST=127.0.0.1
DB_PORT=3307
DB_NAME=exam_system
DB_USER=root
DB_PASS=
```
> **ข้อควรระวัง:** ห้าม commit ไฟล์ `.env`, รหัสผ่านจริง หรือข้อมูลผู้ใช้จริงขึ้น Git โดยเด็ดขาด

### 3. ตั้งค่า Timezone ของ PHP
ระบบตั้งค่าเวลาเป็นเขตเวลาประเทศไทย (`Asia/Bangkok`) เพื่อให้วัน-เวลาเปิดสอบ ปิดสอบ และเวลาบันทึกข้อมูล (`open_at`, `close_at`, `created_at`) ตรงกับเวลาจริง หากเวลาในหน้าเว็บคลาดเคลื่อนให้ตรวจสอบว่าได้ตั้งค่า `date_default_timezone_set('Asia/Bangkok');` ใน `public/index.php` หรือ `config/app.php` แล้ว

### 4. ติดตั้งฐานข้อมูล (`exam_system`)
1. เปิด phpMyAdmin ที่ `http://localhost/phpmyadmin`
2. นำเข้า (Import) ไฟล์โครงสร้างฐานข้อมูลจาก `database/exam_system_db2.sql`
3. ตรวจสอบว่าตาราง `roles` มีบทบาทพื้นฐานครบ 4 บทบาท (`student`, `instructor`, `academic_staff`, `admin`)
4. รันสคริปต์ `database/seed_users.php` หรือสร้างบัญชีทดสอบพร้อม `password_hash` ที่เข้ารหัสด้วย PHP `password_hash()`

ตัวอย่างการสร้างรหัสผ่านแบบ Hash ผ่าน Terminal:
```bash
php -r "echo password_hash('CHANGE_THIS_PASSWORD', PASSWORD_DEFAULT), PHP_EOL;"
```

---

## การเข้าใช้งานและเส้นทางหลัก (Routes)

| HTTP Method | URL Path (ต่อท้าย `/public`) | Controller & Action | สิทธิ์การเข้าถึง (Role) | คำอธิบาย |
| :--- | :--- | :--- | :--- | :--- |
| `GET` | `/` | `HomeController@index` | ผู้ใช้ที่ล็อกอินแล้ว | หน้าแดชบอร์ดหลัก |
| `GET` | `/login` | `AuthController@showLogin` | ทุกคน | หน้าฟอร์มเข้าสู่ระบบ |
| `POST` | `/login` | `AuthController@login` | ทุกคน | ตรวจสอบข้อมูลและสร้าง Session |
| `POST` | `/logout` | `AuthController@logout` | ผู้ใช้ที่ล็อกอินแล้ว | ออกจากระบบและล้าง Session |
| `GET` | `/exams/create` | `ExamController@create` | `instructor`, `admin` | หน้าสร้างชุดข้อสอบและแสดงรายการที่สร้าง |
| `POST` | `/exams/create` | `ExamController@store` | `instructor`, `admin` | บันทึกชุดข้อสอบลงตาราง `exams` |

---

## ระบบความปลอดภัยและสิทธิ์การเข้าถึง (Auth & RBAC)
- **การเข้าสู่ระบบ:** รองรับทั้ง `email` หรือ `student_code` (รหัสนิสิต 8 หลัก) คู่กับรหัสผ่าน
- **เงื่อนไขบัญชี:** อนุญาตเฉพาะบัญชีที่ `active = 1` และ `deleted_at IS NULL`
- **รหัสผ่าน:** ตรวจสอบด้วย `password_verify()` เทียบกับ `users.password_hash` (บัญชีที่ไม่มี hash จะล็อกอินด้วยรหัสผ่านไม่ได้)
- **Session & CSRF:** มีการ regenerate Session ID หลังล็อกอินสำเร็จ และตรวจ `csrf_token` ในทุกฟอร์มแบบ `POST`
- **RBAC (Role-Based Access Control):**
  - `student` — นิสิตผู้เข้าสอบ
  - `instructor` — อาจารย์ผู้สอน / ผู้ออกข้อสอบ
  - `academic_staff` — เจ้าหน้าที่ฝ่ายวิชาการ
  - `admin` — ผู้ดูแลระบบ
  - ป้องกันหน้าจัดการด้วย `Security::requireLogin()` และ `Security::requireRole(['instructor', 'admin'])`

---

## แนวทางการใช้ Git ในทีม (Git Workflow)

### โครงสร้าง Branch
- `main` — โค้ดเวอร์ชันเสถียรสำหรับส่งงาน/ใช้งานจริง **(ห้ามแก้โค้ดตรงบน `main`)**
- `dev` — สาขากลางสำหรับรวมงานของสมาชิกทุกคนและทดสอบร่วมกัน
- `feature/<ชื่องาน>` — แตกออกจาก `dev` ทุกครั้งที่ทำฟีเจอร์ใหม่ เช่น `feature/exam-create`, `feature/question-bank`

### ขั้นตอนการทำงานสำหรับสมาชิกใหม่
```bash
# 1. โคลนโปรเจกต์
git clone https://github.com/sirilak-dev99/Exams-systems.git
cd Exams-systems

# 2. สลับไปที่ branch dev และดึงโค้ดล่าสุด
git checkout dev
git pull origin dev

# 3. แตก branch ใหม่สำหรับงานของตัวเอง
git checkout -b feature/your-feature-name

# 4. เมื่อทำงานเสร็จให้ Commit และ Push ขึ้น GitHub แล้วเปิด Pull Request เข้า dev
git add .
git commit -m "feat: อธิบายสิ่งที่ทำ"
git push -u origin feature/your-feature-name
```

---

## สถานะการพัฒนาปัจจุบัน
- [x] ตั้งโครงสร้างโปรเจกต์ MVC และ Git Repo (`main` / `dev`)
- [x] ออกแบบและนำเข้าฐานข้อมูล `exam_system` (พร้อม Foreign Keys, Constraints, Triggers)
- [x] ระบบ Login / Logout, Session, CSRF และ RBAC พื้นฐาน
- [x] ฟีเจอร์สร้างชุดข้อสอบ (`/exams/create`) พร้อม Flatpickr 24 ชม. และแสดงรายการชุดข้อสอบของผู้สร้าง
- [ ] บันทึกประวัติการเข้าสู่ระบบลง `login_attempt_logs` และ Redirect แยกหน้าตาม Role
- [ ] ดึงรายวิชาที่เปิดสอน (`course_offerings`) มาแสดงเป็น Dropdown ในหน้าสร้างข้อสอบ
- [ ] ระบบจัดการคลังคำถาม (`questions`, `question_versions`, `question_options`) และผูกข้อสอบเข้าชุดสอบ (`exam_questions`)
- [ ] ระบบเข้าสอบของนิสิต (`attempts`), ตรวจให้คะแนน และรายงานสถิติข้อสอบ