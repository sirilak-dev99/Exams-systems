# Frontend Starter และ Design System (งานข้อ 4)

ระบบจัดการคลังข้อสอบและทดสอบออนไลน์ · PHP Views + CSS/JS กลาง (ไม่ใช้เฟรมเวิร์ก JS)
ดีไซน์ตาม `frontend-starter-m0` และ prototype `exam-bank-html` (คู่มือหน้าจอ อาจารย์ / นิสิต / แอดมิน)

## ไฟล์

| ไฟล์ | หน้าที่ |
|---|---|
| `public/assets/css/app.css` | Design System: สี ตัวอักษร Layout, Button, Input, Table, Badge, Card, Tabs, **Modal**, **Toast**, Alert |
| `public/assets/js/api.js` | HTTP Client กลาง (fetch) อ่าน `{ data, error }` ตาม `docs/api_contract.md` |
| `public/assets/js/ui.js` | Modal, Toast, `UI.confirm()`, แสดง error ใต้ช่องกรอก, ปุ่มกำลังโหลด, ข้อความไทยของ enum |
| `app/Views/includes/header.php` | `<head>`, ฟอนต์, CSS, meta ของ API · เลือก layout |
| `app/Views/includes/navbar.php` | เมนูซ้ายตามบทบาท + แถบบน (header.php include ให้เมื่อ layout = app) |
| `app/Views/includes/menu.php` | โครงเมนูทั้งระบบแยกตามบทบาท (อาจารย์ / นิสิต / ฝ่ายวิชาการ / แอดมิน) |
| `app/Views/includes/footer.php` | ปิดหน้า + โหลด `api.js`, `ui.js` และ `$pageJs` |
| `app/Views/errors/403.php`, `404.php` | หน้าไม่มีสิทธิ์ / ไม่พบหน้า |

## สร้างหน้าใหม่

```php
<?php
$pageTitle = 'จัดการชุดสอบ';                 // Controller ส่งมาก็ได้
$pageJs    = ['assets/js/pages/exams.js'];    // JS เฉพาะหน้า (ถ้ามี)
require dirname(__DIR__) . '/includes/header.php';
?>
<div class="page-head">
  <div class="page-head__text"><h1>จัดการชุดสอบ</h1><p>คำอธิบาย</p></div>
  <button class="btn btn--primary" data-modal-open="mAdd">+ สร้างชุดสอบ</button>
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

## เรียก API

```js
const { programs } = await API.get('/programs', { status: 'active' });

const form = document.getElementById('fProgram');
const btn  = form.querySelector('[type=submit]');
UI.busy(btn, true, 'กำลังบันทึก...');
try {
  const { program } = await API.post('/programs', UI.formData(form));
  UI.toast('เพิ่มหลักสูตรแล้ว');
} catch (e) {
  if (e.code === 'VALIDATION_ERROR' && e.details) UI.showErrors(form, e.details);
  else UI.toast(e.message, 'danger');
} finally {
  UI.busy(btn, false);
}

if (await UI.confirm('ลบหลักสูตรนี้?', { detail: 'ลบแล้วกู้คืนไม่ได้', okText: 'ลบ', danger: true })) {
  await API.post('/programs/5/delete');
}
```

- path ตาม `routes/web.php` (ไม่มี `/api/v1`) · Router มีแค่ GET/POST: แก้ไข `POST /xxx/{id}` ลบ `POST /xxx/{id}/delete`
- ได้ **401** → ไปหน้า Login อัตโนมัติ แนบ `?next=` (ปิดได้ด้วย `{ noAuthRedirect: true }`)
- ต่อเน็ตไม่ได้ / ช้าเกิน 20 วินาที → `NETWORK_ERROR` / `TIMEOUT`
- backend ตอบเป็น HTML (404 ของ Router, PHP error) → `e.code` ตาม HTTP status และพิมพ์รายละเอียดใน Console
- CSRF ไม่ผ่าน (Apache เปลี่ยน 419 เป็น 500) → api.js อ่านจากข้อความแล้วคืน `CSRF_MISMATCH`
- พิมพ์ข้อมูลลง HTML ต้องผ่าน `UI.esc()` (PHP ใช้ `Security::escape()`)

## Modal / Toast

```html
<button class="btn btn--primary" data-modal-open="mAdd">เพิ่ม</button>
<div class="modal" id="mAdd" role="dialog" aria-modal="true" aria-labelledby="mAddTitle">
  <div class="modal__box">
    <div class="modal__head"><h2 id="mAddTitle">เพิ่มหลักสูตร</h2>
      <button class="modal__close" type="button" data-modal-close aria-label="ปิด">✕</button></div>
    ...
    <div class="modal__foot"><button class="btn btn--secondary" data-modal-close>ยกเลิก</button><button class="btn btn--primary">บันทึก</button></div>
  </div>
</div>
```

`UI.toast('บันทึกแล้ว')` · `UI.toast('ผิดพลาด', 'danger')` · `UI.toast('แจ้งให้ทราบ', 'info')`

## หลักการ

- ซ่อนเมนูตามบทบาทเป็นแค่ความสะดวก การป้องกันจริงอยู่ที่ `Security::require*` ใน Controller (403)
- ตัวอักษรพื้นฐาน 17px ปุ่มและช่องกรอกสูงอย่างน้อย 48px (ผู้ใช้หลักเป็นอาจารย์สูงวัย)
- กด Tab เห็นกรอบ focus ชัด · Modal กด Esc ปิดได้และวน focus อยู่ข้างใน
- หน้าที่ยังไม่มี route แสดงเป็น "เร็วๆ นี้" ไม่ลิงก์ไปหน้า 404
