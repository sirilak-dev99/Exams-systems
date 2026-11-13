# ระบบสอบกลางคณะ — PHP MVC Starter

โครงสร้างเริ่มต้นสำหรับ PHP + MySQL บน XAMPP ใช้ Apache ที่มากับ XAMPP และ phpMyAdmin จัดการฐานข้อมูล ไม่ต้องติดตั้ง Nginx ในเครื่องพัฒนา

## เริ่มต้นบน Windows / XAMPP
1. แตกโฟลเดอร์ `code-exam-system` ไปไว้ที่ `C:\xampp\htdocs\`
2. คัดลอก `.env.example` เป็น `.env`
3. เปิด XAMPP Control Panel แล้ว Start Apache และ MySQL
4. เปิด `http://localhost/phpmyadmin` แล้วสร้างฐานข้อมูลหรือ Import SQL schema ที่ออกแบบไว้ ชื่อฐานข้อมูลต้องตรงกับ `DB_NAME` ใน `.env`
5. เปิด `http://localhost/code-exam-system/public/`

ถ้าเปลี่ยนชื่อโฟลเดอร์หรือพอร์ต ให้แก้ `APP_URL` / `DB_PORT` ให้ตรงกัน

## Git
เปิด Terminal ในโฟลเดอร์โปรเจกต์:
```bash
git init
git add .
git commit -m "chore: initialize PHP MVC project"
git branch -M main
git remote add origin <URL-ของ-repository>
git push -u origin main
```
สร้าง repository เปล่าบน GitHub/GitLab ก่อนใส่ URL จริง ห้าม commit `.env`, รหัสผ่าน, secret หรือข้อมูลผู้ใช้จริง

## โครงสร้าง
- `app/Controllers` รับ request และประสาน Model / View
- `app/Models` คำสั่ง SQL และการเข้าถึงข้อมูล
- `app/Views` ส่วนแสดงผล
- `config` การตั้งค่าระบบและฐานข้อมูล
- `core` คลาสกลาง เช่น Router, Controller, Database, Security
- `routes` กำหนด URL
- `public` จุดเข้าเว็บและไฟล์ static
- `logs` บันทึกข้อผิดพลาด (ไม่ commit log)

## ลำดับงานถัดไป
1. Import schema และทดสอบ PDO connection
2. ยืนยันชื่อ table/column จากฐานข้อมูลจริง
3. ทำ Login/Session และ RBAC
4. ทำรายวิชาและคลังข้อสอบ
5. ทำการสร้าง/เผยแพร่ข้อสอบ การทำข้อสอบ และตรวจคะแนน

โครงนี้ยังไม่สร้างตารางและยังไม่เขียน SQL ที่อิงชื่อคอลัมน์จริง เพื่อไม่ให้คลาดเคลื่อนจาก schema ล่าสุด
