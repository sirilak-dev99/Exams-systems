# จัดการรายวิชา (อาจารย์) — view ที่ทำไว้แล้ว และสิ่งที่ backend ต้องเพิ่ม

หน้าจอทำตาม prototype `Course.html` อยู่ที่ `code-exam-system/app/Views/courses/teaching.php`
ใน prototype หน้านี้คือ **รายวิชาที่อาจารย์สอน** (รายวิชา × ภาค × กลุ่มเรียน + จำนวนนิสิต) ไม่ใช่รายวิชาทั้งหมดในระบบ
จึงใช้ `GET /courses` ที่มีอยู่ไม่ได้ (`CourseController@index` ส่งแค่ตาราง `courses`) ต้องมี route ใหม่

## 1. Route ที่ต้องเพิ่มใน `routes/web.php`

```php
$router->get('/my-courses', [CourseController::class, 'teaching']);
```

สิทธิ์: `Security::requireRole(['instructor', 'admin'])`

## 2. Controller

```php
// GET /my-courses
public function teaching(): void
{
    Security::requireRole(['instructor', 'admin']);
    $userId = (int)(Security::currentUser()['id_users'] ?? 0);

    $this->view('courses/teaching', [
        'pageTitle' => 'จัดการรายวิชา',
        'offerings' => $this->courseModel->getTeachingOfferings($userId),
    ]);
}
```

ส่งรายการทั้งหมดของอาจารย์มาได้เลย หน้ากรองเอง (ค้นหา, หลักสูตร, ปีการศึกษา, ภาค, กลุ่มเรียน)

## 3. SQL สำหรับ `CourseModel::getTeachingOfferings(int $userId)`

ทดสอบกับข้อมูลตัวอย่างในฐานข้อมูลแล้ว (อ.ทดสอบได้ 3 รายวิชา: 100113 กลุ่ม 01, 02 นิสิต 6 คน · 100121 · 100105)
ใช้ชื่อพารามิเตอร์ 2 ชื่อ (`:user_id`, `:staff_user_id`) เพราะ PDO ของโปรเจกต์ปิด emulate prepares — ใช้ชื่อซ้ำจะได้ `SQLSTATE[HY093]`

```sql
SELECT
    co.id_course_offerings,
    c.id_courses AS courses_id,
    c.code,
    c.name,
    p.id_programs AS programs_id,
    p.name AS program_name,
    t.academic_year,
    t.term_no,
    co.status,
    GROUP_CONCAT(DISTINCT s.section_no ORDER BY s.section_no SEPARATOR ', ') AS section_nos,
    (SELECT COUNT(*) FROM course_members cm
      WHERE cm.course_offerings_id = co.id_course_offerings
        AND cm.section_id IN (SELECT ss2.section_id FROM section_staff ss2 WHERE ss2.user_id = :staff_user_id)
        AND cm.enrollment_status <> 'dropped') AS student_count,
    CASE
        WHEN SUM(ss.staff_role = 'coordinator') > 0 THEN 'coordinator'
        WHEN SUM(ss.staff_role = 'teacher') > 0 THEN 'teacher'
        ELSE 'assistant'
    END AS my_role
FROM section_staff ss
JOIN sections s           ON s.id_sections = ss.section_id
JOIN course_offerings co  ON co.id_course_offerings = s.course_offerings_id
JOIN courses c            ON c.id_courses = co.courses_id AND c.deleted_at IS NULL
JOIN program_versions pv  ON pv.id_program_versions = co.program_versions_id
JOIN programs p           ON p.id_programs = pv.programs_id
JOIN academic_terms t     ON t.id_academic_terms = co.academic_terms_id
WHERE ss.user_id = :user_id
GROUP BY co.id_course_offerings, c.id_courses, c.code, c.name, p.id_programs, p.name, t.academic_year, t.term_no, co.status
ORDER BY t.academic_year DESC, t.term_no DESC, c.code
```

```php
$stmt = $this->db->prepare($sql);
$stmt->execute([':user_id' => $userId, ':staff_user_id' => $userId]);
return $stmt->fetchAll();
```

