<?php
/**
 * ============================================================
 *  menu.php — โครงเมนูทั้งระบบแยกตามบทบาท (navbar.php และ dashboard.php ใช้ร่วมกัน)
 * ------------------------------------------------------------
 *  ชื่อและลำดับเมนูตาม prototype: Main.html (อาจารย์) · StudentHomeDesk.html (นิสิต) · AdminDashboard.html (แอดมิน/วิชาการ)
 *
 *  แต่ละรายการเมนู
 *    'href'  => '/path'  มี route แล้ว → เป็นลิงก์ แสดงเมื่อ 'can' เป็น true
 *    'href'  => null     ยังไม่มี route → แสดงป้าย "เร็วๆ นี้" กดไม่ได้ (ไม่พาไปหน้า 404)
 *    'can'               เงื่อนไขสิทธิ์ (ใช้กับรายการที่มี href เท่านั้น)
 *    'match'             path อื่นที่ให้เมนูนี้ไฮไลต์ · ลงท้าย '/*' = ทุก path ย่อย เช่น '/questions/*'
 *
 *  เพิ่ม route ใหม่แล้ว → เปลี่ยน $soon('ชื่อ', 'icon') เป็น $live('ชื่อ', 'icon', '/path', สิทธิ์, 'คำอธิบาย')
 *  หมายเหตุ: ซ่อนเมนูเป็นแค่ความสะดวก การป้องกันจริงอยู่ที่ Security::require* ใน Controller
 * ============================================================
 */

