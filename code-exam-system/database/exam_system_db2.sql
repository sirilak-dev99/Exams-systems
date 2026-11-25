-- MySQL Workbench Forward Engineering v15

SET @OLD_UNIQUE_CHECKS = @@UNIQUE_CHECKS, UNIQUE_CHECKS = 0;

SET
    @OLD_FOREIGN_KEY_CHECKS = @@FOREIGN_KEY_CHECKS,
    FOREIGN_KEY_CHECKS = 0;

SET
    @OLD_SQL_MODE = @@SQL_MODE,
    SQL_MODE = 'ONLY_FULL_GROUP_BY,STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION';

-- -----------------------------------------------------
-- Schema exam_system
-- -----------------------------------------------------
-- DROP SCHEMA IF EXISTS exam_system;
CREATE SCHEMA IF NOT EXISTS `exam_system` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;

USE `exam_system`;

-- -----------------------------------------------------
-- Table `users` (ผู้ใช้ระบบ)
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `users` (
    `id_users` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `email` VARCHAR(254) NOT NULL,
    `student_code` VARCHAR(8) NULL,
    `display_name` VARCHAR(100) NOT NULL,
    `active` TINYINT(1) NOT NULL DEFAULT 1,
    `password_hash` VARCHAR(255) NULL,
    `google_id` VARCHAR(255) NULL,
    `created_at` DATETIME NOT NULL,
    `updated_at` DATETIME NOT NULL,
    `deleted_at` DATETIME NULL,
    PRIMARY KEY (`id_users`),
    UNIQUE INDEX `student_code_UNIQUE` (`student_code` ASC),
    UNIQUE INDEX `email_UNIQUE` (`email` ASC),
    UNIQUE INDEX `google_id_UNIQUE` (`google_id` ASC),
    CONSTRAINT `chk_users_active` CHECK (`active` IN (0, 1)),
    CONSTRAINT `chk_users_student_code` CHECK (
        `student_code` IS NULL
        OR `student_code` REGEXP '^[0-9]{8}$'
    )
) ENGINE = InnoDB DEFAULT CHARACTER SET = utf8mb4 COLLATE = utf8mb4_general_ci;