| คอลัมน์ที่ view ใช้ | ที่มา |
|---|---|
| `id_course_offerings, courses_id, code, name` | `course_offerings` + `courses` |
| `programs_id, program_name` | `program_versions` → `programs` |
| `academic_year, term_no` | `academic_terms` |
| `section_nos` | กลุ่มเรียนที่อาจารย์สอน เช่น `01, 02` |
| `student_count` | นิสิตใน `course_members` ของกลุ่มที่อาจารย์สอน (ไม่นับ `dropped`) |
| `status` | `course_offerings.status`: `open` = เปิดสอน · `completed` = สิ้นสุดภาค · `closed` = ปิดแล้ว |
| `my_role` | บทบาทสูงสุดใน `section_staff`: `coordinator` เห็นปุ่มแก้ไขรายวิชา (ตาม spec "ผู้ประสานงานรายวิชาแก้ไขข้อมูลวิชาได้") |

## 4. ลิงก์ในหน้าที่ยังไม่มี route (แสดงเป็น "เร็วๆ นี้")

| ปุ่ม | prototype | route ที่เสนอ |
|---|---|---|
| ดูรายละเอียด | `CourseView.html` | `GET /my-courses/{offeringId}` |
| รายชื่อนิสิต | `CourseViewStudents.html` | `GET /my-courses/{offeringId}/students` |
| แก้ไขรายวิชา (ผู้ประสานงาน) | `CourseDetail.html` | `GET /my-courses/{offeringId}/edit` |
| ⋯ ข้อสอบ / ชุดสอบ / เนื้อหาการเรียน / ส่งออกรายชื่อ | `CourseViewBank`, `CourseViewExams`, `CourseViewContent`, ส่งออก Excel/PDF | ยังไม่กำหนด |

เมื่อหน้ารายละเอียดพร้อม ตั้ง `'detailRoutes' => true` ตอนเรียก view ปุ่มจะกลายเป็นลิงก์

## 5. ฝั่ง frontend เมื่อ route พร้อม

ใน `app/Views/includes/menu.php` เปลี่ยนเมนู "จัดการรายวิชา" ของอาจารย์:

```php
$live('จัดการรายวิชา', 'book', '/my-courses', Security::hasAnyRole(['instructor', 'admin']), 'รายวิชาที่ท่านสอน กลุ่มเรียน และจำนวนนิสิต'),
```

---

# รายละเอียดรายวิชาที่สอน — `courses/show.php` (CourseView.html · คู่มือฝั่งอาจารย์ หน้า 06–17)

## Route

```php
$router->get("/my-courses/{id}", [CourseController::class, "teachingShow"]);   // id = id_course_offerings
```

แท็บใช้ GET `?tab=` (ov, staff, sec, stu, topic, lo, content, bank, ex) — ทุกแท็บอยู่ใน view เดียว Controller ส่งข้อมูลทุกส่วนมาครั้งเดียว
สิทธิ์: ต้องเป็นผู้สอนในรายวิชานี้ (`section_staff`) — SQL `offering` คืน 0 แถวถ้าไม่ได้สอน → ส่ง `offering = null` view แสดง "ไม่พบรายวิชา"

## ตัวแปรที่ view ต้องการ

| ตัวแปร | SQL ด้านล่าง | หมายเหตุ |
|---|---|---|
| `$offering` | `offering` (แถวแรก หรือ null) | |
| `$stats` | `stats` (แถวแรก) | |
| `$staff` | `staff` | |
| `$sections` | `sections` | |
| `$students` | `students` | |
| `$chapters` | `chapters` | |
| `$concepts` | `concepts_main` + `concepts_sub` → ใส่ `subs` ตาม `main_concepts_id` | |
| `$learningOutcomes` | `learning_outcomes` | |
| `$contents` | `contents` | |
| `$questions` | `questions` | |
| `$exams` | `exams` | |

## SQL (ทดสอบกับข้อมูลตัวอย่างแล้ว: 100113 นิสิต 6 คน 2 กลุ่ม 4 บท 6 ข้อสอบ 3 ชุดสอบ)

ทุกคำสั่งใช้ชื่อพารามิเตอร์ไม่ซ้ำกันในคำสั่งเดียว