if (!function_exists('app_menu')) {
    /** @return array<string, list<array{label:string, icon:string, href:?string, desc:string, match:list<string>}>> กลุ่มเมนู => รายการ */
    function app_menu(?array $user): array
    {
        if ($user === null) {
            return [];
        }

        // ---------- สิทธิ์ของผู้ใช้ — เงื่อนไขเดียวกับที่ Controller ตรวจ ----------
        $canExam    = Security::hasAnyRole(['instructor', 'admin']);   // ExamController
        $canProgram = Security::hasPermission('program.manage');       // ProgramController
        $canCourse  = Security::hasPermission('course.view');          // CourseController@index

        // ---------- ตัวช่วยสร้างรายการ: $live = มี route แล้ว · $soon = ยังไม่มี route (แสดง "เร็วๆ นี้") ----------
        $live = fn (string $label, string $icon, string $href, bool $can, string $desc, array $match = []) =>
            ['label' => $label, 'icon' => $icon, 'href' => $href, 'can' => $can, 'desc' => $desc, 'match' => array_merge([$href], $match)];
        $soon = fn (string $label, string $icon) =>
            ['label' => $label, 'icon' => $icon, 'href' => null, 'can' => true, 'desc' => '', 'match' => []];

        // ---------- เมนูของแต่ละบทบาท (ชื่อและลำดับตาม prototype) ----------
        $menus = [
            // อาจารย์ 5 เมนู (คุมสอบ ตรวจข้อเขียน สอบกระดาษ เป็นหน้าย่อยในชุดสอบ/ผลสอบ)
            'instructor' => [
                '' => [
                    $live('จัดการรายวิชา', 'book', '/my-courses', $canExam, 'รายวิชาที่ท่านสอน กลุ่มเรียน และจำนวนนิสิต', ['/my-courses/*']),
                    $live('คลังข้อสอบ', 'bank', '/questions', Security::hasPermission('question.manage'), 'จัดเก็บ ค้นหา และจัดการข้อสอบ', ['/questions/*']),
                    $live('จัดการชุดสอบ', 'clip', '/exams', $canExam, 'สร้าง กำหนดเวลา และติดตามการสอบ', ['/exams/*']),
                    $soon('ผลสอบและรายงาน', 'chart'),
                ],
            ],
            // นิสิต 6 เมนู (ยื่นคำร้องสอบซ่อมจากหน้าการสอบของฉัน)
            'student' => [
                '' => [
                    $soon('รายวิชาของฉัน', 'book'),
                    $soon('การสอบของฉัน', 'play'),
                    $soon('ผลการสอบ', 'chart'),
                    $soon('ข่าวประชาสัมพันธ์', 'news'),
                    $soon('โปรไฟล์', 'user'),
                ],
            ],
            // ฝ่ายวิชาการ — admin เห็นกลุ่มนี้ด้วย (ตาม prototype "แอดมิน / วิชาการ")
            'academic_staff' => [
                'งานวิชาการ' => [
                    $live('หลักสูตรและรายวิชา', 'school', '/programs', $canProgram, 'หลักสูตร กลุ่มวิชา และรายวิชา', ['/course-groups', '/courses']),
                    $soon('จัดการนิสิต', 'users'),
                    $soon('ติดตามผลการสอบ', 'chart'),
                    $soon('ประชาสัมพันธ์', 'news'),
                ],
            ],
            // เฉพาะ admin (academic_staff ไม่มีสิทธิ์ user.manage / system.settings)
            'admin' => [
                'ระบบ' => [
                    $soon('ผู้ใช้และสิทธิ์', 'shield'),   // users, user_roles, roles, permissions
                    $soon('บันทึกการใช้งาน', 'log'),      // audit_logs
                    $soon('ตั้งค่าระบบ', 'gear'),
                ],
            ],
        ];

        // ---------- admin เห็นเมนูฝ่ายวิชาการด้วย ----------
        $roles = (array)($user['roles'] ?? []);
        if (in_array('admin', $roles, true) && !in_array('academic_staff', $roles, true)) {
            $roles[] = 'academic_staff';
        }

        // หน้าหลัก: ฝั่งแอดมิน/วิชาการเรียก "Dashboard" ตาม prototype
        // ---------- ชื่อหน้าหลัก: ฝั่งแอดมิน/วิชาการเรียก "Dashboard" ----------
        $isStaffOnly = !array_intersect($roles, ['instructor', 'student']);
        $homeLabel   = $isStaffOnly ? 'Dashboard' : 'หน้าหลัก';
        $home        = ['label' => $homeLabel, 'icon' => 'home', 'href' => '/', 'can' => true, 'desc' => '', 'match' => ['/']];

        // รวมเมนูของทุกบทบาทที่มี: กลุ่มชื่อเดียวกันรวมกัน, รายการชื่อซ้ำแสดงครั้งเดียว
        $groups = [];
        $seen   = [];
        foreach (['instructor', 'student', 'academic_staff', 'admin'] as $role) {
            if (!in_array($role, $roles, true)) {
                continue;
            }
            foreach ($menus[$role] as $groupName => $items) {
                foreach ($items as $item) {
                    if ($item['href'] !== null && !$item['can']) {
                        continue;   // มี route แต่ไม่มีสิทธิ์ → ไม่แสดง
                    }
                    if (isset($seen[$item['label']])) {
                        continue;
                    }
                    $seen[$item['label']] = true;
                    unset($item['can']);
                    $groups[$groupName][] = $item;
                }
            }
        }

        // หน้าหลักอยู่บนสุดของกลุ่มแรก (ฝั่งวิชาการอยู่ใต้หัวข้อ "งานวิชาการ" ตาม prototype)
        if ($groups === []) {
            $groups[''] = [];
        }
        $firstKey = array_key_first($groups);
        array_unshift($groups[$firstKey], $home);

        return $groups;
    }
}

if (!function_exists('menu_active_item')) {
    /** รายการเมนูที่ตรงกับ path ปัจจุบัน (ตัวแรกที่เจอ) หรือ null */
    function menu_active_item(array $groups, string $path): ?array
    {
        foreach ($groups as $items) {
            foreach ($items as $item) {
                if (in_array($path, $item['match'], true) || array_filter($item['match'], fn ($m) => str_ends_with($m, '/*') && str_starts_with($path, substr($m, 0, -1)))) {
                    return $item;
                }
            }
        }
        return null;
    }
}