-- -----------------------------------------------------
-- Table `roles` & `user_roles` (บทบาทและผู้ใช้)
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `roles` (
    `id_roles` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name` VARCHAR(45) NOT NULL,
    PRIMARY KEY (`id_roles`),
    UNIQUE INDEX `name_UNIQUE` (`name` ASC)
) ENGINE = InnoDB DEFAULT CHARACTER SET = utf8mb4 COLLATE = utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `user_roles` (
    `users_id` INT UNSIGNED NOT NULL,
    `roles_id` INT UNSIGNED NOT NULL,
    PRIMARY KEY (`users_id`, `roles_id`),
    INDEX `fk_user_roles_roles1_idx` (`roles_id` ASC),
    CONSTRAINT `fk_user_roles_users1` FOREIGN KEY (`users_id`) REFERENCES `users` (`id_users`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_user_roles_roles1` FOREIGN KEY (`roles_id`) REFERENCES `roles` (`id_roles`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE = InnoDB DEFAULT CHARACTER SET = utf8mb4 COLLATE = utf8mb4_general_ci;

-- -----------------------------------------------------
-- Table `password_reset_tokens` (โทเค็นสำหรับรีเซ็ตรหัสผ่าน)
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `password_reset_tokens` (
    `id_password_reset_tokens` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `users_id` INT UNSIGNED NOT NULL,
    `token_hash` VARCHAR(255) NOT NULL,
    `expires_at` DATETIME NOT NULL,
    `used_at` DATETIME NULL DEFAULT NULL,
    `request_ip` VARCHAR(45) NULL DEFAULT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id_password_reset_tokens`),
    UNIQUE INDEX `uq_prt_token_hash` (`token_hash` ASC),
    INDEX `idx_prt_user_expires` (`users_id` ASC, `expires_at` ASC),
    CONSTRAINT `fk_prt_user` FOREIGN KEY (`users_id`) REFERENCES `users` (`id_users`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `chk_prt_expires` CHECK (`expires_at` > `created_at`)
) ENGINE = InnoDB DEFAULT CHARACTER SET = utf8mb4 COLLATE = utf8mb4_general_ci;

-- -----------------------------------------------------
-- Table `programs` (หลักสูตร)
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `programs` (
    `id_programs` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `code` VARCHAR(20) NOT NULL,
    `name` VARCHAR(150) NOT NULL,
    `status` VARCHAR(20) NOT NULL DEFAULT 'active',
    PRIMARY KEY (`id_programs`),
    UNIQUE INDEX `code_UNIQUE` (`code` ASC),
    CONSTRAINT `chk_programs_status` CHECK (
        `status` IN (
            'active',
            'inactive',
            'archived'
        )
    )
) ENGINE = InnoDB DEFAULT CHARACTER SET = utf8mb4 COLLATE = utf8mb4_general_ci;

-- -----------------------------------------------------
-- `program_versions` (รุ่นหลักสูตร)
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `program_versions` (
    `id_program_versions` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `programs_id` INT UNSIGNED NOT NULL,
    `version_no` INT UNSIGNED NOT NULL,
    `effective_from` DATE NULL,
    `effective_to` DATE NULL,
    `status` VARCHAR(20) NOT NULL DEFAULT 'active',
    PRIMARY KEY (`id_program_versions`),
    UNIQUE INDEX `uq_program_version` (
        `programs_id` ASC,
        `version_no` ASC
    ),
    CONSTRAINT `fk_pv_programs` FOREIGN KEY (`programs_id`) REFERENCES `programs` (`id_programs`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `chk_pv_dates` CHECK (
        `effective_to` IS NULL
        OR `effective_from` IS NULL
        OR `effective_to` >= `effective_from`
    ),
    CONSTRAINT `chk_pv_status` CHECK (
        `status` IN (
            'active',
            'inactive',
            'archived'
        )
    )
) ENGINE = InnoDB DEFAULT CHARACTER SET = utf8mb4 COLLATE = utf8mb4_general_ci;

-- -----------------------------------------------------
-- Table `course_groups` (กลุ่มวิชา)
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `course_groups` (
    `id_course_groups` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name` VARCHAR(100) NOT NULL,
    `created_at` DATETIME NOT NULL,
    PRIMARY KEY (`id_course_groups`)
) ENGINE = InnoDB DEFAULT CHARACTER SET = utf8mb4 COLLATE = utf8mb4_general_ci;

-- -----------------------------------------------------
-- `courses` (วิชา)
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `courses` (
    `id_courses` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `course_groups_id` INT UNSIGNED NOT NULL,
    `created_by` INT UNSIGNED NOT NULL,
    `code` VARCHAR(20) NOT NULL,
    `name` VARCHAR(150) NOT NULL,
    `created_at` DATETIME NOT NULL,
    `updated_at` DATETIME NOT NULL,
    `deleted_at` DATETIME NULL,
    PRIMARY KEY (`id_courses`),
    UNIQUE INDEX `code_UNIQUE` (`code` ASC),
    INDEX `course_groups_idx` (`course_groups_id` ASC),
    INDEX `idx_courses_created_by` (`created_by` ASC),
    CONSTRAINT `fk_courses_course_groups` FOREIGN KEY (`course_groups_id`) REFERENCES `course_groups` (`id_course_groups`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_courses_users_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id_users`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE = InnoDB DEFAULT CHARACTER SET = utf8mb4 COLLATE = utf8mb4_general_ci;

-- -----------------------------------------------------
-- Table `program_version_courses` (วิชาที่อยู่ในหลักสูตร)
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `program_version_courses` (
    `id_program_version_courses` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `program_versions_id` INT UNSIGNED NOT NULL,
    `courses_id` INT UNSIGNED NOT NULL,
    `semester_no` INT NULL,
    `year_level` INT NULL,
    PRIMARY KEY (`id_program_version_courses`),
    UNIQUE INDEX `uq_pvc_course` (
        `program_versions_id` ASC,
        `courses_id` ASC
    ),
    UNIQUE INDEX `uq_pvc_id_course` (
        `id_program_version_courses` ASC,
        `courses_id` ASC
    ),
    UNIQUE INDEX `uq_pvc_id_pv` (
        `id_program_version_courses` ASC,
        `program_versions_id` ASC
    ),
    CONSTRAINT `fk_pvc_program_versions` FOREIGN KEY (`program_versions_id`) REFERENCES `program_versions` (`id_program_versions`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_pvc_courses` FOREIGN KEY (`courses_id`) REFERENCES `courses` (`id_courses`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `chk_pvc_semester` CHECK (
        `semester_no` IS NULL
        OR `semester_no` >= 1
    ),
    CONSTRAINT `chk_pvc_year` CHECK (
        `year_level` IS NULL
        OR `year_level` >= 1
    )
) ENGINE = InnoDB DEFAULT CHARACTER SET = utf8mb4 COLLATE = utf8mb4_general_ci;

-- -----------------------------------------------------
-- Table `academic_terms` (เทอมการศึกษา)
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `academic_terms` (
    `id_academic_terms` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `academic_year` INT NOT NULL,
    `term_no` INT NOT NULL,
    `start_date` DATE NULL,
    `end_date` DATE NULL,
    `status` VARCHAR(20) NOT NULL DEFAULT 'active',
    PRIMARY KEY (`id_academic_terms`),
    UNIQUE INDEX `uq_year_term` (
        `academic_year` ASC,
        `term_no` ASC
    ),
    CONSTRAINT `chk_term_dates` CHECK (
        `end_date` IS NULL
        OR `start_date` IS NULL
        OR `end_date` >= `start_date`
    ),
    CONSTRAINT `chk_at_year` CHECK (`academic_year` >= 2500),
    CONSTRAINT `chk_at_term` CHECK (`term_no` IN (1, 2, 3)),
    CONSTRAINT `chk_at_status` CHECK (
        `status` IN (
            'active',
            'inactive',
            'upcoming',
            'archived'
        )
    )
) ENGINE = InnoDB DEFAULT CHARACTER SET = utf8mb4 COLLATE = utf8mb4_general_ci;

-- -----------------------------------------------------
-- Table `course_offerings` (การเปิดสอนวิชา)
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `course_offerings` (
    `id_course_offerings` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `program_version_courses_id` INT UNSIGNED NOT NULL,
    `program_versions_id` INT UNSIGNED NOT NULL,
    `courses_id` INT UNSIGNED NOT NULL,
    `academic_terms_id` INT UNSIGNED NOT NULL,
    `pass_percent` DECIMAL(5, 2) NULL,
    `status` VARCHAR(20) NOT NULL DEFAULT 'open',
    PRIMARY KEY (`id_course_offerings`),
    UNIQUE INDEX `uq_offering` (
        `program_version_courses_id` ASC,
        `academic_terms_id` ASC
    ),
    UNIQUE INDEX `uq_co_id_course` (
        `id_course_offerings` ASC,
        `courses_id` ASC
    ),
    UNIQUE INDEX `uq_co_offering_pv` (
        `id_course_offerings` ASC,
        `program_version_courses_id` ASC
    ),
    UNIQUE INDEX `uq_co_id_pv` (
        `id_course_offerings` ASC,
        `program_versions_id` ASC
    ),
    INDEX `academic_terms_id_idx` (`academic_terms_id` ASC),
    CONSTRAINT `fk_co_pvc_course` FOREIGN KEY (`program_version_courses_id`, `courses_id`)
        REFERENCES `program_version_courses` (`id_program_version_courses`, `courses_id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_co_pvc_pv` FOREIGN KEY (`program_version_courses_id`, `program_versions_id`)
        REFERENCES `program_version_courses` (`id_program_version_courses`, `program_versions_id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_co_terms` FOREIGN KEY (`academic_terms_id`) REFERENCES `academic_terms` (`id_academic_terms`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `chk_pass_percent` CHECK (
        `pass_percent` IS NULL
        OR (
            `pass_percent` >= 0
            AND `pass_percent` <= 100
        )
    ),
    CONSTRAINT `chk_co_status` CHECK (
        `status` IN ('open', 'closed', 'completed')
    )
) ENGINE = InnoDB DEFAULT CHARACTER SET = utf8mb4 COLLATE = utf8mb4_general_ci;

-- -----------------------------------------------------
-- Table `sections` (กลุ่มเรียน)
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `sections` (
    `id_sections` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `course_offerings_id` INT UNSIGNED NOT NULL,
    `section_no` VARCHAR(5) NOT NULL,
    `created_at` DATETIME NOT NULL,
    PRIMARY KEY (`id_sections`),
    INDEX `course_offerings_id_idx` (`course_offerings_id` ASC),
    UNIQUE INDEX `uq_offering_section` (
        `course_offerings_id` ASC,
        `section_no` ASC
    ),
    UNIQUE INDEX `uq_sec_id_offering` (
        `id_sections` ASC,
        `course_offerings_id` ASC
    ),
    CONSTRAINT `fk_sections_offerings` FOREIGN KEY (`course_offerings_id`) REFERENCES `course_offerings` (`id_course_offerings`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE = InnoDB DEFAULT CHARACTER SET = utf8mb4 COLLATE = utf8mb4_general_ci;

-- -----------------------------------------------------
-- Table `student_program_enrollments` (เก็บประวัตินิสิตกับหลักสูตร)
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `student_program_enrollments` (
    `id_enrollments` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `users_id` INT UNSIGNED NOT NULL,
    `program_versions_id` INT UNSIGNED NOT NULL,
    `admission_year` INT NOT NULL,
    `current_year_level` TINYINT UNSIGNED NOT NULL DEFAULT 1 COMMENT 'ชั้นปีปัจจุบันของนิสิต',
    `status` ENUM(
        'studying',
        'suspended',
        'resigned',
        'graduated',
        'dismissed'
    ) NOT NULL DEFAULT 'studying',
    `started_at` DATE NOT NULL,
    `graduated_at` DATE NULL,
    PRIMARY KEY (`id_enrollments`),
    INDEX `idx_spe_user_program` (
        `users_id` ASC,
        `program_versions_id` ASC
    ),
    UNIQUE INDEX `uq_spe_id_user_pv` (
        `id_enrollments` ASC,
        `users_id` ASC,
        `program_versions_id` ASC
    ),
    CONSTRAINT `fk_spe_users` FOREIGN KEY (`users_id`) REFERENCES `users` (`id_users`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_spe_program_versions` FOREIGN KEY (`program_versions_id`) REFERENCES `program_versions` (`id_program_versions`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `chk_spe_admission_year` CHECK (`admission_year` >= 2500),
    CONSTRAINT `chk_spe_year_level` CHECK (`current_year_level` BETWEEN 1 AND 8),
    CONSTRAINT `chk_spe_graduated_date` CHECK (
        `graduated_at` IS NULL
        OR `graduated_at` >= `started_at`
    )
) ENGINE = InnoDB DEFAULT CHARACTER SET = utf8mb4 COLLATE = utf8mb4_general_ci;

-- -----------------------------------------------------
-- Table `student_promotion_batches` (เก็บข้อมูลการปรับชั้นปีของนิสิต)
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `student_promotion_batches` (
    `id_student_promotion_batches` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `program_versions_id` INT UNSIGNED NOT NULL,
    `academic_terms_id` INT UNSIGNED NOT NULL,
    `from_year_level` TINYINT UNSIGNED NOT NULL,
    `to_year_level` TINYINT UNSIGNED NOT NULL,
    `affected_count` INT UNSIGNED NOT NULL DEFAULT 0,
    `note` VARCHAR(255) NULL DEFAULT NULL,
    `promoted_by` INT UNSIGNED NOT NULL,
    `promoted_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id_student_promotion_batches`),
    INDEX `idx_spb_pv_term` (`program_versions_id` ASC, `academic_terms_id` ASC),
    INDEX `idx_spb_promoted_by` (`promoted_by` ASC),
    CONSTRAINT `fk_spb_program_version` FOREIGN KEY (`program_versions_id`) REFERENCES `program_versions` (`id_program_versions`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_spb_academic_term` FOREIGN KEY (`academic_terms_id`) REFERENCES `academic_terms` (`id_academic_terms`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_spb_promoted_by` FOREIGN KEY (`promoted_by`) REFERENCES `users` (`id_users`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `chk_spb_year_range` CHECK (`from_year_level` BETWEEN 1 AND 7 AND `to_year_level` BETWEEN 2 AND 8 AND `to_year_level` > `from_year_level`)
) ENGINE = InnoDB DEFAULT CHARACTER SET = utf8mb4 COLLATE = utf8mb4_general_ci;

-- student_year_level_histories (เก็บประวัติการปรับชั้นปีของนิสิต)
CREATE TABLE IF NOT EXISTS `student_year_level_histories` (
    `id_student_year_level_histories` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `enrollments_id` INT UNSIGNED NOT NULL,
    `promotion_batch_id` INT UNSIGNED NULL DEFAULT NULL COMMENT 'NULL = ปรับชั้นปีรายบุคคล',
    `academic_terms_id` INT UNSIGNED NOT NULL,
    `from_year_level` TINYINT UNSIGNED NOT NULL,
    `to_year_level` TINYINT UNSIGNED NOT NULL,
    `changed_by` INT UNSIGNED NOT NULL,
    `reason` VARCHAR(255) NULL DEFAULT NULL,
    `changed_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id_student_year_level_histories`),
    INDEX `idx_sylh_enrollment_time` (`enrollments_id` ASC, `changed_at` ASC),
    INDEX `idx_sylh_batch` (`promotion_batch_id` ASC),
    INDEX `idx_sylh_term` (`academic_terms_id` ASC),
    INDEX `idx_sylh_changed_by` (`changed_by` ASC),
    CONSTRAINT `fk_sylh_enrollment` FOREIGN KEY (`enrollments_id`) REFERENCES `student_program_enrollments` (`id_enrollments`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_sylh_batch` FOREIGN KEY (`promotion_batch_id`) REFERENCES `student_promotion_batches` (`id_student_promotion_batches`) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `fk_sylh_term` FOREIGN KEY (`academic_terms_id`) REFERENCES `academic_terms` (`id_academic_terms`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_sylh_changed_by` FOREIGN KEY (`changed_by`) REFERENCES `users` (`id_users`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `chk_sylh_year_range` CHECK (`from_year_level` BETWEEN 1 AND 8 AND `to_year_level` BETWEEN 1 AND 8 AND `from_year_level` <> `to_year_level`)
) ENGINE = InnoDB DEFAULT CHARACTER SET = utf8mb4 COLLATE = utf8mb4_general_ci;

-- -----------------------------------------------------
-- Table `course_members` (นิสิตผู้เรียน)
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `course_members` (
    `id_course_members` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id` INT UNSIGNED NOT NULL,
    `enrollments_id` INT UNSIGNED NOT NULL,
    `program_versions_id` INT UNSIGNED NOT NULL,
    `section_id` INT UNSIGNED NOT NULL,
    `course_offerings_id` INT UNSIGNED NOT NULL,
    `enrollment_status` ENUM(
        'enrolled',
        'dropped',
        'completed'
    ) NOT NULL DEFAULT 'enrolled',
    `enrolled_at` DATETIME NOT NULL,
    `dropped_at` DATETIME NULL,
    PRIMARY KEY (`id_course_members`),
    INDEX `fk_cm_users1_idx` (`user_id` ASC),
    UNIQUE INDEX `uq_user_offering` (
        `user_id` ASC,
        `course_offerings_id` ASC
    ),
    UNIQUE INDEX `uq_cm_user_section_offering` (
        `user_id` ASC,
        `section_id` ASC,
        `course_offerings_id` ASC
    ),
    CONSTRAINT `fk_cm_users1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id_users`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_cm_sections1` FOREIGN KEY (
        `section_id`,
        `course_offerings_id`
    ) REFERENCES `sections` (
        `id_sections`,
        `course_offerings_id`
    ) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_cm_enrollment` FOREIGN KEY (
        `enrollments_id`,
        `user_id`,
        `program_versions_id`
    ) REFERENCES `student_program_enrollments` (
        `id_enrollments`,
        `users_id`,
        `program_versions_id`
    ) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_cm_offering_pv` FOREIGN KEY (
        `course_offerings_id`,
        `program_versions_id`
    ) REFERENCES `course_offerings` (
        `id_course_offerings`,
        `program_versions_id`
    ) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE = InnoDB DEFAULT CHARACTER SET = utf8mb4 COLLATE = utf8mb4_general_ci;

-- -----------------------------------------------------
-- Table `section_staff` (อาจารย์/ผู้ช่วยสอนประจำกลุ่ม)
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `section_staff` (
    `id_section_staff` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id` INT UNSIGNED NOT NULL,
    `section_id` INT UNSIGNED NOT NULL,
    `staff_role` VARCHAR(20) NOT NULL DEFAULT 'teacher',
    PRIMARY KEY (`id_section_staff`),
    UNIQUE INDEX `uq_staff_section` (
        `user_id` ASC,
        `section_id` ASC
    ),
    INDEX `idx_ss_section_id` (`section_id` ASC),
    CONSTRAINT `fk_ss_users` FOREIGN KEY (`user_id`) REFERENCES `users` (`id_users`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_ss_sections` FOREIGN KEY (`section_id`) REFERENCES `sections` (`id_sections`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `chk_staff_role` CHECK (
        `staff_role` IN (
            'teacher',
            'assistant',
            'coordinator'
        )
    )
) ENGINE = InnoDB DEFAULT CHARACTER SET = utf8mb4 COLLATE = utf8mb4_general_ci;

-- -----------------------------------------------------
-- Table `chapters`
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `chapters` (
    `id_chapters` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `courses_id` INT UNSIGNED NOT NULL,
    `chapters_no` INT UNSIGNED NOT NULL,
    `name` VARCHAR(150) NOT NULL,
    PRIMARY KEY (`id_chapters`),
    INDEX `fk_chapters_courses1_idx` (`courses_id` ASC),
    UNIQUE INDEX `uq_course_chapter_no` (
        `courses_id` ASC,
        `chapters_no` ASC
    ),
    UNIQUE INDEX `uq_chap_course` (
        `id_chapters` ASC,
        `courses_id` ASC
    ),
    CONSTRAINT `fk_chapters_courses1` FOREIGN KEY (`courses_id`) REFERENCES `courses` (`id_courses`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE = InnoDB DEFAULT CHARACTER SET = utf8mb4 COLLATE = utf8mb4_general_ci;

-- -----------------------------------------------------
-- `bloom_levels`
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `bloom_levels` (
    `code` VARCHAR(20) NOT NULL,
    `name_th` VARCHAR(100) NOT NULL,
    `name_en` VARCHAR(100) NOT NULL,
    `sort_order` INT UNSIGNED NULL,
    PRIMARY KEY (`code`),
    UNIQUE INDEX `sort_order_UNIQUE` (`sort_order` ASC)
) ENGINE = InnoDB DEFAULT CHARACTER SET = utf8mb4 COLLATE = utf8mb4_general_ci;

-- -----------------------------------------------------
-- Table `learning_outcomes` (ผลการเรียนรู้)
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `learning_outcomes` (
    `id_learning_outcomes` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `courses_id` INT UNSIGNED NOT NULL,
    `code` VARCHAR(20) NOT NULL,
    `description` TEXT NOT NULL,
    PRIMARY KEY (`id_learning_outcomes`),
    UNIQUE INDEX `uq_course_lo_code` (`courses_id` ASC, `code` ASC),
    UNIQUE INDEX `uq_lo_id_course` (
        `id_learning_outcomes` ASC,
        `courses_id` ASC
    ),
    CONSTRAINT `fk_lo_courses` FOREIGN KEY (`courses_id`) REFERENCES `courses` (`id_courses`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE = InnoDB DEFAULT CHARACTER SET = utf8mb4 COLLATE = utf8mb4_general_ci;

-- -----------------------------------------------------
-- Table `main_concepts` (แนวคิดหลัก)
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `main_concepts` (
    `id_main_concepts` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `courses_id` INT UNSIGNED NOT NULL,
    `code` VARCHAR(20) NOT NULL,
    `name` VARCHAR(150) NOT NULL,
    `description` TEXT NULL,
    PRIMARY KEY (`id_main_concepts`),
    UNIQUE INDEX `uq_course_main_concept_code` (`courses_id` ASC, `code` ASC),
    UNIQUE INDEX `uq_course_main_concept_name` (`courses_id` ASC, `name` ASC),
    UNIQUE INDEX `uq_main_concept_id_course` (
        `id_main_concepts` ASC,
        `courses_id` ASC
    ),
    CONSTRAINT `fk_main_concepts_courses` FOREIGN KEY (`courses_id`) REFERENCES `courses` (`id_courses`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE = InnoDB DEFAULT CHARACTER SET = utf8mb4 COLLATE = utf8mb4_general_ci;

-- -----------------------------------------------------
-- Table `sub_concepts` (แนวคิดรอง)
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `sub_concepts` (
    `id_sub_concepts` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `main_concepts_id` INT UNSIGNED NOT NULL,
    `courses_id` INT UNSIGNED NOT NULL,
    `code` VARCHAR(20) NOT NULL,
    `name` VARCHAR(150) NOT NULL,
    `description` TEXT NULL,
    PRIMARY KEY (`id_sub_concepts`),
    UNIQUE INDEX `uq_main_concept_sub_code` (
        `main_concepts_id` ASC,
        `code` ASC
    ),
    UNIQUE INDEX `uq_main_concept_sub_name` (`main_concepts_id` ASC, `name` ASC),
    UNIQUE INDEX `uq_sub_concept_id_course` (
        `id_sub_concepts` ASC,
        `courses_id` ASC
    ),
    CONSTRAINT `fk_sub_concepts_main` FOREIGN KEY (
        `main_concepts_id`,
        `courses_id`
    ) REFERENCES `main_concepts` (
        `id_main_concepts`,
        `courses_id`
    ) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE = InnoDB DEFAULT CHARACTER SET = utf8mb4 COLLATE = utf8mb4_general_ci;

-- -----------------------------------------------------
-- Table 'question_scenarios` (สถานการณ์ของข้อสอบ)
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `question_scenarios` (
    `id_question_scenarios` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `courses_id` INT UNSIGNED NULL DEFAULT NULL COMMENT 'NULL = ใช้ร่วมกันในคลังกลาง',
    `title` VARCHAR(200) NOT NULL,
    `content` LONGTEXT NOT NULL,
    `image` VARCHAR(255) NULL DEFAULT NULL,
    `status` VARCHAR(20) NOT NULL DEFAULT 'draft',
    `created_by` INT UNSIGNED NOT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` DATETIME NULL DEFAULT NULL,
    PRIMARY KEY (`id_question_scenarios`),
    INDEX `idx_qs_course` (`courses_id` ASC),
    INDEX `idx_qs_created_by` (`created_by` ASC),
    CONSTRAINT `fk_qs_course` FOREIGN KEY (`courses_id`) REFERENCES `courses` (`id_courses`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_qs_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id_users`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `chk_qs_status` CHECK (`status` IN ('draft', 'published', 'archived'))
) ENGINE = InnoDB DEFAULT CHARACTER SET = utf8mb4 COLLATE = utf8mb4_general_ci;

-- -----------------------------------------------------
-- Table `questions`
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `questions` (
    `id_questions` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `chapters_id` INT UNSIGNED NULL DEFAULT NULL COMMENT 'NULL = ข้อสอบคลังกลางยังไม่ผูกบทเรียน',
    `courses_id` INT UNSIGNED NULL DEFAULT NULL COMMENT 'NULL = ข้อสอบคลังกลางยังไม่ผูกวิชา',
    `scenario_id` INT UNSIGNED NULL DEFAULT NULL COMMENT 'ผูกโจทย์สถานการณ์ (FR-11)',
    `scenario_sort_order` INT UNSIGNED NULL DEFAULT NULL COMMENT 'ลำดับข้อย่อยภายใต้สถานการณ์เดียวกัน',
    `bloom_levels_code` VARCHAR(20) NOT NULL,
    `type` VARCHAR(45) NOT NULL,
    `text` TEXT NOT NULL,
    `image` VARCHAR(255) NULL,
    `explanation` TEXT NULL,
    `manual_max_score` DECIMAL(7, 2) NULL,
    `shuffle_options` TINYINT(1) NOT NULL DEFAULT 0,
    `student_visible` TINYINT(1) NOT NULL DEFAULT 0,
    `status` VARCHAR(20) NOT NULL DEFAULT 'draft',
    `scoring_logic` VARCHAR(45) NOT NULL,
    `created_at` DATETIME NOT NULL,
    `updated_at` DATETIME NOT NULL,
    `deleted_at` DATETIME NULL,
    PRIMARY KEY (`id_questions`),
    INDEX `fk_questions_bloom_levels1_idx` (`bloom_levels_code` ASC),
    INDEX `idx_questions_course` (`courses_id` ASC),
    INDEX `idx_questions_scenario` (`scenario_id` ASC, `scenario_sort_order` ASC),
    UNIQUE INDEX `uq_q_id_course` (`id_questions` ASC, `courses_id` ASC),
    CONSTRAINT `fk_questions_course` FOREIGN KEY (`courses_id`) REFERENCES `courses` (`id_courses`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_questions_chap_course` FOREIGN KEY (`chapters_id`, `courses_id`) REFERENCES `chapters` (`id_chapters`, `courses_id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_questions_scenario` FOREIGN KEY (`scenario_id`) REFERENCES `question_scenarios` (`id_question_scenarios`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_questions_bloom_levels1` FOREIGN KEY (`bloom_levels_code`) REFERENCES `bloom_levels` (`code`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `chk_q_manual_score` CHECK (`manual_max_score` IS NULL OR `manual_max_score` >= 0),
    CONSTRAINT `chk_q_status` CHECK (`status` IN ('draft', 'published', 'archived')),
    CONSTRAINT `chk_q_type` CHECK (`type` IN ('multiple_choice', 'true_false', 'short_answer', 'essay', 'matching')),
    CONSTRAINT `chk_q_shuffle_opts` CHECK (`shuffle_options` IN (0, 1)),
    CONSTRAINT `chk_q_student_vis` CHECK (`student_visible` IN (0, 1))
) ENGINE = InnoDB DEFAULT CHARACTER SET = utf8mb4 COLLATE = utf8mb4_general_ci;

-- -----------------------------------------------------
-- Table question_course_mappings (เชื่อมโยงข้อสอบกับวิชา)
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `question_course_mappings` (
    `id_question_course_mappings` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `questions_id` INT UNSIGNED NOT NULL,
    `courses_id` INT UNSIGNED NOT NULL,
    `chapters_id` INT UNSIGNED NULL DEFAULT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id_question_course_mappings`),
    UNIQUE INDEX `uq_qcm_question_course` (`questions_id` ASC, `courses_id` ASC),
    INDEX `idx_qcm_course_chap` (`courses_id` ASC, `chapters_id` ASC),
    CONSTRAINT `fk_qcm_question` FOREIGN KEY (`questions_id`) REFERENCES `questions` (`id_questions`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_qcm_course` FOREIGN KEY (`courses_id`) REFERENCES `courses` (`id_courses`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_qcm_chap_course` FOREIGN KEY (`chapters_id`, `courses_id`) REFERENCES `chapters` (`id_chapters`, `courses_id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE = InnoDB DEFAULT CHARACTER SET = utf8mb4 COLLATE = utf8mb4_general_ci;

-- -----------------------------------------------------
--  `question_versions`
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `question_versions` (
    `id_question_versions` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `version_no` INT UNSIGNED NOT NULL,
    `content_snapshot` JSON NOT NULL,
    `bloom_levels_code` VARCHAR(20) NOT NULL COMMENT 'Snapshot ระดับ Bloom ของข้อสอบเวอร์ชันนี้',
    `parent_version_id` INT UNSIGNED NULL,
    `created_at` DATETIME NOT NULL,
    `created_by` INT UNSIGNED NOT NULL,
    `questions_id` INT UNSIGNED NOT NULL,
    `courses_id` INT UNSIGNED NULL DEFAULT NULL,
    PRIMARY KEY (`id_question_versions`),
    INDEX `created_by_idx` (`created_by` ASC),
    INDEX `idx_qv_course` (`courses_id` ASC),
    INDEX `idx_qv_bloom` (`bloom_levels_code` ASC),
    UNIQUE INDEX `uq_question_version` (`questions_id`, `version_no`),
    UNIQUE INDEX `uq_qv_id_question` (`id_question_versions` ASC, `questions_id` ASC),
    UNIQUE INDEX `uq_qv_id_course` (`id_question_versions` ASC, `courses_id` ASC),
    CONSTRAINT `fk_qversion_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id_users`) ON DELETE NO ACTION ON UPDATE NO ACTION,
    CONSTRAINT `fk_qversion_question` FOREIGN KEY (`questions_id`) REFERENCES `questions` (`id_questions`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_qversion_course` FOREIGN KEY (`courses_id`) REFERENCES `courses` (`id_courses`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_qv_bloom` FOREIGN KEY (`bloom_levels_code`) REFERENCES `bloom_levels` (`code`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_question_versions_parent` FOREIGN KEY (`parent_version_id`, `questions_id`) REFERENCES `question_versions` (`id_question_versions`, `questions_id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `chk_qv_version_no` CHECK (`version_no` >= 1)
) ENGINE = InnoDB DEFAULT CHARACTER SET = utf8mb4 COLLATE = utf8mb4_general_ci;

-- -----------------------------------------------------
--  `question_learning_outcomes` (เชื่อมโยงข้อสอบกับผลการเรียนรู้)
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `question_learning_outcomes` (
    `question_version_id` INT UNSIGNED NOT NULL,
    `learning_outcomes_id` INT UNSIGNED NOT NULL,
    `questions_id` INT UNSIGNED NOT NULL,
    `courses_id` INT UNSIGNED NOT NULL,
    PRIMARY KEY (
        `question_version_id`,
        `learning_outcomes_id`
    ),
    CONSTRAINT `fk_qlo_version_question` FOREIGN KEY (
        `question_version_id`,
        `questions_id`
    ) REFERENCES `question_versions` (
        `id_question_versions`,
        `questions_id`
    ) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_qlo_lo_course` FOREIGN KEY (
        `learning_outcomes_id`,
        `courses_id`
    ) REFERENCES `learning_outcomes` (
        `id_learning_outcomes`,
        `courses_id`
    ) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE = InnoDB DEFAULT CHARACTER SET = utf8mb4 COLLATE = utf8mb4_general_ci;

-- -----------------------------------------------------
--  `question_options` (ตัวเลือกของข้อสอบ)
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `question_options` (
    `id_question_options` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `question_versions_id` INT UNSIGNED NOT NULL,
    `label` VARCHAR(20) NOT NULL,
    `text` TEXT NOT NULL,
    `is_correct` TINYINT(1) NOT NULL DEFAULT 0,
    `sort_order` INT UNSIGNED NOT NULL,
    `image` VARCHAR(255) NULL,
    PRIMARY KEY (`id_question_options`),
    UNIQUE INDEX `uq_question_sort` (
        `question_versions_id` ASC,
        `sort_order` ASC
    ),
    UNIQUE INDEX `uq_option_version` (
        `id_question_options`,
        `question_versions_id`
    ),
    UNIQUE INDEX `uq_question_version_label` (
        `question_versions_id` ASC,
        `label` ASC
    ),
    CONSTRAINT `fk_qo_question_versions` FOREIGN KEY (`question_versions_id`) REFERENCES `question_versions` (`id_question_versions`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `chk_qo_is_correct` CHECK (`is_correct` IN (0, 1))
) ENGINE = InnoDB DEFAULT CHARACTER SET = utf8mb4 COLLATE = utf8mb4_general_ci;

-- -----------------------------------------------------
-- Table `exams` ชุดข้อสอบ
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `exams` (
    `id_exams` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `course_offerings_id` INT UNSIGNED NOT NULL,
    `courses_id` INT UNSIGNED NOT NULL,
    `parent_exam_id` INT UNSIGNED NULL,
    `created_by` INT UNSIGNED NOT NULL,
    `kind` ENUM(
        'midterm',
        'final',
        'quiz',
        'pre_post'
    ) NOT NULL,
    `title` VARCHAR(150) NOT NULL,
    `mode` ENUM('fixed', 'blueprint') NOT NULL,
    `duration_min` INT UNSIGNED NOT NULL,
    `grace_period_min` INT UNSIGNED NOT NULL DEFAULT 5 COMMENT 'ระยะเวลาผ่อนผันหลังหมดเวลา (นาที)',
    `max_attempts` INT UNSIGNED NOT NULL,
    `open_at` DATETIME NOT NULL,
    `close_at` DATETIME NOT NULL,
    `show_answer` ENUM(
        'never',
        'after_submit',
        'after_close',
        'after_announce'
    ) NULL,
    `shuffle_questions` TINYINT(1) NOT NULL DEFAULT 0,
    `require_exam_code` TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'บังคับกรอกรหัสเข้าสอบ (FR-02)',
    `access_code_ttl_min` INT UNSIGNED NOT NULL DEFAULT 10 COMMENT 'อายุรหัสเข้าสอบ (นาที)',
    `require_code_on_resume` TINYINT(1) NOT NULL DEFAULT 1 COMMENT 'บังคับกรอกรหัสใหม่เมื่อหลุด/ออกจากหน้าสอบ',
    `require_location_consent` TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'บังคับยินยอมและบันทึกพิกัดระหว่างสอบ (FR-04)',
    `created_at` DATETIME NOT NULL,
    `updated_at` DATETIME NOT NULL,
    `published_at` DATETIME NULL,
    `exam_snapshot` JSON NOT NULL,
    `status` VARCHAR(20) NOT NULL DEFAULT 'draft',
    `deleted_at` DATETIME NULL,
    PRIMARY KEY (`id_exams`),
    UNIQUE INDEX `uq_exam_offering` (
        `id_exams` ASC,
        `course_offerings_id` ASC
    ),
    UNIQUE INDEX `uq_exam_id_course` (
        `id_exams` ASC,
        `courses_id` ASC
    ),
    INDEX `idx_exams_created_by` (`created_by` ASC),
    CONSTRAINT `fk_exams_co_course` FOREIGN KEY (
        `course_offerings_id`,
        `courses_id`
    ) REFERENCES `course_offerings` (
        `id_course_offerings`,
        `courses_id`
    ) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_exams_parent` FOREIGN KEY (
        `parent_exam_id`,
        `course_offerings_id`
    ) REFERENCES `exams` (
        `id_exams`,
        `course_offerings_id`
    ) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `chk_exam_req_code` CHECK (`require_exam_code` IN (0, 1)),
    CONSTRAINT `chk_exam_code_ttl` CHECK (`access_code_ttl_min` >= 1),
    CONSTRAINT `chk_exam_req_code_resume` CHECK (`require_code_on_resume` IN (0, 1)),
    CONSTRAINT `chk_exam_req_loc` CHECK (`require_location_consent` IN (0, 1)),
    CONSTRAINT `fk_exams_users1` FOREIGN KEY (`created_by`) REFERENCES `users` (`id_users`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `chk_exam_dates` CHECK (`close_at` > `open_at`),
    CONSTRAINT `chk_exam_duration` CHECK (`duration_min` > 0),
    CONSTRAINT `chk_exam_grace_period` CHECK (`grace_period_min` >= 0),
    CONSTRAINT `chk_exam_max_attempts` CHECK (`max_attempts` > 0),
    CONSTRAINT `chk_exam_shuffle_q` CHECK (`shuffle_questions` IN (0, 1)),
    CONSTRAINT `chk_exam_status` CHECK (
        `status` IN (
            'draft',
            'published',
            'closed',
            'archived'
        )
    )
) ENGINE = InnoDB DEFAULT CHARACTER SET = utf8mb4 COLLATE = utf8mb4_general_ci;

-- -----------------------------------------------------
--  `exam_sections` (เชื่อมโยงชุดข้อสอบกับกลุ่มเรียน)
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `exam_sections` (
    `exams_id` INT UNSIGNED NOT NULL,
    `sections_id` INT UNSIGNED NOT NULL,
    `course_offerings_id` INT UNSIGNED NOT NULL,
    PRIMARY KEY (`exams_id`, `sections_id`),
    UNIQUE INDEX `uq_es_exam_section_offering` (
        `exams_id`,
        `sections_id`,
        `course_offerings_id`
    ),
    CONSTRAINT `fk_es_exams` FOREIGN KEY (
        `exams_id`,
        `course_offerings_id`
    ) REFERENCES `exams` (
        `id_exams`,
        `course_offerings_id`
    ) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_es_sections` FOREIGN KEY (
        `sections_id`,
        `course_offerings_id`
    ) REFERENCES `sections` (
        `id_sections`,
        `course_offerings_id`
    ) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE = InnoDB DEFAULT CHARACTER SET = utf8mb4 COLLATE = utf8mb4_general_ci;

-- -----------------------------------------------------
-- table `exam_access_codes` (รหัสเข้าสอบ)
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `exam_access_codes` (
    `id_exam_access_codes` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `exams_id` INT UNSIGNED NOT NULL,
    `sections_id` INT UNSIGNED NULL DEFAULT NULL COMMENT 'NULL = ใช้ได้ทุกกลุ่มเรียนในชุดสอบ',
    `section_scope_key` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '0 = ทุกกลุ่มเรียน (ใช้คุม Unique แทน NULL)',    `access_code` CHAR(5) NOT NULL COMMENT 'รหัสเข้าสอบ 5 หลัก (A-Z, 0-9)',
    `status` ENUM('active', 'revoked', 'expired') NOT NULL DEFAULT 'active',
    `active_code_key` TINYINT(1) GENERATED ALWAYS AS (
        CASE WHEN `status` = 'active' THEN 1 ELSE NULL END
    ) STORED COMMENT 'บังคับให้มีรหัส active ได้เพียง 1 รหัสต่อชุดสอบ/กลุ่มเรียน',
    `valid_from` DATETIME NOT NULL,
    `valid_until` DATETIME NOT NULL,
    `created_by` INT UNSIGNED NOT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `revoked_by` INT UNSIGNED NULL DEFAULT NULL COMMENT 'ผู้ยกเลิกรหัสหรือผู้กดสร้างรหัสใหม่แทน',
    `revoked_at` DATETIME NULL DEFAULT NULL COMMENT 'เวลาที่ยกเลิกรหัสก่อนครบกำหนด',
    PRIMARY KEY (`id_exam_access_codes`),
    UNIQUE INDEX `uq_eac_id_exam` (`id_exam_access_codes` ASC, `exams_id` ASC),
    UNIQUE INDEX `uq_eac_single_active` (`exams_id` ASC, `section_scope_key` ASC, `active_code_key` ASC),
    INDEX `idx_eac_exam_status` (`exams_id` ASC, `status` ASC),
    INDEX `idx_eac_code_status` (`access_code` ASC, `status` ASC),
    INDEX `idx_eac_created_by` (`created_by` ASC),
    INDEX `idx_eac_revoked_by` (`revoked_by` ASC),
    CONSTRAINT `fk_eac_exam` FOREIGN KEY (`exams_id`) REFERENCES `exams` (`id_exams`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_eac_exam_section` FOREIGN KEY (`exams_id`, `sections_id`) REFERENCES `exam_sections` (`exams_id`, `sections_id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_eac_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id_users`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_eac_revoked_by` FOREIGN KEY (`revoked_by`) REFERENCES `users` (`id_users`) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `chk_eac_code_format` CHECK (`access_code` REGEXP '^[A-Z0-9]{5}$'),
    CONSTRAINT `chk_eac_valid_dates` CHECK (`valid_until` > `valid_from`)
) ENGINE = InnoDB DEFAULT CHARACTER SET = utf8mb4 COLLATE = utf8mb4_general_ci;

-- -----------------------------------------------------
-- Table `exam_questions`
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `exam_questions` (
    `id_exam_questions` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `exams_id` INT UNSIGNED NOT NULL,
    `questions_id` INT UNSIGNED NOT NULL,
    `question_version_id` INT UNSIGNED NOT NULL,
    `courses_id` INT UNSIGNED NOT NULL,
    `sort_order` INT UNSIGNED NOT NULL,
    `score` DECIMAL(7, 2) NOT NULL,
    PRIMARY KEY (`id_exam_questions`),
    UNIQUE INDEX `uq_exam_question_sort` (
        `exams_id` ASC,
        `sort_order` ASC
    ),
    UNIQUE INDEX `uq_exam_question_version` (
        `exams_id` ASC,
        `question_version_id` ASC
    ),
    UNIQUE INDEX `uq_eq_id_exam` (
        `id_exam_questions` ASC,
        `exams_id` ASC
    ),
    UNIQUE INDEX `uq_eq_id_version` (
        `id_exam_questions` ASC,
        `question_version_id` ASC
    ),
    UNIQUE INDEX `uq_eq_id_exam_version` (
        `id_exam_questions` ASC,
        `exams_id` ASC,
        `question_version_id` ASC
    ),
    UNIQUE INDEX `uq_eq_id_exam_question` (`id_exam_questions` ASC, `exams_id` ASC, `questions_id` ASC),
    CONSTRAINT `fk_eq_exam_course` FOREIGN KEY (`exams_id`, `courses_id`) REFERENCES `exams` (`id_exams`, `courses_id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_eq_qv_question` FOREIGN KEY (`question_version_id`, `questions_id`) REFERENCES `question_versions` (`id_question_versions`, `questions_id`) ON DELETE RESTRICT ON UPDATE CASCADE,    CONSTRAINT `chk_eq_score` CHECK (`score` >= 0)
) ENGINE = InnoDB DEFAULT CHARACTER SET = utf8mb4 COLLATE = utf8mb4_general_ci;

-- -----------------------------------------------------
-- Table `exam_blueprints` (แบบแผนการสร้างชุดข้อสอบ)
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `exam_blueprints` (
    `id_exam_blueprints` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `exams_id` INT UNSIGNED NOT NULL,
    `title` VARCHAR(150) NOT NULL,
    `total_questions` INT UNSIGNED NOT NULL,
    `total_score` DECIMAL(7, 2) NULL DEFAULT NULL,
    `created_by` INT UNSIGNED NOT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id_exam_blueprints`),
    UNIQUE INDEX `uq_eb_exam` (`exams_id` ASC),
    INDEX `idx_eb_created_by` (`created_by` ASC),
    CONSTRAINT `fk_eb_exam` FOREIGN KEY (`exams_id`) REFERENCES `exams` (`id_exams`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_eb_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id_users`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `chk_eb_total_q` CHECK (`total_questions` >= 1),
    CONSTRAINT `chk_eb_total_score` CHECK (`total_score` IS NULL OR `total_score` >= 0)
) ENGINE = InnoDB DEFAULT CHARACTER SET = utf8mb4 COLLATE = utf8mb4_general_ci;

-- table `exam_blueprint_rules` (กฎเกณฑ์การสร้างชุดข้อสอบตามแบบแผน)
CREATE TABLE IF NOT EXISTS `exam_blueprint_rules` (
    `id_exam_blueprint_rules` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `exam_blueprints_id` INT UNSIGNED NOT NULL,
    `chapters_id` INT UNSIGNED NULL DEFAULT NULL,
    `main_concepts_id` INT UNSIGNED NULL DEFAULT NULL,
    `sub_concepts_id` INT UNSIGNED NULL DEFAULT NULL,
    `bloom_levels_code` VARCHAR(20) NULL DEFAULT NULL,
    `question_type` VARCHAR(45) NULL DEFAULT NULL,
    `scenario_id` INT UNSIGNED NULL DEFAULT NULL,
    `question_count` INT UNSIGNED NOT NULL,
    `score_per_question` DECIMAL(7, 2) NOT NULL,
    `sort_order` INT UNSIGNED NOT NULL DEFAULT 1,
    PRIMARY KEY (`id_exam_blueprint_rules`),
    INDEX `idx_ebr_blueprint` (`exam_blueprints_id` ASC, `sort_order` ASC),
    INDEX `idx_ebr_chapter` (`chapters_id` ASC),
    INDEX `idx_ebr_main_concept` (`main_concepts_id` ASC),
    INDEX `idx_ebr_sub_concept` (`sub_concepts_id` ASC),
    INDEX `idx_ebr_bloom` (`bloom_levels_code` ASC),
    INDEX `idx_ebr_scenario` (`scenario_id` ASC),
    CONSTRAINT `fk_ebr_blueprint` FOREIGN KEY (`exam_blueprints_id`) REFERENCES `exam_blueprints` (`id_exam_blueprints`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_ebr_chapter` FOREIGN KEY (`chapters_id`) REFERENCES `chapters` (`id_chapters`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_ebr_main_concept` FOREIGN KEY (`main_concepts_id`) REFERENCES `main_concepts` (`id_main_concepts`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_ebr_sub_concept` FOREIGN KEY (`sub_concepts_id`) REFERENCES `sub_concepts` (`id_sub_concepts`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_ebr_bloom` FOREIGN KEY (`bloom_levels_code`) REFERENCES `bloom_levels` (`code`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_ebr_scenario` FOREIGN KEY (`scenario_id`) REFERENCES `question_scenarios` (`id_question_scenarios`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `chk_ebr_q_count` CHECK (`question_count` >= 1),
    CONSTRAINT `chk_ebr_score` CHECK (`score_per_question` >= 0),
    CONSTRAINT `chk_ebr_q_type` CHECK (
        `question_type` IS NULL
        OR `question_type` IN ('multiple_choice', 'true_false', 'short_answer', 'essay', 'matching')
    )
) ENGINE = InnoDB DEFAULT CHARACTER SET = utf8mb4 COLLATE = utf8mb4_general_ci;

-- -----------------------------------------------------
-- Table exam_question_version_releases (บันทึกการเปลี่ยนเวอร์ชันข้อสอบ)
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `exam_question_version_releases` (
    `id_exam_question_version_releases` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `exam_question_id` INT UNSIGNED NOT NULL,
    `exams_id` INT UNSIGNED NOT NULL,
    `questions_id` INT UNSIGNED NOT NULL,
    `old_question_version_id` INT UNSIGNED NULL DEFAULT NULL,
    `new_question_version_id` INT UNSIGNED NOT NULL,
    `release_reason` VARCHAR(255) NOT NULL,
    `released_by` INT UNSIGNED NOT NULL,
    `released_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id_exam_question_version_releases`),
    UNIQUE INDEX `uq_eqvr_id_eq_new_ver` (`id_exam_question_version_releases` ASC, `exam_question_id` ASC, `new_question_version_id` ASC),
    INDEX `idx_eqvr_eq_time` (`exam_question_id` ASC, `released_at` ASC),
    INDEX `idx_eqvr_released_by` (`released_by` ASC),
    CONSTRAINT `fk_eqvr_eq_exam_question` FOREIGN KEY (`exam_question_id`, `exams_id`, `questions_id`) REFERENCES `exam_questions` (`id_exam_questions`, `exams_id`, `questions_id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_eqvr_old_ver` FOREIGN KEY (`old_question_version_id`, `questions_id`) REFERENCES `question_versions` (`id_question_versions`, `questions_id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_eqvr_new_ver` FOREIGN KEY (`new_question_version_id`, `questions_id`) REFERENCES `question_versions` (`id_question_versions`, `questions_id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_eqvr_released_by` FOREIGN KEY (`released_by`) REFERENCES `users` (`id_users`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE = InnoDB DEFAULT CHARACTER SET = utf8mb4 COLLATE = utf8mb4_general_ci;

-- -----------------------------------------------------
-- Table `exam_eligibilities` (สิทธิ์ในการเข้าสอบ)
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `exam_eligibilities` (
    `id_exam_eligibilities` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `exams_id` INT UNSIGNED NOT NULL,
    `users_id` INT UNSIGNED NOT NULL,
    `sections_id` INT UNSIGNED NOT NULL,
    `course_offerings_id` INT UNSIGNED NOT NULL,
    `status` ENUM('eligible', 'revoked') NOT NULL DEFAULT 'eligible',
    `custom_open_at` DATETIME NULL DEFAULT NULL COMMENT 'เวลาเปิดสอบเฉพาะบุคคล (สอบซ่อม/ย้อนหลัง)',
    `custom_close_at` DATETIME NULL DEFAULT NULL COMMENT 'เวลาปิดสอบเฉพาะบุคคล (สอบซ่อม/ย้อนหลัง)',
    `note` VARCHAR(255) NULL DEFAULT NULL COMMENT 'หมายเหตุการเปิดสิทธิ์สอบพิเศษ',
    `created_at` DATETIME NOT NULL,
    PRIMARY KEY (`id_exam_eligibilities`),
    UNIQUE INDEX `uq_exam_user` (
        `exams_id` ASC,
        `users_id` ASC
    ),
    INDEX `idx_es_exam_section_offering` (
        `exams_id`,
        `sections_id`,
        `course_offerings_id`
    ),
    UNIQUE INDEX `uq_elig_id_exam_user_sec_co` (
        `id_exam_eligibilities` ASC,
        `exams_id` ASC,
        `users_id` ASC,
        `sections_id` ASC,
        `course_offerings_id` ASC
    ),
    CONSTRAINT `fk_elig_es` FOREIGN KEY (
        `exams_id`,
        `sections_id`,
        `course_offerings_id`
    ) REFERENCES `exam_sections` (
        `exams_id`,
        `sections_id`,
        `course_offerings_id`
    ) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_elig_cm` FOREIGN KEY (
        `users_id`,
        `sections_id`,
        `course_offerings_id`
    ) REFERENCES `course_members` (
        `user_id`,
        `section_id`,
        `course_offerings_id`
    ) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `chk_elig_custom_dates` CHECK (
        `custom_close_at` IS NULL
        OR `custom_open_at` IS NULL
        OR `custom_close_at` > `custom_open_at`
    )
) ENGINE = InnoDB DEFAULT CHARACTER SET = utf8mb4 COLLATE = utf8mb4_general_ci;

-- -----------------------------------------------------
-- Table `exam_eligibility_grants` (สิทธิ์สอบพิเศษ)
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `exam_eligibility_grants` (
    `id_exam_eligibility_grants` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `exam_eligibilities_id` INT UNSIGNED NOT NULL,
    `grant_type` ENUM('normal', 'make_up', 'late', 'legacy') NOT NULL DEFAULT 'normal',
    `status` ENUM('active', 'revoked', 'expired') NOT NULL DEFAULT 'active',
    `open_at` DATETIME NULL DEFAULT NULL,
    `close_at` DATETIME NULL DEFAULT NULL,
    `max_attempts` INT UNSIGNED NULL DEFAULT NULL COMMENT 'จำนวนครั้งสูงสุดที่ใช้สิทธิ์ Grant นี้ได้',
    `grace_period_min` INT UNSIGNED NULL DEFAULT NULL COMMENT 'NULL = ใช้ค่าเริ่มต้นจาก exams',
    `grant_source` ENUM('direct', 'request', 'system') NOT NULL DEFAULT 'system' COMMENT 'แหล่งที่มาของการสร้าง Grant',
    `reason` VARCHAR(255) NULL DEFAULT NULL,
    `approved_by` INT UNSIGNED NULL DEFAULT NULL,
    `approved_at` DATETIME NULL DEFAULT NULL,
    `revoked_by` INT UNSIGNED NULL DEFAULT NULL COMMENT 'ผู้เพิกถอนสิทธิ์',
    `revoked_at` DATETIME NULL DEFAULT NULL COMMENT 'วันเวลาที่เพิกถอนสิทธิ์',
    `revoked_reason` VARCHAR(255) NULL DEFAULT NULL COMMENT 'เหตุผลที่เพิกถอนสิทธิ์',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP COMMENT 'วันเวลาแก้ไข Grant ล่าสุด',
    PRIMARY KEY (`id_exam_eligibility_grants`),
    UNIQUE INDEX `uq_grant_eligibility` (`id_exam_eligibility_grants` ASC, `exam_eligibilities_id` ASC),
    INDEX `idx_grant_eligibility` (`exam_eligibilities_id` ASC, `status` ASC),
    INDEX `idx_grant_approved_by` (`approved_by` ASC),
    INDEX `idx_grant_revoked_by` (`revoked_by` ASC),
    CONSTRAINT `chk_grant_grace_period` CHECK (`grace_period_min` IS NULL OR `grace_period_min` >= 0),
    CONSTRAINT `fk_grant_eligibility` FOREIGN KEY (`exam_eligibilities_id`) REFERENCES `exam_eligibilities` (`id_exam_eligibilities`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_grant_approver` FOREIGN KEY (`approved_by`) REFERENCES `users` (`id_users`) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `fk_grant_revoked_by` FOREIGN KEY (`revoked_by`) REFERENCES `users` (`id_users`) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `chk_grant_dates` CHECK (`close_at` IS NULL OR `open_at` IS NULL OR `close_at` > `open_at`),
    CONSTRAINT `chk_grant_max_attempts` CHECK (`max_attempts` IS NULL OR `max_attempts` >= 1)
) ENGINE = InnoDB DEFAULT CHARACTER SET = utf8mb4 COLLATE = utf8mb4_general_ci;

-- -----------------------------------------------------
-- ตาราง `exam_control_events` สำหรับเก็บข้อมูลเหตุการณ์ควบคุมสอบ
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `exam_control_events` (
    `id_exam_control_events` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `exams_id` INT UNSIGNED NOT NULL,
    `sections_id` INT UNSIGNED NULL DEFAULT NULL COMMENT 'NULL = มีผลกับทุกกลุ่มเรียนในชุดสอบ',
    `event_type` ENUM('force_exit', 'pause', 'resume', 'extend_time', 'close_early') NOT NULL,
    `reason` VARCHAR(255) NOT NULL,
    `affected_attempts_count` INT UNSIGNED NOT NULL DEFAULT 0,
    `triggered_by` INT UNSIGNED NOT NULL,
    `triggered_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id_exam_control_events`),
    UNIQUE INDEX `uq_ece_id_exam` (`id_exam_control_events` ASC, `exams_id` ASC),
    INDEX `idx_ece_exam_sec` (`exams_id` ASC, `sections_id` ASC),
    INDEX `idx_ece_triggered_by` (`triggered_by` ASC),
    CONSTRAINT `fk_ece_exam` FOREIGN KEY (`exams_id`) REFERENCES `exams` (`id_exams`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_ece_exam_section` FOREIGN KEY (`exams_id`, `sections_id`) REFERENCES `exam_sections` (`exams_id`, `sections_id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_ece_triggered_by` FOREIGN KEY (`triggered_by`) REFERENCES `users` (`id_users`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE = InnoDB DEFAULT CHARACTER SET = utf8mb4 COLLATE = utf8mb4_general_ci;

-- -----------------------------------------------------
-- ตาราง `attempts` สำหรับเก็บข้อมูลการทำข้อสอบของนิสิต
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `attempts` (
    `id_attempts` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `exams_id` INT UNSIGNED NOT NULL,
    `users_id` INT UNSIGNED NOT NULL,
    `sections_id` INT UNSIGNED NOT NULL,
    `course_offerings_id` INT UNSIGNED NOT NULL,
    `exam_eligibilities_id` INT UNSIGNED NOT NULL,
    `exam_eligibility_grant_id` INT UNSIGNED NOT NULL,
    `attempt_no` INT UNSIGNED NOT NULL,
    `started_at` DATETIME NOT NULL,
    `delivered_at` DATETIME NULL,
    `deadline_at` DATETIME NOT NULL,
    `submitted_at` DATETIME NULL,
    `status` VARCHAR(45) NOT NULL DEFAULT 'in_progress',
    `score` DECIMAL(7, 2) NULL,
    `max_score` DECIMAL(7, 2) NULL,
    `resume_token_hash` VARCHAR(255) NOT NULL,
    `locked_until` DATETIME NULL,
    `reentry_required` TINYINT(1) NOT NULL DEFAULT 0 COMMENT '1 = หลุด/ออกจากหน้าสอบ ต้องกรอกรหัสปัจจุบันจากผู้คุมสอบก่อนทำต่อ',
    `forced_out_at` DATETIME NULL DEFAULT NULL COMMENT 'เวลาที่ถูกสั่งบังคับออกจากห้องสอบ',
    `control_event_id` INT UNSIGNED NULL DEFAULT NULL COMMENT 'อ้างอิงเหตุการณ์สั่งออกจากห้องสอบ',
    `ip_address` VARCHAR(45) NULL,
    `user_agent` VARCHAR(255) NULL,
    PRIMARY KEY (`id_attempts`),
    INDEX `idx_attempts_control_event` (`control_event_id` ASC, `exams_id` ASC),
    UNIQUE INDEX `uq_exam_user_attempt` (
        `exams_id` ASC,
        `users_id` ASC,
        `attempt_no` ASC
    ),
    UNIQUE INDEX `uq_attempt_id_exam` (
        `id_attempts` ASC,
        `exams_id` ASC
    ),
    UNIQUE INDEX `uq_attempt_id_exam_user` (
        `id_attempts` ASC,
        `exams_id` ASC,
        `users_id` ASC
    ),
    UNIQUE INDEX `uq_resume_token` (`resume_token_hash` ASC),
    CONSTRAINT `fk_attempts_strict_eligibility` FOREIGN KEY (
        `exam_eligibilities_id`,
        `exams_id`,
        `users_id`,
        `sections_id`,
        `course_offerings_id`
    ) REFERENCES `exam_eligibilities` (
        `id_exam_eligibilities`,
        `exams_id`,
        `users_id`,
        `sections_id`,
        `course_offerings_id`
    ) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_attempts_control_event` FOREIGN KEY (`control_event_id`, `exams_id`) REFERENCES `exam_control_events` (`id_exam_control_events`, `exams_id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_attempt_grant` FOREIGN KEY (
        `exam_eligibility_grant_id`,
        `exam_eligibilities_id`
    ) REFERENCES `exam_eligibility_grants` (
        `id_exam_eligibility_grants`,
        `exam_eligibilities_id`
    ) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_attempts_es` FOREIGN KEY (`exams_id`, `sections_id`) REFERENCES `exam_sections` (`exams_id`, `sections_id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_attempts_cm` FOREIGN KEY (
        `users_id`,
        `sections_id`,
        `course_offerings_id`
    ) REFERENCES `course_members` (
        `user_id`,
        `section_id`,
        `course_offerings_id`
    ) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `chk_attempt_no` CHECK (`attempt_no` >= 1),
    CONSTRAINT `chk_attempt_deadline` CHECK (`deadline_at` >= `started_at`),
    CONSTRAINT `chk_attempt_status` CHECK (
        `status` IN (
            'in_progress',
            'submitted',
            'expired',
            'cancelled',
            'auto_submitted'
        )
    ),
    CONSTRAINT `chk_attempt_reentry_req` CHECK (`reentry_required` IN (0, 1)),
    CONSTRAINT `chk_attempt_score_range` CHECK (
        (`score` IS NULL OR `score` >= 0)
        AND (`max_score` IS NULL OR `max_score` >= 0)
        AND (`score` IS NULL OR `max_score` IS NULL OR `score` <= `max_score`)
    )
) ENGINE = InnoDB DEFAULT CHARACTER SET = utf8mb4 COLLATE = utf8mb4_general_ci;

-- -----------------------------------------------------
-- attempt_access_logs (บันทึกการเข้าห้องสอบของนิสิต)
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `attempt_access_logs` (
    `id_attempt_access_logs` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `attempts_id` INT UNSIGNED NOT NULL,
    `exams_id` INT UNSIGNED NOT NULL,
    `exam_access_codes_id` INT UNSIGNED NOT NULL,
    `action_type` ENUM('start', 'resume') NOT NULL COMMENT 'start = เข้าสอบครั้งแรก, resume = กลับเข้าสอบหลังหลุด/ออกหน้าเว็บ',
    `verified_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `ip_address` VARCHAR(45) NULL DEFAULT NULL,
    `user_agent` VARCHAR(255) NULL DEFAULT NULL,
    PRIMARY KEY (`id_attempt_access_logs`),
    INDEX `idx_aal_attempt_time` (`attempts_id` ASC, `verified_at` ASC),
    INDEX `idx_aal_code` (`exam_access_codes_id` ASC),
    CONSTRAINT `fk_aal_attempt_exam` FOREIGN KEY (`attempts_id`, `exams_id`) REFERENCES `attempts` (`id_attempts`, `exams_id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_aal_access_code_exam` FOREIGN KEY (`exam_access_codes_id`, `exams_id`) REFERENCES `exam_access_codes` (`id_exam_access_codes`, `exams_id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE = InnoDB DEFAULT CHARACTER SET = utf8mb4 COLLATE = utf8mb4_general_ci;

-- -----------------------------------------------------
--  `login_attempt_logs` (บันทึกการเข้าสู่ระบบ)
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `login_attempt_logs` (
    `id_login_attempt_logs` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `users_id` INT UNSIGNED NULL DEFAULT NULL COMMENT 'NULL = ไม่พบบัญชีผู้ใช้ในระบบ',
    `identifier` VARCHAR(254) NOT NULL COMMENT 'อีเมลหรือรหัสนิสิตที่ใช้กรอกเข้าสู่ระบบ',
    `exams_id` INT UNSIGNED NULL DEFAULT NULL COMMENT 'ชุดสอบที่กำลังดำเนินการอยู่ (ถ้ามี)',
    `attempts_id` INT UNSIGNED NULL DEFAULT NULL COMMENT 'Attempt ที่เกี่ยวข้อง (ถ้ามี)',
    `login_method` ENUM('password', 'google') NOT NULL DEFAULT 'password',
    `is_success` TINYINT(1) NOT NULL,
    `failure_reason` VARCHAR(100) NULL DEFAULT NULL,
    `ip_address` VARCHAR(45) NULL DEFAULT NULL,
    `user_agent` VARCHAR(255) NULL DEFAULT NULL,
    `attempted_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id_login_attempt_logs`),
    INDEX `idx_lal_user_time` (`users_id` ASC, `attempted_at` ASC),
    INDEX `idx_lal_identifier_time` (`identifier` ASC, `attempted_at` ASC),
    INDEX `idx_lal_exam_user_time` (`exams_id` ASC, `users_id` ASC, `attempted_at` ASC),
    INDEX `idx_lal_attempt` (`attempts_id` ASC),
    CONSTRAINT `fk_lal_user` FOREIGN KEY (`users_id`) REFERENCES `users` (`id_users`) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `fk_lal_exam` FOREIGN KEY (`exams_id`) REFERENCES `exams` (`id_exams`) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `fk_lal_attempt` FOREIGN KEY (`attempts_id`) REFERENCES `attempts` (`id_attempts`) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `chk_lal_is_success` CHECK (`is_success` IN (0, 1))
) ENGINE = InnoDB DEFAULT CHARACTER SET = utf8mb4 COLLATE = utf8mb4_general_ci;

-- -----------------------------------------------------
--  `exam_location_consents` (บันทึกความยินยอมเกี่ยวกับตำแหน่งสอบ)
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `exam_location_consents` (
    `id_exam_location_consents` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `exams_id` INT UNSIGNED NOT NULL,
    `users_id` INT UNSIGNED NOT NULL,
    `attempts_id` INT UNSIGNED NULL DEFAULT NULL,
    `consent_version` VARCHAR(30) NOT NULL DEFAULT 'v1',
    `consent_given` TINYINT(1) NOT NULL,
    `consented_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `revoked_at` DATETIME NULL DEFAULT NULL,
    `ip_address` VARCHAR(45) NULL DEFAULT NULL,
    `user_agent` VARCHAR(255) NULL DEFAULT NULL,
    PRIMARY KEY (`id_exam_location_consents`),
    UNIQUE INDEX `uq_elc_id_user_exam` (`id_exam_location_consents` ASC, `users_id` ASC, `exams_id` ASC),
    INDEX `idx_elc_exam_user` (`exams_id` ASC, `users_id` ASC),
    INDEX `idx_elc_attempt` (`attempts_id` ASC, `exams_id` ASC, `users_id` ASC),
    CONSTRAINT `fk_elc_exam` FOREIGN KEY (`exams_id`) REFERENCES `exams` (`id_exams`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_elc_user` FOREIGN KEY (`users_id`) REFERENCES `users` (`id_users`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_elc_attempt_exam_user` FOREIGN KEY (`attempts_id`, `exams_id`, `users_id`) REFERENCES `attempts` (`id_attempts`, `exams_id`, `users_id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `chk_elc_consent_given` CHECK (`consent_given` IN (0, 1)),
    CONSTRAINT `chk_elc_revoked_at` CHECK (`revoked_at` IS NULL OR `revoked_at` >= `consented_at`)
) ENGINE = InnoDB DEFAULT CHARACTER SET = utf8mb4 COLLATE = utf8mb4_general_ci;

-- table `exam_location_logs` (บันทึกพิกัดตำแหน่งสอบ)
CREATE TABLE IF NOT EXISTS `exam_location_logs` (
    `id_exam_location_logs` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `attempts_id` INT UNSIGNED NOT NULL,
    `exams_id` INT UNSIGNED NOT NULL,
    `users_id` INT UNSIGNED NOT NULL,
    `consent_id` INT UNSIGNED NOT NULL COMMENT 'ต้องมีบันทึกความยินยอมของนิสิตและชุดสอบเดียวกัน',
    `latitude` DECIMAL(10, 7) NOT NULL,
    `longitude` DECIMAL(10, 7) NOT NULL,
    `accuracy_meters` DECIMAL(8, 2) NULL DEFAULT NULL,
    `ip_address` VARCHAR(45) NULL DEFAULT NULL,
    `captured_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id_exam_location_logs`),
    INDEX `idx_ell_attempt_time` (`attempts_id` ASC, `captured_at` ASC),
    INDEX `idx_ell_user_time` (`users_id` ASC, `captured_at` ASC),
    INDEX `idx_ell_consent` (`consent_id` ASC, `users_id` ASC, `exams_id` ASC),
    CONSTRAINT `fk_ell_attempt_exam_user` FOREIGN KEY (`attempts_id`, `exams_id`, `users_id`) REFERENCES `attempts` (`id_attempts`, `exams_id`, `users_id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_ell_consent_user_exam` FOREIGN KEY (`consent_id`, `users_id`, `exams_id`) REFERENCES `exam_location_consents` (`id_exam_location_consents`, `users_id`, `exams_id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `chk_ell_lat` CHECK (`latitude` BETWEEN -90 AND 90),
    CONSTRAINT `chk_ell_lng` CHECK (`longitude` BETWEEN -180 AND 180),
    CONSTRAINT `chk_ell_accuracy` CHECK (`accuracy_meters` IS NULL OR `accuracy_meters` >= 0)
) ENGINE = InnoDB DEFAULT CHARACTER SET = utf8mb4 COLLATE = utf8mb4_general_ci;

-- -----------------------------------------------------
--  `attempt_answers` (คำตอบของนิสิตต่อข้อสอบ)
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `attempt_answers` (
    `id_attempt_answers` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `exam_question_id` INT UNSIGNED NOT NULL,
    `attempts_id` INT UNSIGNED NOT NULL,
    `exams_id` INT UNSIGNED NOT NULL,
    `question_version_id` INT UNSIGNED NOT NULL,
    `version_release_id` INT UNSIGNED NULL DEFAULT NULL COMMENT 'อ้างอิงรอบประกาศเปลี่ยนเวอร์ชันระหว่างสอบ (ถ้ามี)',
    `answer_text` LONGTEXT NULL,
    `answered_at` DATETIME NOT NULL,
    `is_correct` TINYINT(1) NULL,
    `score` DECIMAL(7, 2) NULL,
    `displayed_order` INT UNSIGNED NULL,
    PRIMARY KEY (`id_attempt_answers`),
    INDEX `idx_aa_question_version` (`question_version_id` ASC),
    INDEX `idx_aa_version_release` (`version_release_id` ASC, `exam_question_id` ASC, `question_version_id` ASC),
    UNIQUE INDEX `uq_attempt_question` (
        `attempts_id` ASC,
        `exam_question_id` ASC
    ),
    UNIQUE INDEX `uq_aa_id_version` (
        `id_attempt_answers` ASC,
        `question_version_id` ASC
    ),
    UNIQUE INDEX `uq_aa_id_attempt` (`id_attempt_answers` ASC, `attempts_id` ASC),
    CONSTRAINT `fk_answer_attempt_exam` FOREIGN KEY (`attempts_id`, `exams_id`) REFERENCES `attempts` (`id_attempts`, `exams_id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_answer_question_exam` FOREIGN KEY (
        `exam_question_id`,
        `exams_id`
    ) REFERENCES `exam_questions` (
        `id_exam_questions`,
        `exams_id`
    ) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_aa_question_version` FOREIGN KEY (`question_version_id`) REFERENCES `question_versions` (`id_question_versions`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_aa_version_release` FOREIGN KEY (`version_release_id`, `exam_question_id`, `question_version_id`) REFERENCES `exam_question_version_releases` (`id_exam_question_version_releases`, `exam_question_id`, `new_question_version_id`) ON DELETE RESTRICT ON UPDATE CASCADE,    CONSTRAINT `chk_aa_is_correct` CHECK (`is_correct` IN (0, 1)),
    CONSTRAINT `chk_aa_score` CHECK (
        `score` IS NULL
        OR `score` >= 0
    )
) ENGINE = InnoDB DEFAULT CHARACTER SET = utf8mb4 COLLATE = utf8mb4_general_ci;

-- -----------------------------------------------------
--  table `attempt_answer_drafts` (บันทึกคำตอบร่างของนิสิต)
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `attempt_answer_drafts` (
    `id_attempt_answer_drafts` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `attempt_answers_id` INT UNSIGNED NOT NULL,
    `attempts_id` INT UNSIGNED NOT NULL,
    `draft_no` INT UNSIGNED NOT NULL,
    `answer_text` LONGTEXT NULL,
    `saved_by_action` ENUM('autosave', 'manual_save', 'submit_snapshot') NOT NULL DEFAULT 'autosave',
    `saved_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id_attempt_answer_drafts`),
    UNIQUE INDEX `uq_aad_answer_draft_no` (`attempt_answers_id` ASC, `draft_no` ASC),
    INDEX `idx_aad_attempt_time` (`attempts_id` ASC, `saved_at` ASC),
    CONSTRAINT `fk_aad_answer_attempt` FOREIGN KEY (`attempt_answers_id`, `attempts_id`) REFERENCES `attempt_answers` (`id_attempt_answers`, `attempts_id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `chk_aad_draft_no` CHECK (`draft_no` >= 1)
) ENGINE = InnoDB DEFAULT CHARACTER SET = utf8mb4 COLLATE = utf8mb4_general_ci;

-- -----------------------------------------------------
-- Table `attempt_answer_events` (ประวัติการบันทึกและส่งคำตอบรายข้อทุกครั้ง)
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `attempt_answer_events` (
    `id_attempt_answer_events` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `attempt_answers_id` INT UNSIGNED NOT NULL,
    `attempts_id` INT UNSIGNED NOT NULL,
    `question_version_id` INT UNSIGNED NOT NULL,
    `event_type` ENUM('save', 'autosave', 'update', 'submit') NOT NULL,
    `answer_text_snapshot` LONGTEXT NULL DEFAULT NULL,
    `selected_options_snapshot` JSON NULL DEFAULT NULL COMMENT 'เก็บรายการ question_options_id ที่เลือก ณ เวลานั้น',
    `ip_address` VARCHAR(45) NULL DEFAULT NULL,
    `user_agent` VARCHAR(255) NULL DEFAULT NULL,
    `event_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id_attempt_answer_events`),
    INDEX `idx_aae_answer_time` (`attempt_answers_id` ASC, `event_at` ASC),
    INDEX `idx_aae_attempt_time` (`attempts_id` ASC, `event_at` ASC),
    INDEX `idx_aae_qv` (`question_version_id` ASC),
    CONSTRAINT `fk_aae_answer_attempt` FOREIGN KEY (`attempt_answers_id`, `attempts_id`) REFERENCES `attempt_answers` (`id_attempt_answers`, `attempts_id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_aae_question_version` FOREIGN KEY (`question_version_id`) REFERENCES `question_versions` (`id_question_versions`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE = InnoDB DEFAULT CHARACTER SET = utf8mb4 COLLATE = utf8mb4_general_ci;


-- -----------------------------------------------------
--  `attempt_answer_options` (คำตอบของนิสิตต่อข้อสอบแบบหลายตัวเลือก)
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `attempt_answer_options` (
    `id_attempt_answer_options` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `attempt_answers_id` INT UNSIGNED NOT NULL,
    `question_options_id` INT UNSIGNED NOT NULL,
    `question_version_id` INT UNSIGNED NOT NULL,
    PRIMARY KEY (`id_attempt_answer_options`),
    UNIQUE INDEX `uq_answer_option` (
        `attempt_answers_id` ASC,
        `question_options_id` ASC
    ),
    CONSTRAINT `fk_aao_answer` FOREIGN KEY (
        `attempt_answers_id`,
        `question_version_id`
    ) REFERENCES `attempt_answers` (
        `id_attempt_answers`,
        `question_version_id`
    ) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_aao_option` FOREIGN KEY (
        `question_options_id`,
        `question_version_id`
    ) REFERENCES `question_options` (
        `id_question_options`,
        `question_versions_id`
    ) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE = InnoDB DEFAULT CHARACTER SET = utf8mb4 COLLATE = utf8mb4_general_ci;

-- -----------------------------------------------------
-- Table `score_versions` (เก็บประวัติการแก้ไขคะแนน)
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `score_versions` (
    `id_score_versions` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `attempt_answer_id` INT UNSIGNED NOT NULL,
    `old_score` DECIMAL(7, 2) NULL,
    `new_score` DECIMAL(7, 2) NOT NULL,
    `graded_by` INT UNSIGNED NOT NULL,
    `reason` VARCHAR(255) NULL,
    `created_at` DATETIME NOT NULL,
    PRIMARY KEY (`id_score_versions`),
    INDEX `idx_sv_attempt_answer` (`attempt_answer_id` ASC),
    INDEX `idx_sv_graded_by` (`graded_by` ASC),
    CONSTRAINT `fk_sv_attempt_answer` FOREIGN KEY (`attempt_answer_id`) REFERENCES `attempt_answers` (`id_attempt_answers`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_sv_graded_by` FOREIGN KEY (`graded_by`) REFERENCES `users` (`id_users`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `chk_sv_new_score` CHECK (`new_score` >= 0),
    CONSTRAINT `chk_sv_old_score` CHECK (
        `old_score` IS NULL
        OR `old_score` >= 0
    )
) ENGINE = InnoDB DEFAULT CHARACTER SET = utf8mb4 COLLATE = utf8mb4_general_ci;

-- ---------------------------------------------------------
-- Table `exam_make_up_requests` (ตารางคำร้องขอสอบซ่อม)
-- ---------------------------------------------------------
CREATE TABLE IF NOT EXISTS `exam_make_up_requests` (
    `id_exam_make_up_requests` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `exam_eligibilities_id` INT UNSIGNED NOT NULL,
    `requested_format` ENUM('same', 'child') NOT NULL,
    `requested_child_exam_id` INT UNSIGNED NULL DEFAULT NULL,
    `request_reason` TEXT NOT NULL,
    `evidence_file` VARCHAR(255) NULL DEFAULT NULL,
    `status` ENUM('pending', 'approved', 'rejected', 'cancelled') NOT NULL DEFAULT 'pending',
    `pending_request_key` TINYINT(1) GENERATED ALWAYS AS (
        CASE WHEN `status` = 'pending' THEN 1 ELSE NULL END
    ) STORED COMMENT 'บังคับให้มีคำร้อง pending ได้เพียง 1 รายการต่อสิทธิ์สอบ',
    `reviewed_by` INT UNSIGNED NULL DEFAULT NULL,
    `reviewed_at` DATETIME NULL DEFAULT NULL,
    `review_note` VARCHAR(255) NULL DEFAULT NULL,
    `resulting_eligibility_id` INT UNSIGNED NULL DEFAULT NULL COMMENT 'สิทธิ์ที่ใช้จริงหลังอนุมัติ (รองรับทั้งชุดเดิมและชุดลูก)',
    `resulting_grant_id` INT UNSIGNED NULL DEFAULT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id_exam_make_up_requests`),
    UNIQUE INDEX `uq_mur_single_pending` (`exam_eligibilities_id` ASC, `pending_request_key` ASC),
    INDEX `idx_mur_eligibility_status` (`exam_eligibilities_id` ASC, `status` ASC),
    INDEX `idx_mur_reviewed_by` (`reviewed_by` ASC),
    INDEX `idx_mur_child_exam` (`requested_child_exam_id` ASC),
    INDEX `idx_mur_resulting_grant` (`resulting_grant_id` ASC, `resulting_eligibility_id` ASC),
    CONSTRAINT `fk_mur_eligibility` FOREIGN KEY (`exam_eligibilities_id`) REFERENCES `exam_eligibilities` (`id_exam_eligibilities`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_mur_child_exam` FOREIGN KEY (`requested_child_exam_id`) REFERENCES `exams` (`id_exams`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_mur_reviewer` FOREIGN KEY (`reviewed_by`) REFERENCES `users` (`id_users`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_mur_resulting_grant` FOREIGN KEY (`resulting_grant_id`, `resulting_eligibility_id`) REFERENCES `exam_eligibility_grants` (`id_exam_eligibility_grants`, `exam_eligibilities_id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE = InnoDB DEFAULT CHARACTER SET = utf8mb4 COLLATE = utf8mb4_general_ci;

-- -----------------------------------------------------
--  `grading_assignments` (ตารางมอบหมายงานตรวจข้อสอบ)
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `grading_assignments` (
    `id_grading_assignments` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `attempt_answer_id` INT UNSIGNED NOT NULL,
    `exams_id` INT UNSIGNED NOT NULL,
    `grader_id` INT UNSIGNED NOT NULL,
    `assigned_by` INT UNSIGNED NOT NULL,
    `status` ENUM('assigned', 'in_progress', 'completed', 'reassigned', 'cancelled') NOT NULL DEFAULT 'assigned',
    `active_grader_key` TINYINT(1) GENERATED ALWAYS AS (
        CASE WHEN `status` IN ('assigned', 'in_progress') THEN 1 ELSE NULL END
    ) STORED COMMENT 'ป้องกันการมอบหมายผู้ตรวจคนเดิมซ้ำในข้อที่ยังไม่เสร็จ',
    `assigned_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `started_at` DATETIME NULL DEFAULT NULL,
    `completed_at` DATETIME NULL DEFAULT NULL,
    `note` VARCHAR(255) NULL DEFAULT NULL,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id_grading_assignments`),
    UNIQUE INDEX `uq_ga_id_answer_grader` (`id_grading_assignments` ASC, `attempt_answer_id` ASC, `grader_id` ASC),
    UNIQUE INDEX `uq_ga_active_grader` (`attempt_answer_id` ASC, `grader_id` ASC, `active_grader_key` ASC),
    INDEX `idx_ga_exam_grader_status` (`exams_id` ASC, `grader_id` ASC, `status` ASC),
    INDEX `idx_ga_assigned_by` (`assigned_by` ASC),
    CONSTRAINT `fk_ga_attempt_answer` FOREIGN KEY (`attempt_answer_id`) REFERENCES `attempt_answers` (`id_attempt_answers`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_ga_exam` FOREIGN KEY (`exams_id`) REFERENCES `exams` (`id_exams`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_ga_grader` FOREIGN KEY (`grader_id`) REFERENCES `users` (`id_users`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_ga_assigned_by` FOREIGN KEY (`assigned_by`) REFERENCES `users` (`id_users`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `chk_ga_dates` CHECK (`completed_at` IS NULL OR `started_at` IS NULL OR `completed_at` >= `started_at`)
) ENGINE = InnoDB DEFAULT CHARACTER SET = utf8mb4 COLLATE = utf8mb4_general_ci;

-- -----------------------------------------------------
--  `grading_details` (เก็บรายละเอียดการให้คะแนนต่อเกณฑ์)
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `grading_details` (
    `id_grading_details` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `attempt_answer_id` INT UNSIGNED NOT NULL,
    `grader_id` INT UNSIGNED NOT NULL,
    `grading_assignment_id` INT UNSIGNED NULL DEFAULT NULL COMMENT 'อ้างอิงงานมอบหมายตรวจ (ถ้ามี)',
    `criterion_name` VARCHAR(155) NOT NULL,
    `max_score` DECIMAL(7, 2) NOT NULL,
    `score_given` DECIMAL(7, 2) NOT NULL,
    `comments` LONGTEXT NULL,
    `created_at` DATETIME NOT NULL,
    PRIMARY KEY (`id_grading_details`),
    UNIQUE INDEX `uq_grading_criterion` (
        `attempt_answer_id` ASC,
        `grader_id` ASC,
        `criterion_name` ASC
    ),
    INDEX `idx_gd_grader_id` (`grader_id` ASC),
    INDEX `idx_gd_assignment` (`grading_assignment_id` ASC, `attempt_answer_id` ASC, `grader_id` ASC),
    CONSTRAINT `fk_grading_assignment` FOREIGN KEY (`grading_assignment_id`, `attempt_answer_id`, `grader_id`) REFERENCES `grading_assignments` (`id_grading_assignments`, `attempt_answer_id`, `grader_id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_grading_attempt_answer` FOREIGN KEY (`attempt_answer_id`) REFERENCES `attempt_answers` (`id_attempt_answers`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_grading_grader` FOREIGN KEY (`grader_id`) REFERENCES `users` (`id_users`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `chk_gd_score` CHECK (`score_given` >= 0),
    CONSTRAINT `chk_gd_max_score` CHECK (`score_given` <= `max_score`)
) ENGINE = InnoDB DEFAULT CHARACTER SET = utf8mb4 COLLATE = utf8mb4_general_ci;

-- -----------------------------------------------------
-- Table `announcements` (ประกาศ)
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `announcements` (
    `id_announcements` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `title` VARCHAR(255) NOT NULL,
    `content` TEXT NOT NULL,
    `target_role` VARCHAR(45) NULL,
    `created_by` INT UNSIGNED NOT NULL,
    `created_at` DATETIME NOT NULL,
    PRIMARY KEY (`id_announcements`),
    INDEX `idx_announcements_created_by` (`created_by` ASC),
    CONSTRAINT `fk_announcements_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id_users`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `chk_announcements_role` CHECK (
        `target_role` IN (
            'student',
            'teacher',
            'all',
            'admin'
        )
        OR `target_role` IS NULL
    )
) ENGINE = InnoDB DEFAULT CHARACTER SET = utf8mb4 COLLATE = utf8mb4_general_ci;

-- -----------------------------------------------------
--  `announcement_attachments` (ไฟล์แนบประกาศ)
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `announcement_attachments` (
    `id_announcement_attachments` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `announcements_id` INT UNSIGNED NOT NULL,
    `file_name` VARCHAR(255) NOT NULL,
    `file_url` VARCHAR(255) NOT NULL,
    PRIMARY KEY (`id_announcement_attachments`),
    CONSTRAINT `fk_aa_announcements` FOREIGN KEY (`announcements_id`) REFERENCES `announcements` (`id_announcements`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE = InnoDB DEFAULT CHARACTER SET = utf8mb4 COLLATE = utf8mb4_general_ci;

-- -----------------------------------------------------
-- Table `audit_logs` (บันทึกการกระทำของผู้ใช้)
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `audit_logs` (
    `id_audit_logs` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `users_id` INT UNSIGNED NULL,
    `action` VARCHAR(150) NOT NULL,
    `entity_table` VARCHAR(255) NOT NULL,
    `entity_id` BIGINT UNSIGNED NULL,
    `old_values` JSON NULL,
    `new_values` JSON NULL,
    `reason` TEXT NULL,
    `ip_address` VARCHAR(45) NULL,
    `user_agent` VARCHAR(255) NULL,
    `created_at` DATETIME NOT NULL,
    PRIMARY KEY (`id_audit_logs`),
    INDEX `idx_audit_logs_users_id` (`users_id` ASC),
    CONSTRAINT `fk_audit_logs_users` FOREIGN KEY (`users_id`) REFERENCES `users` (`id_users`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE = InnoDB DEFAULT CHARACTER SET = utf8mb4 COLLATE = utf8mb4_general_ci;

-- -----------------------------------------------------
--  `permissions` (สิทธิ์การเข้าถึงระบบ)
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `permissions` (
    `id_permissions` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `code` VARCHAR(100) NOT NULL,
    `description` VARCHAR(255) NULL,
    PRIMARY KEY (`id_permissions`),
    UNIQUE INDEX `code_UNIQUE` (`code` ASC)
) ENGINE = InnoDB DEFAULT CHARACTER SET = utf8mb4 COLLATE = utf8mb4_general_ci;

-- -----------------------------------------------------
--  `role_permissions` (เชื่อมโยงสิทธิ์กับบทบาท)
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `role_permissions` (
    `roles_id` INT UNSIGNED NOT NULL,
    `permissions_id` INT UNSIGNED NOT NULL,
    PRIMARY KEY (`roles_id`, `permissions_id`),
    CONSTRAINT `fk_role_permissions_roles` FOREIGN KEY (`roles_id`) REFERENCES `roles` (`id_roles`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_role_permissions_permissions` FOREIGN KEY (`permissions_id`) REFERENCES `permissions` (`id_permissions`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE = InnoDB DEFAULT CHARACTER SET = utf8mb4 COLLATE = utf8mb4_general_ci;

-- -----------------------------------------------------
-- Table `learning_contents` (เนื้อหาการเรียน)
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `learning_contents` (
    `id_learning_contents` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `courses_id` INT UNSIGNED NOT NULL,
    `title` VARCHAR(255) NOT NULL,
    `content_type` ENUM(
        'text',
        'file',
        'video',
        'link',
        'mixed'
    ) NOT NULL DEFAULT 'text',
    `content_body` LONGTEXT NULL,
    `source_url` VARCHAR(1000) NULL,
    `description` TEXT NULL,
    `status` ENUM(
        'draft',
        'published',
        'archived'
    ) NOT NULL DEFAULT 'draft',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id_learning_contents`),
    UNIQUE INDEX `uq_learning_content_id_course` (
        `id_learning_contents`,
        `courses_id`
    ),
    INDEX `idx_learning_contents_course` (`courses_id`),
    CONSTRAINT `fk_learning_contents_courses` FOREIGN KEY (`courses_id`) REFERENCES `courses` (`id_courses`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE = InnoDB DEFAULT CHARACTER SET = utf8mb4 COLLATE = utf8mb4_general_ci;

-- -----------------------------------------------------
-- เพิ่มระบบไฟล์แนบหลายไฟล์ในชุดเนื้อหาการเรียน
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `learning_content_attachments` (
    `id_learning_content_attachments` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `learning_content_id` INT UNSIGNED NOT NULL,
    `file_name` VARCHAR(255) NOT NULL,
    `file_url` VARCHAR(1000) NOT NULL,
    `file_type` VARCHAR(50) NULL,
    `sort_order` INT UNSIGNED NOT NULL DEFAULT 1,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE INDEX `uq_lca_content_sort` (
        `learning_content_id`,
        `sort_order`
    ),
    PRIMARY KEY (
        `id_learning_content_attachments`
    ),
    INDEX `idx_lca_content` (`learning_content_id` ASC),
    CONSTRAINT `fk_lca_content` FOREIGN KEY (`learning_content_id`) REFERENCES `learning_contents` (`id_learning_contents`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE = InnoDB DEFAULT CHARACTER SET = utf8mb4 COLLATE = utf8mb4_general_ci;

-- -----------------------------------------------------
-- Table `learning_content_main_concepts` (เชื่อมเนื้อหากับแนวคิดหลัก)
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `learning_content_main_concepts` (
    `learning_content_id` INT UNSIGNED NOT NULL,
    `main_concepts_id` INT UNSIGNED NOT NULL,
    `courses_id` INT UNSIGNED NOT NULL,
    PRIMARY KEY (
        `learning_content_id`,
        `main_concepts_id`
    ),
    INDEX `idx_lcmc_main_course` (
        `main_concepts_id`,
        `courses_id`
    ),
    CONSTRAINT `fk_lcmc_content_course` FOREIGN KEY (
        `learning_content_id`,
        `courses_id`
    ) REFERENCES `learning_contents` (
        `id_learning_contents`,
        `courses_id`
    ) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_lcmc_main_course` FOREIGN KEY (
        `main_concepts_id`,
        `courses_id`
    ) REFERENCES `main_concepts` (
        `id_main_concepts`,
        `courses_id`
    ) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE = InnoDB DEFAULT CHARACTER SET = utf8mb4 COLLATE = utf8mb4_general_ci;

-- -----------------------------------------------------
-- Table `learning_content_sub_concepts` (เชื่อมเนื้อหากับแนวคิดรอง)
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `learning_content_sub_concepts` (
    `learning_content_id` INT UNSIGNED NOT NULL,
    `sub_concepts_id` INT UNSIGNED NOT NULL,
    `courses_id` INT UNSIGNED NOT NULL,
    PRIMARY KEY (
        `learning_content_id`,
        `sub_concepts_id`
    ),
    INDEX `idx_lcsc_sub_course` (
        `sub_concepts_id`,
        `courses_id`
    ),
    CONSTRAINT `fk_lcsc_content_course` FOREIGN KEY (
        `learning_content_id`,
        `courses_id`
    ) REFERENCES `learning_contents` (
        `id_learning_contents`,
        `courses_id`
    ) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_lcsc_sub_course` FOREIGN KEY (
        `sub_concepts_id`,
        `courses_id`
    ) REFERENCES `sub_concepts` (
        `id_sub_concepts`,
        `courses_id`
    ) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE = InnoDB DEFAULT CHARACTER SET = utf8mb4 COLLATE = utf8mb4_general_ci;

-- -----------------------------------------------------
-- Table `question_version_main_concepts` (เชื่อมข้อสอบกับแนวคิดหลักโดยตรง - เผื่อกรณีคำถามกว้างๆ)
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `question_version_main_concepts` (
    `question_version_id` INT UNSIGNED NOT NULL,
    `main_concepts_id` INT UNSIGNED NOT NULL,
    `questions_id` INT UNSIGNED NOT NULL,
    `courses_id` INT UNSIGNED NOT NULL,
    PRIMARY KEY (
        `question_version_id`,
        `main_concepts_id`
    ),
    CONSTRAINT `fk_qvmc_version_question` FOREIGN KEY (
        `question_version_id`,
        `questions_id`
    ) REFERENCES `question_versions` (
        `id_question_versions`,
        `questions_id`
    ) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_qvmc_main_course` FOREIGN KEY (
        `main_concepts_id`,
        `courses_id`
    ) REFERENCES `main_concepts` (
        `id_main_concepts`,
        `courses_id`
    ) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE = InnoDB DEFAULT CHARACTER SET = utf8mb4 COLLATE = utf8mb4_general_ci;

-- -----------------------------------------------------
-- Table `question_version_sub_concepts` (เชื่อมข้อสอบกับแนวคิดรอง)
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `question_version_sub_concepts` (
    `question_version_id` INT UNSIGNED NOT NULL,
    `sub_concepts_id` INT UNSIGNED NOT NULL,
    `questions_id` INT UNSIGNED NOT NULL,
    `courses_id` INT UNSIGNED NOT NULL,
    PRIMARY KEY (
        `question_version_id`,
        `sub_concepts_id`
    ),
    CONSTRAINT `fk_qvsc_version_question` FOREIGN KEY (
        `question_version_id`,
        `questions_id`
    ) REFERENCES `question_versions` (
        `id_question_versions`,
        `questions_id`
    ) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_qvsc_sub_course` FOREIGN KEY (
        `sub_concepts_id`,
        `courses_id`
    ) REFERENCES `sub_concepts` (
        `id_sub_concepts`,
        `courses_id`
    ) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE = InnoDB DEFAULT CHARACTER SET = utf8mb4 COLLATE = utf8mb4_general_ci;

-- -----------------------------------------------------
-- exam_import_jobs (ตารางบันทึกงานนำเข้าข้อสอบ)
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `exam_import_jobs` (
    `id_exam_import_jobs` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `courses_id` INT UNSIGNED NULL DEFAULT NULL COMMENT 'NULL = นำเข้าสู่คลังข้อสอบกลาง',
    `uploaded_by` INT UNSIGNED NOT NULL,
    `original_file_name` VARCHAR(255) NOT NULL,
    `stored_file_path` VARCHAR(500) NOT NULL,
    `status` ENUM('uploaded', 'parsing', 'preview_ready', 'importing', 'completed', 'failed', 'cancelled') NOT NULL DEFAULT 'uploaded',
    `total_items` INT UNSIGNED NOT NULL DEFAULT 0,
    `valid_items` INT UNSIGNED NOT NULL DEFAULT 0,
    `imported_items` INT UNSIGNED NOT NULL DEFAULT 0,
    `error_summary` TEXT NULL DEFAULT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `completed_at` DATETIME NULL DEFAULT NULL,
    PRIMARY KEY (`id_exam_import_jobs`),
    INDEX `idx_eij_course_status` (`courses_id` ASC, `status` ASC),
    INDEX `idx_eij_uploaded_by` (`uploaded_by` ASC),
    CONSTRAINT `fk_eij_course` FOREIGN KEY (`courses_id`) REFERENCES `courses` (`id_courses`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_eij_uploaded_by` FOREIGN KEY (`uploaded_by`) REFERENCES `users` (`id_users`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE = InnoDB DEFAULT CHARACTER SET = utf8mb4 COLLATE = utf8mb4_general_ci;

-- exam_import_items (ตารางบันทึกรายการข้อสอบที่นำเข้า)
CREATE TABLE IF NOT EXISTS `exam_import_items` (
    `id_exam_import_items` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `import_job_id` INT UNSIGNED NOT NULL,
    `item_no` INT UNSIGNED NOT NULL,
    `raw_data` JSON NOT NULL COMMENT 'ข้อมูลดิบที่อ่านได้จากไฟล์ Word',
    `parsed_question_type` VARCHAR(45) NULL DEFAULT NULL,
    `parsed_text` LONGTEXT NULL DEFAULT NULL,
    `parsed_bloom_code` VARCHAR(20) NULL DEFAULT NULL,
    `parsed_concept_payload` JSON NULL DEFAULT NULL COMMENT 'รายการแนวคิดหลัก/รองที่จับคู่ได้ (รองรับ Multi-select)',
    `parsed_scenario_key` VARCHAR(100) NULL DEFAULT NULL COMMENT 'รหัสกลุ่มสถานการณ์กรณีนำเข้าเป็นชุด',
    `validation_status` ENUM('valid', 'warning', 'error') NOT NULL DEFAULT 'valid',
    `validation_errors` JSON NULL DEFAULT NULL,
    `import_status` ENUM('pending', 'imported', 'skipped', 'failed') NOT NULL DEFAULT 'pending',
    `created_question_id` INT UNSIGNED NULL DEFAULT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id_exam_import_items`),
    UNIQUE INDEX `uq_eii_job_item_no` (`import_job_id` ASC, `item_no` ASC),
    INDEX `idx_eii_job_status` (`import_job_id` ASC, `validation_status` ASC, `import_status` ASC),
    INDEX `idx_eii_bloom` (`parsed_bloom_code` ASC),
    INDEX `idx_eii_created_question` (`created_question_id` ASC),
    CONSTRAINT `fk_eii_import_job` FOREIGN KEY (`import_job_id`) REFERENCES `exam_import_jobs` (`id_exam_import_jobs`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_eii_bloom` FOREIGN KEY (`parsed_bloom_code`) REFERENCES `bloom_levels` (`code`) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `fk_eii_created_question` FOREIGN KEY (`created_question_id`) REFERENCES `questions` (`id_questions`) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `chk_eii_item_no` CHECK (`item_no` >= 1)
) ENGINE = InnoDB DEFAULT CHARACTER SET = utf8mb4 COLLATE = utf8mb4_general_ci;


-- -----------------------------------------------------
-- รายละเอียดสถิติการสอบ (exam_statistics) สำหรับเก็บข้อมูลสรุปผลการสอบของแต่ละชุดสอบ
-- -----------------------------------------------------
-- 1. ตารางสถิติภาพรวมของชุดสอบ (exam_statistics)
CREATE TABLE IF NOT EXISTS `exam_statistics` (
    `id_exam_statistics` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `exams_id` INT UNSIGNED NOT NULL,
    `courses_id` INT UNSIGNED NOT NULL,
    `sections_id` INT UNSIGNED NULL DEFAULT NULL,
    `section_scope_key` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '0 = สถิติรวมทุกกลุ่ม (ใช้คุม Unique แทน NULL)',    `run_no` INT UNSIGNED NOT NULL DEFAULT 1 COMMENT 'ลำดับรอบการคำนวณ',
    `calculation_status` ENUM('pending', 'processing', 'completed', 'failed') NOT NULL DEFAULT 'pending',
    `calculation_type` ENUM('initial', 'recalculation') NOT NULL DEFAULT 'initial',
    `attempt_selection_mode` ENUM(
        'first',
        'latest',
        'highest_score',
        'all',
        'manual'
    ) NOT NULL DEFAULT 'manual',
    `attempt_no_filter` INT UNSIGNED NULL DEFAULT NULL COMMENT 'NULL = ทุกครั้ง/ดีที่สุด, 1 = เฉพาะสอบครั้งที่ 1',
    `include_makeup_attempts` TINYINT(1) NOT NULL DEFAULT 1,
    `r_method` ENUM(
        'upper_lower',
        'point_biserial',
        'both'
    ) NOT NULL DEFAULT 'both',
    `data_cutoff_at` DATETIME NULL DEFAULT NULL COMMENT 'เวลาสิ้นสุดการรวบรวมข้อมูลของรอบนี้',
    `formula_version` VARCHAR(30) NOT NULL DEFAULT 'v1',
    `delta_formula` VARCHAR(40) NULL DEFAULT NULL,
    `sample_size` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'จำนวนผู้เข้าสอบ (N)',
    `max_score_val` DECIMAL(8, 2) NULL DEFAULT NULL COMMENT 'คะแนนสูงสุด',
    `min_score_val` DECIMAL(8, 2) NULL DEFAULT NULL COMMENT 'คะแนนต่ำสุด',
    `mean_score` DECIMAL(8, 4) NULL DEFAULT NULL COMMENT 'คะแนนเฉลี่ย',
    `sd_score` DECIMAL(8, 4) NULL DEFAULT NULL COMMENT 'ส่วนเบี่ยงเบนมาตรฐาน (SD)',
    `reliability_kr20` DECIMAL(8, 4) NULL DEFAULT NULL COMMENT 'ค่าความเชื่อมั่นแบบทดสอบ (KR-20)',
    `reliability_cronbach_alpha` DECIMAL(8, 4) NULL DEFAULT NULL COMMENT 'ค่าความเชื่อมั่นแบบทดสอบ (Cronbach Alpha)',
    `pass_percent` DECIMAL(5, 2) NULL DEFAULT NULL COMMENT 'เกณฑ์ผ่านในหน่วยเปอร์เซ็นต์',
    `is_current` TINYINT(1) NOT NULL DEFAULT 0,
    `current_run_key` TINYINT(1) GENERATED ALWAYS AS (
        CASE
            WHEN `is_current` = 1 THEN 1
            ELSE NULL
        END
    ) STORED COMMENT 'บังคับให้มี is_current = 1 ได้เพียง 1 รอบต่อชุดสอบ/กลุ่มเรียน',
    `calculated_by` INT UNSIGNED NULL DEFAULT NULL,
    `calculation_started_at` DATETIME NULL DEFAULT NULL COMMENT 'เวลาที่เริ่มคำนวณ',
    `completed_at` DATETIME NULL DEFAULT NULL COMMENT 'เวลาที่คำนวณเสร็จสิ้น',
    `calculated_at` DATETIME NOT NULL COMMENT 'วันเวลาที่คำนวณล่าสุด',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id_exam_statistics`),
    UNIQUE INDEX `uq_stats_id_exam` (
        `id_exam_statistics` ASC,
        `exams_id` ASC
    ),
    UNIQUE INDEX `uq_stats_id_exam_course` (
        `id_exam_statistics` ASC,
        `exams_id` ASC,
        `courses_id` ASC
    ),
    UNIQUE INDEX `uq_stats_exam_section_run` (
        `exams_id` ASC,
        `section_scope_key` ASC,
        `run_no` ASC
    ),
    UNIQUE INDEX `uq_stats_current_run` (
        `exams_id` ASC,
        `section_scope_key` ASC,
        `current_run_key` ASC
    ),
    INDEX `idx_es_exam_sec` (
        `exams_id` ASC,
        `sections_id` ASC
    ),
    INDEX `idx_es_calculated_by` (`calculated_by` ASC),
    CONSTRAINT `fk_exam_stats_exam_course` FOREIGN KEY (`exams_id`, `courses_id`) REFERENCES `exams` (`id_exams`, `courses_id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_exam_stats_exam_section` FOREIGN KEY (`exams_id`, `sections_id`) REFERENCES `exam_sections` (`exams_id`, `sections_id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_exam_stats_user` FOREIGN KEY (`calculated_by`) REFERENCES `users` (`id_users`) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `chk_es_is_current` CHECK (`is_current` IN (0, 1)),
    CONSTRAINT `chk_es_include_makeup` CHECK (
        `include_makeup_attempts` IN (0, 1)
    ),
    CONSTRAINT `chk_es_pass_percent` CHECK (
        `pass_percent` IS NULL
        OR (
            `pass_percent` >= 0
            AND `pass_percent` <= 100
        )
    )
) ENGINE = InnoDB DEFAULT CHARACTER SET = utf8mb4 COLLATE = utf8mb4_general_ci;

-- 2. ตารางเก็บรายการ Attempt ที่ถูกนำมาคำนวณในแต่ละรอบ (exam_statistics_attempts)
CREATE TABLE IF NOT EXISTS `exam_statistics_attempts` (
    `exam_statistics_id` INT UNSIGNED NOT NULL,
    `exams_id` INT UNSIGNED NOT NULL,
    `courses_id` INT UNSIGNED NOT NULL,
    `attempts_id` INT UNSIGNED NOT NULL,
    `users_id` INT UNSIGNED NOT NULL,
    `selected_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (
        `exam_statistics_id`,
        `attempts_id`
    ),
    UNIQUE INDEX `uq_stats_attempt_user_course` (
        `exam_statistics_id` ASC,
        `attempts_id` ASC,
        `users_id` ASC,
        `courses_id` ASC
    ),
    INDEX `idx_stats_attempt_user` (
        `exam_statistics_id` ASC,
        `users_id` ASC
    ),
    CONSTRAINT `fk_esa_statistics` FOREIGN KEY (
        `exam_statistics_id`,
        `exams_id`,
        `courses_id`
    ) REFERENCES `exam_statistics` (
        `id_exam_statistics`,
        `exams_id`,
        `courses_id`
    ) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_esa_attempt` FOREIGN KEY (
        `attempts_id`,
        `exams_id`,
        `users_id`
    ) REFERENCES `attempts` (
        `id_attempts`,
        `exams_id`,
        `users_id`
    ) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE = InnoDB DEFAULT CHARACTER SET = utf8mb4 COLLATE = utf8mb4_general_ci;

-- 3. ตารางสถิติวิเคราะห์ข้อสอบรายข้อ (exam_item_statistics)
CREATE TABLE IF NOT EXISTS `exam_item_statistics` (
    `id_exam_item_statistics` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `exam_statistics_id` INT UNSIGNED NOT NULL,
    `exams_id` INT UNSIGNED NOT NULL,
    `exam_question_id` INT UNSIGNED NOT NULL,
    `question_version_id` INT UNSIGNED NOT NULL,
    `bloom_levels_code` VARCHAR(20) NOT NULL COMMENT 'ระดับ Bloom ที่ใช้วิเคราะห์ในรอบนี้',
    `correct_count` INT UNSIGNED NOT NULL DEFAULT 0,
    `wrong_count` INT UNSIGNED NOT NULL DEFAULT 0,
    `answered_count` INT UNSIGNED NOT NULL DEFAULT 0,
    `omitted_count` INT UNSIGNED NOT NULL DEFAULT 0,
    `high_group_n` INT UNSIGNED NOT NULL DEFAULT 0,
    `low_group_n` INT UNSIGNED NOT NULL DEFAULT 0,
    `p_value` DECIMAL(6, 4) NULL DEFAULT NULL COMMENT 'ค่าความยากง่าย (p)',
    `q_value` DECIMAL(6, 4) NULL DEFAULT NULL COMMENT '1 - p',
    `r_value` DECIMAL(6, 4) NULL DEFAULT NULL COMMENT 'ค่าอำนาจจำแนกหลัก (Legacy/Primary)',
    `r_upper_lower` DECIMAL(8, 4) NULL DEFAULT NULL COMMENT 'ค่าอำนาจจำแนกแบบ Upper-Lower',
    `r_point_biserial` DECIMAL(8, 4) NULL DEFAULT NULL COMMENT 'ค่าอำนาจจำแนกแบบ Point-Biserial',
    `delta_value` DECIMAL(6, 2) NULL DEFAULT NULL COMMENT 'ค่าเดลต้า (ETS Delta)',
    PRIMARY KEY (`id_exam_item_statistics`),
    UNIQUE INDEX `uq_eis_stat_question` (
        `exam_statistics_id` ASC,
        `exam_question_id` ASC
    ),
    UNIQUE INDEX `uq_eis_id_version` (
        `id_exam_item_statistics` ASC,
        `question_version_id` ASC
    ),
    INDEX `idx_eis_bloom` (`bloom_levels_code` ASC),
    CONSTRAINT `fk_eis_stat_exam` FOREIGN KEY (
        `exam_statistics_id`,
        `exams_id`
    ) REFERENCES `exam_statistics` (
        `id_exam_statistics`,
        `exams_id`
    ) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_eis_eq_exam_version` FOREIGN KEY (
        `exam_question_id`,
        `exams_id`,
        `question_version_id`
    ) REFERENCES `exam_questions` (
        `id_exam_questions`,
        `exams_id`,
        `question_version_id`
    ) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_eis_bloom` FOREIGN KEY (`bloom_levels_code`) REFERENCES `bloom_levels` (`code`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE = InnoDB DEFAULT CHARACTER SET = utf8mb4 COLLATE = utf8mb4_general_ci;

-- 4. ตารางสถิติวิเคราะห์ตัวเลือก / ตัวลวง (exam_option_statistics)
CREATE TABLE IF NOT EXISTS `exam_option_statistics` (
    `id_exam_option_statistics` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `exam_item_statistics_id` INT UNSIGNED NOT NULL,
    `question_version_id` INT UNSIGNED NOT NULL,
    `question_options_id` INT UNSIGNED NOT NULL,
    `selected_count` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'จำนวนคนที่เลือกตัวเลือกนี้',
    `option_p_value` DECIMAL(6, 4) NULL DEFAULT NULL COMMENT 'สัดส่วนผู้เลือกตัวเลือกนี้',
    `high_group_count` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'จำนวนผู้เลือกในกลุ่มสูง',
    `low_group_count` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'จำนวนผู้เลือกในกลุ่มต่ำ',
    `high_group_rate` DECIMAL(6, 4) NULL DEFAULT NULL COMMENT 'สัดส่วนผู้เลือกตัวเลือกในกลุ่มสูง',
    `low_group_rate` DECIMAL(6, 4) NULL DEFAULT NULL COMMENT 'สัดส่วนผู้เลือกตัวเลือกในกลุ่มต่ำ',
    PRIMARY KEY (`id_exam_option_statistics`),
    UNIQUE INDEX `uq_eos_item_option` (
        `exam_item_statistics_id` ASC,
        `question_options_id` ASC
    ),
    CONSTRAINT `fk_eos_item_version` FOREIGN KEY (
        `exam_item_statistics_id`,
        `question_version_id`
    ) REFERENCES `exam_item_statistics` (
        `id_exam_item_statistics`,
        `question_version_id`
    ) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_eos_option_version` FOREIGN KEY (
        `question_options_id`,
        `question_version_id`
    ) REFERENCES `question_options` (
        `id_question_options`,
        `question_versions_id`
    ) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE = InnoDB DEFAULT CHARACTER SET = utf8mb4 COLLATE = utf8mb4_general_ci;

-- 5. ตารางเกณฑ์แบ่งกลุ่มศักยภาพของแต่ละรอบคำนวณ (exam_performance_groups)
CREATE TABLE IF NOT EXISTS `exam_performance_groups` (
    `id_exam_performance_groups` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `exam_statistics_id` INT UNSIGNED NOT NULL,
    `group_code` VARCHAR(30) NOT NULL,
    `group_name` VARCHAR(100) NOT NULL,
    `min_percent` DECIMAL(5, 2) NOT NULL,
    `max_percent` DECIMAL(5, 2) NOT NULL,
    `sort_order` INT UNSIGNED NOT NULL,
    PRIMARY KEY (`id_exam_performance_groups`),
    UNIQUE INDEX `uq_group_code` (
        `exam_statistics_id` ASC,
        `group_code` ASC
    ),
    UNIQUE INDEX `uq_group_id_run` (
        `id_exam_performance_groups` ASC,
        `exam_statistics_id` ASC
    ),
    CONSTRAINT `fk_epg_statistics` FOREIGN KEY (`exam_statistics_id`) REFERENCES `exam_statistics` (`id_exam_statistics`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `chk_epg_range` CHECK (
        `min_percent` >= 0
        AND `max_percent` <= 100
        AND `max_percent` > `min_percent`
    )
) ENGINE = InnoDB DEFAULT CHARACTER SET = utf8mb4 COLLATE = utf8mb4_general_ci;

-- 6. ตารางผลสอบรายบุคคล ลำดับที่ และกลุ่มศักยภาพ (exam_student_statistics)
CREATE TABLE IF NOT EXISTS `exam_student_statistics` (
    `id_exam_student_statistics` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `exam_statistics_id` INT UNSIGNED NOT NULL,
    `courses_id` INT UNSIGNED NOT NULL,
    `attempts_id` INT UNSIGNED NOT NULL,
    `users_id` INT UNSIGNED NOT NULL,
    `score` DECIMAL(8, 2) NOT NULL,
    `max_score` DECIMAL(8, 2) NOT NULL,
    `score_percent` DECIMAL(6, 2) NULL DEFAULT NULL,
    `rank_position` INT UNSIGNED NULL DEFAULT NULL,
    `is_passed` TINYINT(1) NULL DEFAULT NULL,
    `performance_group_id` INT UNSIGNED NULL DEFAULT NULL,
    PRIMARY KEY (`id_exam_student_statistics`),
    UNIQUE INDEX `uq_student_run_attempt` (
        `exam_statistics_id` ASC,
        `attempts_id` ASC
    ),
    UNIQUE INDEX `uq_ess_id_course` (
        `id_exam_student_statistics` ASC,
        `courses_id` ASC
    ),
    INDEX `idx_student_rank` (
        `exam_statistics_id` ASC,
        `rank_position` ASC
    ),
    INDEX `idx_student_pass` (
        `exam_statistics_id` ASC,
        `is_passed` ASC
    ),
    CONSTRAINT `fk_ess_selected_attempt` FOREIGN KEY (
        `exam_statistics_id`,
        `attempts_id`,
        `users_id`,
        `courses_id`
    ) REFERENCES `exam_statistics_attempts` (
        `exam_statistics_id`,
        `attempts_id`,
        `users_id`,
        `courses_id`
    ) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_ess_performance_group` FOREIGN KEY (
        `performance_group_id`,
        `exam_statistics_id`
    ) REFERENCES `exam_performance_groups` (
        `id_exam_performance_groups`,
        `exam_statistics_id`
    ) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `chk_ess_score` CHECK (
        `score` >= 0
        AND `max_score` >= 0
        AND `score` <= `max_score`
    ),
    CONSTRAINT `chk_ess_percent` CHECK (
        `score_percent` IS NULL
        OR `score_percent` BETWEEN 0 AND 100
    ),
    CONSTRAINT `chk_ess_passed` CHECK (
        `is_passed` IS NULL
        OR `is_passed` IN (0, 1)
    )
) ENGINE = InnoDB DEFAULT CHARACTER SET = utf8mb4 COLLATE = utf8mb4_general_ci;

-- 7. ตารางผลสอบรายบุคคลตามแนวคิดหลักและแนวคิดรอง (exam_student_concept_statistics)
CREATE TABLE IF NOT EXISTS `exam_student_concept_statistics` (
    `id_exam_student_concept_statistics` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `exam_student_statistics_id` INT UNSIGNED NOT NULL,
    `courses_id` INT UNSIGNED NOT NULL,
    `concept_type` ENUM('main', 'sub') NOT NULL,
    `main_concepts_id` INT UNSIGNED NULL DEFAULT NULL,
    `sub_concepts_id` INT UNSIGNED NULL DEFAULT NULL,
    `question_count` INT UNSIGNED NOT NULL DEFAULT 0,
    `correct_count` INT UNSIGNED NOT NULL DEFAULT 0,
    `score` DECIMAL(8, 2) NOT NULL DEFAULT 0,
    `max_score` DECIMAL(8, 2) NOT NULL DEFAULT 0,
    `score_percent` DECIMAL(6, 2) NULL DEFAULT NULL,
    `is_passed` TINYINT(1) NULL DEFAULT NULL,
    PRIMARY KEY (
        `id_exam_student_concept_statistics`
    ),
    UNIQUE INDEX `uq_student_concept_main` (
        `exam_student_statistics_id` ASC,
        `main_concepts_id` ASC
    ),
    UNIQUE INDEX `uq_student_concept_sub` (
        `exam_student_statistics_id` ASC,
        `sub_concepts_id` ASC
    ),
    CONSTRAINT `fk_escs_student_course` FOREIGN KEY (
        `exam_student_statistics_id`,
        `courses_id`
    ) REFERENCES `exam_student_statistics` (
        `id_exam_student_statistics`,
        `courses_id`
    ) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_escs_main_course` FOREIGN KEY (
        `main_concepts_id`,
        `courses_id`
    ) REFERENCES `main_concepts` (
        `id_main_concepts`,
        `courses_id`
    ) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_escs_sub_course` FOREIGN KEY (
        `sub_concepts_id`,
        `courses_id`
    ) REFERENCES `sub_concepts` (
        `id_sub_concepts`,
        `courses_id`
    ) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `chk_escs_score` CHECK (
        `score` >= 0
        AND `max_score` >= 0
        AND `score` <= `max_score`
    )
) ENGINE = InnoDB DEFAULT CHARACTER SET = utf8mb4 COLLATE = utf8mb4_general_ci;

-- ---------------------------------------------------------
-- exam_student_bloom_statistics (ตารางผลสอบรายบุคคลตามระดับ Bloom)
-- ---------------------------------------------------------
CREATE TABLE IF NOT EXISTS `exam_student_bloom_statistics` (
    `id_exam_student_bloom_statistics` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `exam_student_statistics_id` INT UNSIGNED NOT NULL,
    `bloom_levels_code` VARCHAR(20) NOT NULL,
    `question_count` INT UNSIGNED NOT NULL DEFAULT 0,
    `correct_count` INT UNSIGNED NOT NULL DEFAULT 0,
    `score` DECIMAL(8, 2) NOT NULL DEFAULT 0,
    `max_score` DECIMAL(8, 2) NOT NULL DEFAULT 0,
    `score_percent` DECIMAL(6, 2) NULL DEFAULT NULL,
    `is_passed` TINYINT(1) NULL DEFAULT NULL,
    PRIMARY KEY (`id_exam_student_bloom_statistics`),
    UNIQUE INDEX `uq_esbs_student_bloom` (`exam_student_statistics_id` ASC, `bloom_levels_code` ASC),
    INDEX `idx_esbs_bloom` (`bloom_levels_code` ASC),
    CONSTRAINT `fk_esbs_student` FOREIGN KEY (`exam_student_statistics_id`) REFERENCES `exam_student_statistics` (`id_exam_student_statistics`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_esbs_bloom` FOREIGN KEY (`bloom_levels_code`) REFERENCES `bloom_levels` (`code`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `chk_esbs_score` CHECK (`score` >= 0 AND `max_score` >= 0 AND `score` <= `max_score`),
    CONSTRAINT `chk_esbs_percent` CHECK (`score_percent` IS NULL OR `score_percent` BETWEEN 0 AND 100),
    CONSTRAINT `chk_esbs_passed` CHECK (`is_passed` IS NULL OR `is_passed` IN (0, 1))
) ENGINE = InnoDB DEFAULT CHARACTER SET = utf8mb4 COLLATE = utf8mb4_general_ci;


-- ---------------------------------------------------------
-- Trigger 1 : ตรวจสอบสิทธิ์และจำนวนครั้งต่อ Grant
-- ---------------------------------------------------------
DELIMITER $$

DROP TRIGGER IF EXISTS `trg_attempts_validate_eligibility`$$
CREATE TRIGGER `trg_attempts_validate_eligibility`
BEFORE INSERT ON `attempts`
FOR EACH ROW
BEGIN
    DECLARE v_elig_status VARCHAR(20);
    DECLARE v_custom_open DATETIME;
    DECLARE v_custom_close DATETIME;
    DECLARE v_grant_status VARCHAR(20);
    DECLARE v_grant_open DATETIME;
    DECLARE v_grant_close DATETIME;
    DECLARE v_grant_max_attempts INT UNSIGNED;
    DECLARE v_exam_open DATETIME;
    DECLARE v_exam_close DATETIME;
    DECLARE v_exam_max_attempts INT UNSIGNED;
    DECLARE v_effective_open DATETIME;
    DECLARE v_effective_close DATETIME;
    DECLARE v_grant_used_attempts INT UNSIGNED DEFAULT 0;

    -- 1. ตรวจสอบสิทธิ์หลัก
    SELECT `status`, `custom_open_at`, `custom_close_at`
    INTO v_elig_status, v_custom_open, v_custom_close
    FROM `exam_eligibilities`
    WHERE `id_exam_eligibilities` = NEW.`exam_eligibilities_id`;

    IF v_elig_status IS NULL OR v_elig_status <> 'eligible' THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'Cannot create attempt: exam eligibility is not active';
    END IF;

    -- 2. ตรวจสอบ Grant
    SELECT `status`, `open_at`, `close_at`, `max_attempts`
    INTO v_grant_status, v_grant_open, v_grant_close, v_grant_max_attempts
    FROM `exam_eligibility_grants`
    WHERE `id_exam_eligibility_grants` = NEW.`exam_eligibility_grant_id`
      AND `exam_eligibilities_id` = NEW.`exam_eligibilities_id`;

    IF v_grant_status IS NULL OR v_grant_status <> 'active' THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'Cannot create attempt: eligibility grant is not active';
    END IF;

    -- 3. ตรวจสอบช่วงเวลาเปิด-ปิดสอบ (ลำดับความสำคัญ: Grant -> Custom Eligibility -> Exam)
    SELECT `open_at`, `close_at`, `max_attempts`
    INTO v_exam_open, v_exam_close, v_exam_max_attempts
    FROM `exams`
    WHERE `id_exams` = NEW.`exams_id`;

    SET v_effective_open = COALESCE(v_grant_open, v_custom_open, v_exam_open);
    SET v_effective_close = COALESCE(v_grant_close, v_custom_close, v_exam_close);

    IF NEW.`started_at` < v_effective_open OR NEW.`started_at` > v_effective_close THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'Cannot create attempt: outside allowed exam time window';
    END IF;

    -- 4. นับจำนวน Attempt และตรวจโควตา
    SELECT COUNT(*)
    INTO v_grant_used_attempts
    FROM `attempts`
    WHERE `exam_eligibility_grant_id` = NEW.`exam_eligibility_grant_id`;

    IF v_grant_max_attempts IS NOT NULL THEN
        IF v_grant_used_attempts >= v_grant_max_attempts THEN
            SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'Cannot create attempt: exceeds grant max_attempts';
        END IF;
    ELSE
        IF NEW.`attempt_no` > v_exam_max_attempts THEN
            SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'Cannot create attempt: exceeds exam max_attempts';
        END IF;
    END IF;
END$$




-- ---------------------------------------------------------
-- Trigger 1.5 : ตั้งค่า section_scope_key สำหรับ exam_access_codes
-- ---------------------------------------------------------
DROP TRIGGER IF EXISTS `trg_eac_set_scope_bi`$$
CREATE TRIGGER `trg_eac_set_scope_bi` BEFORE INSERT ON `exam_access_codes`
FOR EACH ROW SET NEW.`section_scope_key` = COALESCE(NEW.`sections_id`, 0)$$

DROP TRIGGER IF EXISTS `trg_eac_set_scope_bu`$$
CREATE TRIGGER `trg_eac_set_scope_bu` BEFORE UPDATE ON `exam_access_codes`
FOR EACH ROW SET NEW.`section_scope_key` = COALESCE(NEW.`sections_id`, 0)$$

DROP TRIGGER IF EXISTS `trg_es_set_scope_bi`$$
CREATE TRIGGER `trg_es_set_scope_bi` BEFORE INSERT ON `exam_statistics`
FOR EACH ROW SET NEW.`section_scope_key` = COALESCE(NEW.`sections_id`, 0)$$

DROP TRIGGER IF EXISTS `trg_es_set_scope_bu`$$
CREATE TRIGGER `trg_es_set_scope_bu` BEFORE UPDATE ON `exam_statistics`
FOR EACH ROW SET NEW.`section_scope_key` = COALESCE(NEW.`sections_id`, 0)$$

-- ---------------------------------------------------------
-- Trigger 1.6 : ตรวจสอบความแตกต่างของ old_question_version_id และ new_question_version_id ใน exam_question_version_releases
-- ---------------------------------------------------------
DROP TRIGGER IF EXISTS `trg_eqvr_diff_ver_bi`$$
CREATE TRIGGER `trg_eqvr_diff_ver_bi`
BEFORE INSERT ON `exam_question_version_releases`
FOR EACH ROW
BEGIN
    IF NEW.`old_question_version_id` IS NOT NULL
       AND NEW.`old_question_version_id` = NEW.`new_question_version_id` THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'Cannot release: old_question_version_id and new_question_version_id must be different';
    END IF;
END$$

DROP TRIGGER IF EXISTS `trg_eqvr_diff_ver_bu`$$
CREATE TRIGGER `trg_eqvr_diff_ver_bu`
BEFORE UPDATE ON `exam_question_version_releases`
FOR EACH ROW
BEGIN
    IF NEW.`old_question_version_id` IS NOT NULL
       AND NEW.`old_question_version_id` = NEW.`new_question_version_id` THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'Cannot release: old_question_version_id and new_question_version_id must be different';
    END IF;
END$$

-- ---------------------------------------------------------
-- Trigger 1.7 : ตรวจสอบว่าหากระบุ chapters_id ต้องระบุ courses_id เสมอ
-- ---------------------------------------------------------
DROP TRIGGER IF EXISTS `trg_questions_chap_requires_course_bi`$$
CREATE TRIGGER `trg_questions_chap_requires_course_bi`
BEFORE INSERT ON `questions`
FOR EACH ROW
BEGIN
    IF NEW.`chapters_id` IS NOT NULL AND NEW.`courses_id` IS NULL THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'Cannot insert question: courses_id is required when chapters_id is specified';
    END IF;
END$$

DROP TRIGGER IF EXISTS `trg_questions_chap_requires_course_bu`$$
CREATE TRIGGER `trg_questions_chap_requires_course_bu`
BEFORE UPDATE ON `questions`
FOR EACH ROW
BEGIN
    IF NEW.`chapters_id` IS NOT NULL AND NEW.`courses_id` IS NULL THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'Cannot update question: courses_id is required when chapters_id is specified';
    END IF;
END$$

-- ---------------------------------------------------------
-- Trigger 1.8 : ตรวจสอบความสอดคล้องของ requested_format และ requested_child_exam_id
-- ---------------------------------------------------------
DROP TRIGGER IF EXISTS `trg_mur_format_exam_bi`$$
CREATE TRIGGER `trg_mur_format_exam_bi`
BEFORE INSERT ON `exam_make_up_requests`
FOR EACH ROW
BEGIN
    IF (NEW.`requested_format` = 'same' AND NEW.`requested_child_exam_id` IS NOT NULL)
       OR (NEW.`requested_format` = 'child' AND NEW.`requested_child_exam_id` IS NULL) THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'Invalid requested_child_exam_id for the selected requested_format';
    END IF;
END$$

DROP TRIGGER IF EXISTS `trg_mur_format_exam_bu`$$
CREATE TRIGGER `trg_mur_format_exam_bu`
BEFORE UPDATE ON `exam_make_up_requests`
FOR EACH ROW
BEGIN
    IF (NEW.`requested_format` = 'same' AND NEW.`requested_child_exam_id` IS NOT NULL)
       OR (NEW.`requested_format` = 'child' AND NEW.`requested_child_exam_id` IS NULL) THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'Invalid requested_child_exam_id for the selected requested_format';
    END IF;
END$$

-- ---------------------------------------------------------
-- Trigger 1.9 : ตรวจสอบความสอดคล้องของ status กับข้อมูลการพิจารณาคำร้องสอบซ่อม
-- ---------------------------------------------------------
DROP TRIGGER IF EXISTS `trg_mur_review_status_bi`$$
CREATE TRIGGER `trg_mur_review_status_bi`
BEFORE INSERT ON `exam_make_up_requests`
FOR EACH ROW
BEGIN
    IF (NEW.`status` = 'pending' AND (NEW.`reviewed_at` IS NOT NULL OR NEW.`resulting_grant_id` IS NOT NULL))
       OR (NEW.`status` = 'approved' AND (NEW.`reviewed_at` IS NULL OR NEW.`reviewed_by` IS NULL OR NEW.`resulting_grant_id` IS NULL OR NEW.`resulting_eligibility_id` IS NULL))
       OR (NEW.`status` = 'rejected' AND (NEW.`reviewed_at` IS NULL OR NEW.`reviewed_by` IS NULL OR NEW.`resulting_grant_id` IS NOT NULL))
       OR (NEW.`status` = 'cancelled' AND NEW.`resulting_grant_id` IS NOT NULL) THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'Invalid review fields for the given exam_make_up_requests status';
    END IF;
END$$

DROP TRIGGER IF EXISTS `trg_mur_review_status_bu`$$
CREATE TRIGGER `trg_mur_review_status_bu`
BEFORE UPDATE ON `exam_make_up_requests`
FOR EACH ROW
BEGIN
    IF (NEW.`status` = 'pending' AND (NEW.`reviewed_at` IS NOT NULL OR NEW.`resulting_grant_id` IS NOT NULL))
       OR (NEW.`status` = 'approved' AND (NEW.`reviewed_at` IS NULL OR NEW.`reviewed_by` IS NULL OR NEW.`resulting_grant_id` IS NULL OR NEW.`resulting_eligibility_id` IS NULL))
       OR (NEW.`status` = 'rejected' AND (NEW.`reviewed_at` IS NULL OR NEW.`reviewed_by` IS NULL OR NEW.`resulting_grant_id` IS NOT NULL))
       OR (NEW.`status` = 'cancelled' AND NEW.`resulting_grant_id` IS NOT NULL) THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'Invalid review fields for the given exam_make_up_requests status';
    END IF;
END$$

-- ---------------------------------------------------------
-- Trigger 1.10 : ตรวจสอบความสอดคล้องของ concept_type กับ main_concepts_id / sub_concepts_id
-- ---------------------------------------------------------
DROP TRIGGER IF EXISTS `trg_escs_concept_type_bi`$$
CREATE TRIGGER `trg_escs_concept_type_bi`
BEFORE INSERT ON `exam_student_concept_statistics`
FOR EACH ROW
BEGIN
    IF NOT (
        (NEW.`concept_type` = 'main' AND NEW.`main_concepts_id` IS NOT NULL AND NEW.`sub_concepts_id` IS NULL)
        OR (NEW.`concept_type` = 'sub' AND NEW.`sub_concepts_id` IS NOT NULL AND NEW.`main_concepts_id` IS NULL)
    ) THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'Invalid main_concepts_id / sub_concepts_id for the given concept_type';
    END IF;
END$$

DROP TRIGGER IF EXISTS `trg_escs_concept_type_bu`$$
CREATE TRIGGER `trg_escs_concept_type_bu`
BEFORE UPDATE ON `exam_student_concept_statistics`
FOR EACH ROW
BEGIN
    IF NOT (
        (NEW.`concept_type` = 'main' AND NEW.`main_concepts_id` IS NOT NULL AND NEW.`sub_concepts_id` IS NULL)
        OR (NEW.`concept_type` = 'sub' AND NEW.`sub_concepts_id` IS NOT NULL AND NEW.`main_concepts_id` IS NULL)
    ) THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'Invalid main_concepts_id / sub_concepts_id for the given concept_type';
    END IF;
END$$

-- -----------------------------------------------------
-- Trigger 2: บันทึกประวัติการแก้ไขคะแนนอัตโนมัติลง score_versions
-- -----------------------------------------------------
DROP TRIGGER IF EXISTS `trg_attempt_answers_score_history`$$
CREATE TRIGGER `trg_attempt_answers_score_history`
AFTER UPDATE ON `attempt_answers`
FOR EACH ROW
BEGIN
    IF NOT (OLD.`score` <=> NEW.`score`) AND NEW.`score` IS NOT NULL THEN
        IF @current_user_id IS NULL THEN
            SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'Cannot update score: @current_user_id session variable is required for audit';
        END IF;

        INSERT INTO `score_versions` (
            `attempt_answer_id`,
            `old_score`,
            `new_score`,
            `graded_by`,
            `reason`,
            `created_at`
        ) VALUES (
            NEW.`id_attempt_answers`,
            OLD.`score`,
            NEW.`score`,
            @current_user_id,
            COALESCE(@score_change_reason, 'Auto-Recorded by Trigger'),
            NOW()
        );
    END IF;
END$$

-- -----------------------------------------------------
-- Trigger 3: ป้องกันการลบข้อสอบออกจากชุดสอบขณะมีผู้สอบกำลังทำข้อสอบ 
-- -----------------------------------------------------
DROP TRIGGER IF EXISTS `trg_exam_questions_prevent_delete_active`$$
CREATE TRIGGER `trg_exam_questions_prevent_delete_active`
BEFORE DELETE ON `exam_questions`
FOR EACH ROW
BEGIN
    IF EXISTS (
        SELECT 1
        FROM `attempts`
        WHERE `exams_id` = OLD.`exams_id`
          AND `status` = 'in_progress'
    ) THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'Cannot delete exam question while an exam attempt is in progress';
    END IF;
END$$

-- -----------------------------------------------------
-- Trigger 4: ป้องกันการย้ายชุดสอบหรือสลับลำดับข้อสอบขณะมีผู้สอบกำลังทำข้อสอบ 
-- -----------------------------------------------------
DROP TRIGGER IF EXISTS `trg_exam_questions_prevent_move_active`$$
CREATE TRIGGER `trg_exam_questions_prevent_move_active`
BEFORE UPDATE ON `exam_questions`
FOR EACH ROW
BEGIN
    DECLARE v_has_in_progress TINYINT(1) DEFAULT 0;

    SELECT EXISTS (
        SELECT 1
        FROM `attempts`
        WHERE `exams_id` = OLD.`exams_id`
          AND `status` = 'in_progress'
    ) INTO v_has_in_progress;

    IF v_has_in_progress = 1 THEN
        IF OLD.`exams_id` <> NEW.`exams_id` OR OLD.`sort_order` <> NEW.`sort_order` THEN
            SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'Cannot move or reorder exam question while an exam attempt is in progress';
        END IF;

        IF OLD.`question_version_id` <> NEW.`question_version_id` THEN
            IF NOT EXISTS (
                SELECT 1
                FROM `exam_question_version_releases`
                WHERE `exam_question_id` = OLD.`id_exam_questions`
                  AND `old_question_version_id` <=> OLD.`question_version_id`
                  AND `new_question_version_id` = NEW.`question_version_id`
            ) THEN
                SIGNAL SQLSTATE '45000'
                SET MESSAGE_TEXT = 'Cannot change question_version_id directly during active exam: release record is required';
            END IF;
        END IF;
    END IF;
END$$

-- -----------------------------------------------------
-- Trigger 5 : ตรวจสอบความยินยอม, การถอนสิทธิ์ และความตรงกันของ Attempt
-- -----------------------------------------------------
DROP TRIGGER IF EXISTS `trg_location_logs_validate_consent`$$
CREATE TRIGGER `trg_location_logs_validate_consent`
BEFORE INSERT ON `exam_location_logs`
FOR EACH ROW
BEGIN
    DECLARE v_consent_given TINYINT(1);
    DECLARE v_revoked_at DATETIME;
    DECLARE v_consent_attempt_id INT UNSIGNED;

    SELECT `consent_given`, `revoked_at`, `attempts_id`
    INTO v_consent_given, v_revoked_at, v_consent_attempt_id
    FROM `exam_location_consents`
    WHERE `id_exam_location_consents` = NEW.`consent_id`
      AND `users_id` = NEW.`users_id`
      AND `exams_id` = NEW.`exams_id`;

    IF v_consent_given IS NULL OR v_consent_given <> 1 OR v_revoked_at IS NOT NULL THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'Cannot log location: valid active consent is required';
    END IF;

    IF v_consent_attempt_id IS NOT NULL AND v_consent_attempt_id <> NEW.`attempts_id` THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'Cannot log location: attempt mismatch with consent record';
    END IF;
END$$

-- -----------------------------------------------------
-- Trigger 6: ตรวจสอบว่า control_event_id ตรงกับกลุ่มเรียนของ Attempt (FR-14)
-- -----------------------------------------------------
DROP TRIGGER IF EXISTS `trg_attempts_validate_control_event`$$
CREATE TRIGGER `trg_attempts_validate_control_event`
BEFORE UPDATE ON `attempts`
FOR EACH ROW
BEGIN
    DECLARE v_event_section_id INT UNSIGNED;

    IF NEW.`control_event_id` IS NOT NULL
       AND NOT (OLD.`control_event_id` <=> NEW.`control_event_id`) THEN
        SELECT `sections_id`
        INTO v_event_section_id
        FROM `exam_control_events`
        WHERE `id_exam_control_events` = NEW.`control_event_id`
          AND `exams_id` = NEW.`exams_id`;

        IF v_event_section_id IS NOT NULL AND v_event_section_id <> NEW.`sections_id` THEN
            SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'Cannot assign control event: section mismatch with attempt';
        END IF;
    END IF;
END$$

DELIMITER ;

SET SQL_MODE = @OLD_SQL_MODE;

SET FOREIGN_KEY_CHECKS = @OLD_FOREIGN_KEY_CHECKS;

SET UNIQUE_CHECKS = @OLD_UNIQUE_CHECKS;