### `offering`

```sql
SELECT co.id_course_offerings, c.id_courses AS courses_id, c.code, c.name, cg.name AS course_group_name,
       p.code AS program_code, p.name AS program_name, pv.version_no, pv.effective_from,
       pvc.year_level, pvc.semester_no, t.academic_year, t.term_no, co.status, co.pass_percent,
       CASE WHEN SUM(ss.staff_role = 'coordinator') > 0 THEN 'coordinator'
            WHEN SUM(ss.staff_role = 'teacher') > 0 THEN 'teacher'
            WHEN COUNT(ss.id_section_staff) > 0 THEN 'assistant' ELSE NULL END AS my_role
FROM course_offerings co
JOIN courses c ON c.id_courses = co.courses_id AND c.deleted_at IS NULL
JOIN course_groups cg ON cg.id_course_groups = c.course_groups_id
JOIN program_versions pv ON pv.id_program_versions = co.program_versions_id
JOIN programs p ON p.id_programs = pv.programs_id
JOIN program_version_courses pvc ON pvc.id_program_version_courses = co.program_version_courses_id
JOIN academic_terms t ON t.id_academic_terms = co.academic_terms_id
JOIN sections s ON s.course_offerings_id = co.id_course_offerings
LEFT JOIN section_staff ss ON ss.section_id = s.id_sections AND ss.user_id = :user_id
WHERE co.id_course_offerings = :offering_id
GROUP BY co.id_course_offerings
HAVING my_role IS NOT NULL
```

### `stats`

```sql
SELECT
  (SELECT COUNT(*) FROM course_members cm WHERE cm.course_offerings_id = :o1 AND cm.enrollment_status <> 'dropped') AS students,
  (SELECT COUNT(*) FROM sections s WHERE s.course_offerings_id = :o2) AS sections,
  (SELECT COUNT(*) FROM chapters ch WHERE ch.courses_id = :c1) AS chapters,
  (SELECT COUNT(*) FROM main_concepts mc WHERE mc.courses_id = :c2) AS concepts_main,
  (SELECT COUNT(*) FROM sub_concepts sc WHERE sc.courses_id = :c3) AS concepts_sub,
  (SELECT COUNT(*) FROM questions q WHERE q.courses_id = :c4 AND q.deleted_at IS NULL) AS questions
```

### `staff`

```sql
SELECT u.display_name, u.email,
       GROUP_CONCAT(s.section_no ORDER BY s.section_no SEPARATOR ', ') AS section_nos,
       CASE WHEN SUM(ss.staff_role = 'coordinator') > 0 THEN 'coordinator'
            WHEN SUM(ss.staff_role = 'teacher') > 0 THEN 'teacher' ELSE 'assistant' END AS staff_role
FROM section_staff ss
JOIN sections s ON s.id_sections = ss.section_id
JOIN users u ON u.id_users = ss.user_id
WHERE s.course_offerings_id = :offering_id
GROUP BY u.id_users, u.display_name, u.email
ORDER BY FIELD(staff_role, 'coordinator', 'teacher', 'assistant'), u.display_name
```

### `sections`

```sql
SELECT s.section_no,
       (SELECT GROUP_CONCAT(u.display_name ORDER BY u.display_name SEPARATOR ', ')
          FROM section_staff ss JOIN users u ON u.id_users = ss.user_id WHERE ss.section_id = s.id_sections) AS staff_names,
       (SELECT COUNT(*) FROM course_members cm WHERE cm.section_id = s.id_sections AND cm.enrollment_status <> 'dropped') AS enrolled,
       (SELECT COUNT(*) FROM course_members cm WHERE cm.section_id = s.id_sections AND cm.enrollment_status = 'dropped') AS dropped
FROM sections s WHERE s.course_offerings_id = :offering_id ORDER BY s.section_no
```

### `students`

```sql
SELECT u.student_code, u.display_name, u.email, s.section_no, cm.enrollment_status
FROM course_members cm
JOIN users u ON u.id_users = cm.user_id
JOIN sections s ON s.id_sections = cm.section_id
WHERE cm.course_offerings_id = :offering_id
ORDER BY s.section_no, u.student_code
```

