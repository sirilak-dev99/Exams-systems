# Frontend Starter และ Design System (งานข้อ 4)

ระบบจัดการคลังข้อสอบและทดสอบออนไลน์ · PHP Views + CSS กลาง (ตอนนี้ไม่มีไฟล์ JS กลาง)
ดีไซน์ตาม `frontend-starter-m0` และ prototype `exam-bank-html` (คู่มือหน้าจอ อาจารย์ / นิสิต / แอดมิน)

## ไฟล์

| ไฟล์ | หน้าที่ |
|---|---|
| `public/assets/css/app.css` | Design System: สี ตัวอักษร Layout, Button, Input, Table, Badge, Card, Tabs, Modal, Toast, Alert (มีแค่สไตล์ ยังไม่มี JS เปิด/ปิด) |
| `app/Views/includes/header.php` | `<head>`, ฟอนต์, CSS · เลือก layout |
| `app/Views/includes/navbar.php` | เมนูซ้ายตามบทบาท + แถบบน (header.php include ให้เมื่อ layout = app) |
| `app/Views/includes/menu.php` | โครงเมนูทั้งระบบแยกตามบทบาท (อาจารย์ / นิสิต / ฝ่ายวิชาการ / แอดมิน) |
| `app/Views/includes/footer.php` | ปิดหน้า + โหลด JS เฉพาะหน้า (`$pageJs` ถ้ามี) |
| `app/Views/errors/403.php`, `404.php` | หน้าไม่มีสิทธิ์ / ไม่พบหน้า |

## สร้างหน้าใหม่

```php
<?php
$pageTitle = 'จัดการชุดสอบ';                 // Controller ส่งมาก็ได้
$pageJs    = [];                              // JS เฉพาะหน้า (ถ้ามี) เช่น ['assets/js/exams.js']
require dirname(__DIR__) . '/includes/header.php';
?>
<div class="page-head">
  <div class="page-head__text"><h1>จัดการชุดสอบ</h1><p>คำอธิบาย</p></div>
  <a class="btn btn--primary" href="...">+ สร้างชุดสอบ</a>
</div>
<section class="card">...</section>
<?php require dirname(__DIR__) . '/includes/footer.php'; ?>
```

| `$layout` | ได้อะไร | ค่าเริ่มต้นเมื่อ |
|---|---|---|
| `app` | เมนูซ้าย + แถบบน (navbar.php) | เข้าสู่ระบบแล้ว |
| `blank` | กล่องกลางจอ | ยังไม่เข้าสู่ระบบ (เช่น 404, 403) |
| `auth` | แบ่งสองฝั่ง แผงแบรนด์ + ฟอร์ม | ตั้งเองในหน้า Login |

เพิ่มเมนูใหม่ที่ `includes/menu.php`: `$live('ชื่อ', 'icon', '/path', เงื่อนไขสิทธิ์, 'คำอธิบาย', ['/path/*'])` · ยังไม่มี route ใช้ `$soon('ชื่อ', 'icon')`

## หลักการ

- ซ่อนเมนูตามบทบาทเป็นแค่ความสะดวก การป้องกันจริงอยู่ที่ `Security::require*` ใน Controller (403)
- ตัวอักษรพื้นฐาน 17px ปุ่มและช่องกรอกสูงอย่างน้อย 48px (ผู้ใช้หลักเป็นอาจารย์สูงวัย)
- กด Tab เห็นกรอบ focus ชัด
- หน้าที่ยังไม่มี route แสดงเป็น "เร็วๆ นี้" ไม่ลิงก์ไปหน้า 404
