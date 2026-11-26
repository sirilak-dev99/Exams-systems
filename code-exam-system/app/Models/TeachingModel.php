<?php

declare(strict_types=1);

/**
 * ============================================================
 *  TeachingModel — ข้อมูลฝั่งอาจารย์ (อ่านอย่างเดียว) · ใช้โดย TeachingController
 * ------------------------------------------------------------
 *  getTeachingOfferings()   รายวิชาที่ผู้ใช้สอน (จาก section_staff)
 *  findTeachingOffering()   รายวิชาที่เปิดสอน 1 รายการ + บทบาทของผู้ใช้ในวิชา (null = ไม่ได้สอน)
 *  getOfferingDetail()      ข้อมูลทุกแท็บของหน้ารายละเอียดรายวิชา
 *  getExamList()            รายการชุดสอบ + ตัวกรอง · getExamStatusCounts() · getExamFilterOptions()
 *
 *  ⚠️ ทุกคำสั่งใช้ชื่อพารามิเตอร์ไม่ซ้ำกันในคำสั่งเดียว (เช่น :user_id กับ :staff_user_id)
 *     เพราะ Database ปิด emulate prepares — ใช้ชื่อซ้ำจะได้ SQLSTATE[HY093]
 * ============================================================
 */
final class TeachingModel
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getConnection();
    }

    private function all(string $sql, array $params): array
    {
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // ==================== รายวิชาที่สอน (GET /my-courses) ====================

    public function getTeachingOfferings(int $userId): array
    {
        return $this->all("
            SELECT co.id_course_offerings, c.id_courses AS courses_id, c.code, c.name,
                   p.id_programs AS programs_id, p.name AS program_name,
                   t.academic_year, t.term_no, co.status,
                   GROUP_CONCAT(DISTINCT s.section_no ORDER BY s.section_no SEPARATOR ', ') AS section_nos,
                   (SELECT COUNT(*) FROM course_members cm
                     WHERE cm.course_offerings_id = co.id_course_offerings
                       AND cm.section_id IN (SELECT ss2.section_id FROM section_staff ss2 WHERE ss2.user_id = :staff_user_id)
                       AND cm.enrollment_status <> 'dropped') AS student_count,
                   CASE WHEN SUM(ss.staff_role = 'coordinator') > 0 THEN 'coordinator'
                        WHEN SUM(ss.staff_role = 'teacher') > 0 THEN 'teacher'
                        ELSE 'assistant' END AS my_role
            FROM section_staff ss
            JOIN sections s          ON s.id_sections = ss.section_id
            JOIN course_offerings co ON co.id_course_offerings = s.course_offerings_id
            JOIN courses c           ON c.id_courses = co.courses_id AND c.deleted_at IS NULL
            JOIN program_versions pv ON pv.id_program_versions = co.program_versions_id
            JOIN programs p          ON p.id_programs = pv.programs_id
            JOIN academic_terms t    ON t.id_academic_terms = co.academic_terms_id
            WHERE ss.user_id = :user_id
            GROUP BY co.id_course_offerings, c.id_courses, c.code, c.name, p.id_programs, p.name, t.academic_year, t.term_no, co.status
            ORDER BY t.academic_year DESC, t.term_no DESC, c.code
        ", [':user_id' => $userId, ':staff_user_id' => $userId]);
    }

    // ==================== รายละเอียดรายวิชา (GET /my-courses/{id}) ====================

    /** null = ไม่พบ หรือผู้ใช้ไม่ได้สอนรายวิชานี้ ($isAdmin = true ดูได้ทุกรายวิชา) */
    public function findTeachingOffering(int $offeringId, int $userId, bool $isAdmin): ?array
    {
        $rows = $this->all("
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
            LEFT JOIN sections s ON s.course_offerings_id = co.id_course_offerings
            LEFT JOIN section_staff ss ON ss.section_id = s.id_sections AND ss.user_id = :user_id
            WHERE co.id_course_offerings = :offering_id
            GROUP BY co.id_course_offerings
        ", [':user_id' => $userId, ':offering_id' => $offeringId]);

        $row = $rows[0] ?? null;
        if ($row === null || ($row['my_role'] === null && !$isAdmin)) {
            return null;
        }
        return $row;
    }

    /** ข้อมูลทุกแท็บของหน้ารายละเอียดรายวิชา */
    public function getOfferingDetail(int $offeringId, int $courseId): array
    {
        $o = $offeringId;
        $c = $courseId;

        $stats = $this->all("
            SELECT
              (SELECT COUNT(*) FROM course_members cm WHERE cm.course_offerings_id = :o1 AND cm.enrollment_status <> 'dropped') AS students,
              (SELECT COUNT(*) FROM sections s WHERE s.course_offerings_id = :o2) AS sections,
              (SELECT COUNT(*) FROM chapters ch WHERE ch.courses_id = :c1) AS chapters,
              (SELECT COUNT(*) FROM main_concepts mc WHERE mc.courses_id = :c2) AS concepts_main,
              (SELECT COUNT(*) FROM sub_concepts sc WHERE sc.courses_id = :c3) AS concepts_sub,
              (SELECT COUNT(*) FROM questions q WHERE q.courses_id = :c4 AND q.deleted_at IS NULL) AS questions
        ", [':o1' => $o, ':o2' => $o, ':c1' => $c, ':c2' => $c, ':c3' => $c, ':c4' => $c])[0] ?? [];

        $staff = $this->all("
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
        ", [':offering_id' => $o]);

        $sections = $this->all("
            SELECT s.section_no,
                   (SELECT GROUP_CONCAT(u.display_name ORDER BY u.display_name SEPARATOR ', ')
                      FROM section_staff ss JOIN users u ON u.id_users = ss.user_id WHERE ss.section_id = s.id_sections) AS staff_names,
                   (SELECT COUNT(*) FROM course_members cm WHERE cm.section_id = s.id_sections AND cm.enrollment_status <> 'dropped') AS enrolled,
                   (SELECT COUNT(*) FROM course_members cm WHERE cm.section_id = s.id_sections AND cm.enrollment_status = 'dropped') AS dropped
            FROM sections s WHERE s.course_offerings_id = :offering_id ORDER BY s.section_no
        ", [':offering_id' => $o]);

        $students = $this->all("
            SELECT u.student_code, u.display_name, u.email, s.section_no, cm.enrollment_status
            FROM course_members cm
            JOIN users u ON u.id_users = cm.user_id
            JOIN sections s ON s.id_sections = cm.section_id
            WHERE cm.course_offerings_id = :offering_id
            ORDER BY s.section_no, u.student_code
        ", [':offering_id' => $o]);

        $chapters = $this->all("
            SELECT ch.chapters_no, ch.name,
                   (SELECT COUNT(*) FROM questions q WHERE q.chapters_id = ch.id_chapters AND q.deleted_at IS NULL) AS question_count
            FROM chapters ch WHERE ch.courses_id = :course_id ORDER BY ch.chapters_no
        ", [':course_id' => $c]);

        $subs = [];
        foreach ($this->all("
            SELECT sc.main_concepts_id, sc.code, sc.name,
                   (SELECT COUNT(DISTINCT qs.questions_id) FROM question_version_sub_concepts qs WHERE qs.sub_concepts_id = sc.id_sub_concepts) AS question_count
            FROM sub_concepts sc WHERE sc.courses_id = :course_id ORDER BY sc.code
        ", [':course_id' => $c]) as $s) {
            $subs[$s['main_concepts_id']][] = $s;
        }
        $concepts = array_map(fn ($m) => $m + ['subs' => $subs[$m['id_main_concepts']] ?? []], $this->all("
            SELECT mc.id_main_concepts, mc.code, mc.name,
                   (SELECT COUNT(DISTINCT qm.questions_id) FROM question_version_main_concepts qm WHERE qm.main_concepts_id = mc.id_main_concepts) AS question_count
            FROM main_concepts mc WHERE mc.courses_id = :course_id ORDER BY mc.code
        ", [':course_id' => $c]));

        $learningOutcomes = $this->all("
            SELECT lo.code, lo.description,
                   (SELECT COUNT(DISTINCT ql.questions_id) FROM question_learning_outcomes ql WHERE ql.learning_outcomes_id = lo.id_learning_outcomes) AS question_count
            FROM learning_outcomes lo WHERE lo.courses_id = :course_id ORDER BY lo.code
        ", [':course_id' => $c]);

        $contents = $this->all("
            SELECT lc.title, lc.content_type, lc.status, lc.description, lc.updated_at,
                   (SELECT COUNT(*) FROM learning_content_attachments a WHERE a.learning_content_id = lc.id_learning_contents) AS attachment_count
            FROM learning_contents lc WHERE lc.courses_id = :course_id ORDER BY lc.updated_at DESC
        ", [':course_id' => $c]);

        $questions = $this->all("
            SELECT q.id_questions, q.text, ch.chapters_no, ch.name AS chapter_name, b.name_th AS bloom_name_th, q.status
            FROM questions q
            LEFT JOIN chapters ch ON ch.id_chapters = q.chapters_id
            JOIN bloom_levels b ON b.code = q.bloom_levels_code
            WHERE q.courses_id = :course_id AND q.deleted_at IS NULL
            ORDER BY ch.chapters_no, q.id_questions
        ", [':course_id' => $c]);

        $exams = $this->all("
            SELECT e.id_exams, e.title, e.kind, e.open_at, e.close_at, e.status,
                   (SELECT COUNT(*) FROM exam_eligibilities el WHERE el.exams_id = e.id_exams AND el.status = 'eligible') AS eligible_count,
                   (SELECT COUNT(DISTINCT a.users_id) FROM attempts a WHERE a.exams_id = e.id_exams AND a.submitted_at IS NOT NULL) AS submitted_count,
                   (SELECT ROUND(AVG(a.score), 1) FROM attempts a WHERE a.exams_id = e.id_exams AND a.submitted_at IS NOT NULL) AS avg_score
            FROM exams e WHERE e.course_offerings_id = :offering_id AND e.deleted_at IS NULL
            ORDER BY e.open_at DESC
        ", [':offering_id' => $o]);

        return compact('stats', 'staff', 'sections', 'students', 'chapters', 'concepts', 'learningOutcomes', 'contents', 'questions', 'exams');
    }

    // ==================== รายการชุดสอบ (GET /exams) ====================

    /** ชุดสอบที่ผู้ใช้สร้าง พร้อมรายวิชา ภาค และกลุ่มเรียนที่สอบ (admin เห็นทั้งหมด) */
    public function getExamList(int $userId, bool $isAdmin, array $filters): array
    {
        $sql = "
            SELECT e.id_exams, e.title, e.kind, e.mode, e.status, e.open_at, e.close_at, e.duration_min,
                   c.code AS course_code, c.name AS course_name,
                   CONCAT(t.term_no, '/', t.academic_year) AS term_label,
                   (SELECT GROUP_CONCAT(s.section_no ORDER BY s.section_no SEPARATOR ', ')
                      FROM exam_sections es JOIN sections s ON s.id_sections = es.sections_id
                     WHERE es.exams_id = e.id_exams) AS section_nos
            FROM exams e
            JOIN course_offerings co ON co.id_course_offerings = e.course_offerings_id
            JOIN courses c ON c.id_courses = e.courses_id
            JOIN academic_terms t ON t.id_academic_terms = co.academic_terms_id
            WHERE e.deleted_at IS NULL
        ";
        $params = [];
        if (!$isAdmin) {
            $sql .= " AND e.created_by = :user_id";
            $params[':user_id'] = $userId;
        }
        if (!empty($filters['courses_id']) && ctype_digit((string)$filters['courses_id'])) {
            $sql .= " AND e.courses_id = :courses_id";
            $params[':courses_id'] = (int)$filters['courses_id'];
        }
        if (!empty($filters['academic_terms_id']) && ctype_digit((string)$filters['academic_terms_id'])) {
            $sql .= " AND co.academic_terms_id = :term_id";
            $params[':term_id'] = (int)$filters['academic_terms_id'];
        }
        if (!empty($filters['kind']) && in_array($filters['kind'], ['midterm', 'final', 'quiz', 'pre_post'], true)) {
            $sql .= " AND e.kind = :kind";
            $params[':kind'] = $filters['kind'];
        }
        if (!empty($filters['status']) && in_array($filters['status'], ['draft', 'published', 'closed', 'archived'], true)) {
            $sql .= " AND e.status = :status";
            $params[':status'] = $filters['status'];
        }
        if (!empty($filters['q'])) {
            $sql .= " AND e.title LIKE :q";
            $params[':q'] = '%' . trim((string)$filters['q']) . '%';
        }
        $sql .= " ORDER BY e.open_at DESC, e.id_exams DESC";

        return $this->all($sql, $params);
    }

    /** นับตามสถานะ (ก่อนกรอง) + จำนวนทั้งหมด */
    public function getExamStatusCounts(int $userId, bool $isAdmin): array
    {
        $sql = "SELECT status, COUNT(*) n FROM exams WHERE deleted_at IS NULL" . ($isAdmin ? '' : ' AND created_by = :user_id') . " GROUP BY status";
        $counts = ['all' => 0, 'draft' => 0, 'published' => 0, 'closed' => 0, 'archived' => 0];
        foreach ($this->all($sql, $isAdmin ? [] : [':user_id' => $userId]) as $r) {
            $counts[$r['status']] = (int)$r['n'];
            $counts['all'] += (int)$r['n'];
        }
        return $counts;
    }

    /** รายวิชาและภาคที่มีชุดสอบของผู้ใช้ — ใช้ทำตัวเลือกตัวกรอง */
    public function getExamFilterOptions(int $userId, bool $isAdmin): array
    {
        $where = 'e.deleted_at IS NULL' . ($isAdmin ? '' : ' AND e.created_by = :user_id');
        $p = $isAdmin ? [] : [':user_id' => $userId];
        $courses = $this->all("SELECT DISTINCT c.id_courses, c.code, c.name FROM exams e JOIN courses c ON c.id_courses = e.courses_id WHERE $where ORDER BY c.code", $p);
        $terms = $this->all("
            SELECT DISTINCT t.id_academic_terms, t.academic_year, t.term_no
            FROM exams e JOIN course_offerings co ON co.id_course_offerings = e.course_offerings_id
            JOIN academic_terms t ON t.id_academic_terms = co.academic_terms_id
            WHERE $where ORDER BY t.academic_year DESC, t.term_no DESC", $p);
        return [$courses, $terms];
    }
}