### `chapters`

```sql
SELECT ch.chapters_no, ch.name,
       (SELECT COUNT(*) FROM questions q WHERE q.chapters_id = ch.id_chapters AND q.deleted_at IS NULL) AS question_count
FROM chapters ch WHERE ch.courses_id = :course_id ORDER BY ch.chapters_no
```

### `concepts_main`

```sql
SELECT mc.id_main_concepts, mc.code, mc.name,
       (SELECT COUNT(DISTINCT qm.questions_id) FROM question_version_main_concepts qm WHERE qm.main_concepts_id = mc.id_main_concepts) AS question_count
FROM main_concepts mc WHERE mc.courses_id = :course_id ORDER BY mc.code
```

### `concepts_sub`

```sql
SELECT sc.main_concepts_id, sc.code, sc.name,
       (SELECT COUNT(DISTINCT qs.questions_id) FROM question_version_sub_concepts qs WHERE qs.sub_concepts_id = sc.id_sub_concepts) AS question_count
FROM sub_concepts sc WHERE sc.courses_id = :course_id ORDER BY sc.code
```

### `learning_outcomes`

```sql
SELECT lo.code, lo.description,
       (SELECT COUNT(DISTINCT ql.questions_id) FROM question_learning_outcomes ql WHERE ql.learning_outcomes_id = lo.id_learning_outcomes) AS question_count
FROM learning_outcomes lo WHERE lo.courses_id = :course_id ORDER BY lo.code
```

### `contents`

```sql
SELECT lc.title, lc.content_type, lc.status, lc.description, lc.updated_at,
       (SELECT COUNT(*) FROM learning_content_attachments a WHERE a.learning_content_id = lc.id_learning_contents) AS attachment_count
FROM learning_contents lc WHERE lc.courses_id = :course_id ORDER BY lc.updated_at DESC
```

### `questions`

```sql
SELECT q.id_questions, q.text, ch.chapters_no, ch.name AS chapter_name, b.name_th AS bloom_name_th, q.status
FROM questions q
LEFT JOIN chapters ch ON ch.id_chapters = q.chapters_id
JOIN bloom_levels b ON b.code = q.bloom_levels_code
WHERE q.courses_id = :course_id AND q.deleted_at IS NULL
ORDER BY ch.chapters_no, q.id_questions
```

### `exams`

```sql
SELECT e.id_exams, e.title, e.kind, e.open_at, e.close_at, e.status,
       (SELECT COUNT(*) FROM exam_eligibilities el WHERE el.exams_id = e.id_exams AND el.status = 'eligible') AS eligible_count,
       (SELECT COUNT(DISTINCT a.users_id) FROM attempts a WHERE a.exams_id = e.id_exams AND a.submitted_at IS NOT NULL) AS submitted_count,
       (SELECT ROUND(AVG(a.score), 1) FROM attempts a WHERE a.exams_id = e.id_exams AND a.submitted_at IS NOT NULL) AS avg_score
FROM exams e WHERE e.course_offerings_id = :offering_id AND e.deleted_at IS NULL
ORDER BY e.open_at DESC
```

## ปุ่มที่ยังรอ route ฝั่งบันทึก (แสดงเป็น "เร็วๆ นี้")

แก้ไขรายวิชา (`CourseDetail.html`) · เพิ่มผู้สอน · เพิ่มบท/แนวคิด · เพิ่ม CLO · เพิ่มเนื้อหา (`CourseViewContentAdd.html`) · เพิ่มข้อสอบในวิชานี้ · ส่งออกรายชื่อ

## เรื่องที่ต้องตัดสินใจ

prototype เรียก "หัวข้อหลัก = บท · หัวข้อรองอยู่ใต้บท" แต่ในฐานข้อมูล `sub_concepts` อยู่ใต้ `main_concepts` ไม่ใช่ใต้ `chapters` — view จึงแสดง 2 กล่อง: บท และ แนวคิดหลัก/รอง ถ้าต้องการให้หัวข้อรองอยู่ใต้บทจริง ต้องเพิ่มคอลัมน์ `chapters_id` ใน `main_concepts` หรือปรับโครงสร้าง
