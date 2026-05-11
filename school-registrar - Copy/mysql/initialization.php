

CREATE DATABASE IF NOT EXISTS school_registrar
  CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;

USE school_registrar;

-- ============================================================
--  USERS
--  Stores superadmin and registrar accounts.
-- ============================================================

CREATE TABLE IF NOT EXISTS `users` (
  `id`        int(11)      NOT NULL AUTO_INCREMENT,
  `email`     varchar(255) NOT NULL,
  `name`      varchar(255) NOT NULL,
  `password`  varchar(255) NOT NULL,
  `role`      enum('superadmin','registrar') NOT NULL DEFAULT 'registrar',
  `is_active` tinyint(1)   NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- Seed superadmin — password: Admin@1234 (change after first login)
INSERT IGNORE INTO `users` (`email`, `name`, `password`, `role`, `is_active`) VALUES
('superadmin@school.com', 'Super Admin',
 '$2y$10$TKh8H1.PfbuNIAHJj5HuxemtLgUgaQ4.LG5O4K5RoTGMFCGqMGnAa',
 'superadmin', 1);
-- Default password: Admin@1234

-- ============================================================
--  SCHOOL YEARS
--  Tracks enrollment periods. Only one should be is_active=1.
-- ============================================================
CREATE TABLE IF NOT EXISTS `school_years` (
  `id`         int(11)     NOT NULL AUTO_INCREMENT,
  `label`      varchar(20) NOT NULL,
  `is_active`  tinyint(1)  NOT NULL DEFAULT 0,
  `created_at` datetime    DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `label` (`label`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT IGNORE INTO `school_years` (`label`, `is_active`) VALUES
  ('2024-2025', 0),
  ('2025-2026', 1);

-- ============================================================
--  GRADE LEVELS
-- ============================================================
CREATE TABLE IF NOT EXISTS `grade_levels` (
  `id`   int(11)     NOT NULL AUTO_INCREMENT,
  `name` varchar(30) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT IGNORE INTO `grade_levels` (`id`, `name`) VALUES
  (1,  'Nursery'),
  (2,  'Kinder 1'),
  (3,  'Kinder 2'),
  (4,  'Grade 1'),
  (5,  'Grade 2'),
  (6,  'Grade 3'),
  (7,  'Grade 4'),
  (8,  'Grade 5'),
  (9,  'Grade 6'),
  (10, 'Grade 7'),
  (11, 'Grade 8'),
  (12, 'Grade 9'),
  (13, 'Grade 10');

-- ============================================================
--  SECTIONS
--  Each section belongs to a grade level (normalized FK).
-- ============================================================
CREATE TABLE IF NOT EXISTS `sections` (
  `id`             int(11)     NOT NULL AUTO_INCREMENT,
  `name`           varchar(50) NOT NULL,
  `grade_level_id` int(11)     NOT NULL,
  PRIMARY KEY (`id`),
  KEY `grade_level_id` (`grade_level_id`),
  CONSTRAINT `sections_ibfk_1`
    FOREIGN KEY (`grade_level_id`) REFERENCES `grade_levels` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT IGNORE INTO `sections` (`name`, `grade_level_id`) VALUES
  ('Newton',   1), ('Einstein', 1), ('Curie', 1), ('Franklin', 1),
  ('Newton',   2), ('Einstein', 2), ('Curie', 2), ('Franklin', 2),
  ('Newton',   3), ('Einstein', 3), ('Curie', 3), ('Franklin', 3),
  ('Newton',   4), ('Einstein', 4), ('Curie', 4), ('Franklin', 4);

-- ============================================================
--  STUDENTS
--  school_year_id added via ALTER below for existing installs.
-- ============================================================
CREATE TABLE IF NOT EXISTS `students` (
  `id`             int(11)      NOT NULL AUTO_INCREMENT,
  `lrn`            varchar(20)  NOT NULL,
  `last_name`      varchar(50)  NOT NULL,
  `first_name`     varchar(50)  NOT NULL,
  `middle_name`    varchar(50)  DEFAULT NULL,
  `grade_level_id` int(11)      NOT NULL,
  `section_id`     int(11)      DEFAULT NULL,
  `school_year_id` int(11)      DEFAULT NULL,
  `city`           varchar(100) DEFAULT NULL,
  `contact_number` varchar(20)  DEFAULT NULL,
  `student_type`   enum('new','old') DEFAULT 'old',
  `photo`          varchar(255) DEFAULT NULL,
  `is_archived`    tinyint(1)   DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `lrn` (`lrn`),
  KEY `grade_level_id` (`grade_level_id`),
  KEY `section_id` (`section_id`),
  KEY `school_year_id` (`school_year_id`),
  CONSTRAINT `students_ibfk_1`
    FOREIGN KEY (`grade_level_id`) REFERENCES `grade_levels` (`id`),
  CONSTRAINT `students_ibfk_2`
    FOREIGN KEY (`section_id`) REFERENCES `sections` (`id`),
  CONSTRAINT `students_ibfk_3`
    FOREIGN KEY (`school_year_id`) REFERENCES `school_years` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- For existing installs: add school_year_id if not present
ALTER TABLE `students`
  ADD COLUMN IF NOT EXISTS `school_year_id` int(11) DEFAULT NULL AFTER `section_id`;

ALTER TABLE `students`
  ADD KEY IF NOT EXISTS `school_year_id` (`school_year_id`);

-- Add FK only if it doesn't exist (safe to skip if already present)
SET @fk_exists = (
  SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
  WHERE CONSTRAINT_SCHEMA = 'school_registrar'
    AND TABLE_NAME = 'students'
    AND CONSTRAINT_NAME = 'students_ibfk_3'
);
SET @sql = IF(@fk_exists = 0,
  'ALTER TABLE students ADD CONSTRAINT students_ibfk_3 FOREIGN KEY (school_year_id) REFERENCES school_years (id)',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ============================================================
--  TEACHERS
-- ============================================================
CREATE TABLE IF NOT EXISTS `teachers` (
  `id`             int(11)      NOT NULL AUTO_INCREMENT,
  `photo`          varchar(255) DEFAULT NULL,
  `first_name`     varchar(100) NOT NULL,
  `last_name`      varchar(100) NOT NULL,
  `middle_name`    varchar(100) DEFAULT NULL,
  `email`          varchar(150) DEFAULT NULL,
  `contact_number` varchar(20)  DEFAULT NULL,
  `subject`        varchar(100) DEFAULT NULL,
  `department`     varchar(100) DEFAULT NULL,
  `is_archived`    tinyint(1)   DEFAULT 0,
  `created_at`     datetime     DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ============================================================
--  TEACHER ATTENDANCE
-- ============================================================
CREATE TABLE IF NOT EXISTS `teacher_attendance` (
  `id`         int(11)     NOT NULL AUTO_INCREMENT,
  `teacher_id` int(11)     NOT NULL,
  `date`       date        NOT NULL,
  `status`     enum('present','absent','late') DEFAULT 'present',
  `remarks`    varchar(255) DEFAULT NULL,
  `created_at` datetime    DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_attendance` (`teacher_id`, `date`),
  CONSTRAINT `teacher_attendance_ibfk_1`
    FOREIGN KEY (`teacher_id`) REFERENCES `teachers` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ============================================================
--  NOTES
-- ============================================================
CREATE TABLE IF NOT EXISTS `notes` (
  `id`         int(11)      NOT NULL AUTO_INCREMENT,
  `user_id`    int(11)      NOT NULL,
  `title`      varchar(255) NOT NULL DEFAULT 'Untitled Note',
  `body`       text         DEFAULT NULL,
  `category`   enum('General','Academic','Meeting','Concern') DEFAULT 'General',
  `created_at` datetime     DEFAULT current_timestamp(),
  `updated_at` datetime     DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


-- ============================================================
--  ENROLLMENT SYSTEM TABLES
-- ============================================================

-- Enrollment status per student per school year
CREATE TABLE IF NOT EXISTS `enrollments` (
  `id`             INT AUTO_INCREMENT PRIMARY KEY,
  `student_id`     INT NOT NULL,
  `school_year_id` INT NOT NULL,
  `grade_level_id` INT NOT NULL,
  `section_id`     INT DEFAULT NULL,
  `status`         ENUM('pending','enrolled','dropped') DEFAULT 'pending',
  `enrolled_at`    DATETIME DEFAULT current_timestamp(),
  UNIQUE KEY `unique_enrollment` (`student_id`, `school_year_id`),
  FOREIGN KEY (`student_id`)     REFERENCES `students`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`school_year_id`) REFERENCES `school_years`(`id`),
  FOREIGN KEY (`grade_level_id`) REFERENCES `grade_levels`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Fee types per grade level per school year
CREATE TABLE IF NOT EXISTS `fees` (
  `id`             INT AUTO_INCREMENT PRIMARY KEY,
  `grade_level_id` INT NOT NULL,
  `school_year_id` INT NOT NULL,
  `name`           VARCHAR(100) NOT NULL,
  `amount`         DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  FOREIGN KEY (`grade_level_id`) REFERENCES `grade_levels`(`id`),
  FOREIGN KEY (`school_year_id`) REFERENCES `school_years`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Payment records per student per fee
CREATE TABLE IF NOT EXISTS `payments` (
  `id`          INT AUTO_INCREMENT PRIMARY KEY,
  `student_id`  INT NOT NULL,
  `fee_id`      INT NOT NULL,
  `amount_paid` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `balance`     DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `status`      ENUM('paid','partial','unpaid') DEFAULT 'unpaid',
  `paid_at`     DATE DEFAULT NULL,
  `or_number`   VARCHAR(50) DEFAULT NULL,
  `notes`       VARCHAR(255) DEFAULT NULL,
  `created_at`  DATETIME DEFAULT current_timestamp(),
  FOREIGN KEY (`student_id`) REFERENCES `students`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`fee_id`)     REFERENCES `fees`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ============================================================
--  PHASE 2 — DUAL INTERFACE ADDITIONS
-- ============================================================

-- Expand roles: superadmin and registrar only
ALTER TABLE `users`
  MODIFY COLUMN `role` ENUM('superadmin','registrar') NOT NULL DEFAULT 'registrar';

-- Parent/Guardian accounts (separate from staff users)
CREATE TABLE IF NOT EXISTS `parent_accounts` (
  `id`         INT AUTO_INCREMENT PRIMARY KEY,
  `student_id` INT NOT NULL,
  `name`       VARCHAR(150) NOT NULL,
  `email`      VARCHAR(150) NOT NULL UNIQUE,
  `password`   VARCHAR(255) NOT NULL,
  `contact`    VARCHAR(20) DEFAULT NULL,
  `is_active`  TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` DATETIME DEFAULT current_timestamp(),
  FOREIGN KEY (`student_id`) REFERENCES `students`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Required document types
CREATE TABLE IF NOT EXISTS `requirements` (
  `id`           INT AUTO_INCREMENT PRIMARY KEY,
  `name`         VARCHAR(100) NOT NULL,
  `description`  VARCHAR(255) DEFAULT NULL,
  `student_type` ENUM('new','old','both') DEFAULT 'both',
  `is_required`  TINYINT(1) DEFAULT 1,
  `sort_order`   INT DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Seed default requirements
INSERT IGNORE INTO `requirements` (`name`, `description`, `student_type`, `sort_order`) VALUES
  ('PSA Birth Certificate', NULL, 'new', 1),
  ('Form 138 / Report Card', NULL, 'new', 2),
  ('Good Moral Certificate', NULL, 'new', 3),
  ('2x2 ID Photo', NULL, 'both', 4),
  ('Certificate of Completion', NULL, 'new', 5);

-- Student document submissions
CREATE TABLE IF NOT EXISTS `student_requirements` (
  `id`             INT AUTO_INCREMENT PRIMARY KEY,
  `student_id`     INT NOT NULL,
  `requirement_id` INT NOT NULL,
  `school_year_id` INT NOT NULL,
  `file_path`      VARCHAR(255) DEFAULT NULL,
  `status`         ENUM('missing','submitted','verified') DEFAULT 'missing',
  `submitted_at`   DATETIME DEFAULT NULL,
  `verified_by`    INT DEFAULT NULL,
  `verified_at`    DATETIME DEFAULT NULL,
  `notes`          VARCHAR(255) DEFAULT NULL,
  UNIQUE KEY `unique_req` (`student_id`, `requirement_id`, `school_year_id`),
  FOREIGN KEY (`student_id`)     REFERENCES `students`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`requirement_id`) REFERENCES `requirements`(`id`),
  FOREIGN KEY (`school_year_id`) REFERENCES `school_years`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Student clearance per school year
CREATE TABLE IF NOT EXISTS `clearance` (
  `id`               INT AUTO_INCREMENT PRIMARY KEY,
  `student_id`       INT NOT NULL,
  `school_year_id`   INT NOT NULL,
  `library_status`   ENUM('pending','cleared') DEFAULT 'pending',
  `library_by`       INT DEFAULT NULL,
  `library_at`       DATETIME DEFAULT NULL,
  `registrar_status` ENUM('pending','cleared') DEFAULT 'pending',
  `registrar_by`     INT DEFAULT NULL,
  `registrar_at`     DATETIME DEFAULT NULL,
  `finance_status`   ENUM('pending','cleared') DEFAULT 'pending',
  `finance_by`       INT DEFAULT NULL,
  `finance_at`       DATETIME DEFAULT NULL,
  UNIQUE KEY `unique_clearance` (`student_id`, `school_year_id`),
  FOREIGN KEY (`student_id`)     REFERENCES `students`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`school_year_id`) REFERENCES `school_years`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Add payment method and proof columns to payments
ALTER TABLE `payments`
  ADD COLUMN IF NOT EXISTS `payment_method` ENUM('cash','check','bank_transfer') DEFAULT 'cash' AFTER `or_number`,
  ADD COLUMN IF NOT EXISTS `proof_file`     VARCHAR(255) DEFAULT NULL AFTER `payment_method`,
  ADD COLUMN IF NOT EXISTS `verified_by`    INT DEFAULT NULL AFTER `proof_file`;

-- Add reference number to enrollments
ALTER TABLE `enrollments`
  ADD COLUMN IF NOT EXISTS `ref_number` VARCHAR(20) DEFAULT NULL AFTER `id`;

-- ============================================================
--  FEE TYPE COLUMN
-- ============================================================
ALTER TABLE `fees`
  ADD COLUMN IF NOT EXISTS `fee_type` ENUM('tuition','miscellaneous','pta_fund','development','books','sped','other') DEFAULT 'tuition' AFTER `name`;

-- ============================================================
--  SPED COLUMNS
-- ============================================================
ALTER TABLE `students`
  ADD COLUMN IF NOT EXISTS `is_sped`    TINYINT(1)   NOT NULL DEFAULT 0   AFTER `is_archived`;
ALTER TABLE `students`
  ADD COLUMN IF NOT EXISTS `sped_notes` VARCHAR(255) DEFAULT NULL AFTER `is_sped`;

-- ============================================================
--  INTERNAL MESSAGING
-- ============================================================
CREATE TABLE IF NOT EXISTS `messages` (
  `id`           INT AUTO_INCREMENT PRIMARY KEY,
  `sender_id`    INT NOT NULL,
  `recipient_id` INT NOT NULL,
  `subject`      VARCHAR(255) NOT NULL,
  `body`         TEXT NOT NULL,
  `is_read`      TINYINT(1) NOT NULL DEFAULT 0,
  `created_at`   DATETIME DEFAULT current_timestamp(),
  FOREIGN KEY (`sender_id`)    REFERENCES `users`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`recipient_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
--  DISCOUNTS / SCHOLARSHIPS
-- ============================================================
CREATE TABLE IF NOT EXISTS `discounts` (
  `id`             INT AUTO_INCREMENT PRIMARY KEY,
  `student_id`     INT NOT NULL,
  `school_year_id` INT NOT NULL,
  `type`           ENUM('employee','sibling','scholarship','other') NOT NULL,
  `percentage`     DECIMAL(5,2) NOT NULL DEFAULT 0.00,
  `label`          VARCHAR(100) DEFAULT NULL,
  `notes`          VARCHAR(255) DEFAULT NULL,
  `created_at`     DATETIME DEFAULT current_timestamp(),
  FOREIGN KEY (`student_id`)     REFERENCES `students`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`school_year_id`) REFERENCES `school_years`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
--  PAYMENT PLAN COLUMNS
-- ============================================================
ALTER TABLE `payments`
  ADD COLUMN IF NOT EXISTS `payment_plan` ENUM('annual','semi_annual','quarterly','monthly') DEFAULT 'annual' AFTER `payment_method`;
ALTER TABLE `payments`
  ADD COLUMN IF NOT EXISTS `surcharge` DECIMAL(10,2) DEFAULT 0.00 AFTER `payment_plan`;

-- ============================================================
--  AUDIT LOG
-- ============================================================
CREATE TABLE IF NOT EXISTS `audit_log` (
  `id`         INT AUTO_INCREMENT PRIMARY KEY,
  `user_id`    INT DEFAULT NULL,
  `user_name`  VARCHAR(150) DEFAULT NULL,
  `action`     VARCHAR(100) NOT NULL,
  `target`     VARCHAR(100) DEFAULT NULL,
  `target_id`  INT DEFAULT NULL,
  `details`    TEXT DEFAULT NULL,
  `created_at` DATETIME DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
--  NOTIFICATIONS
-- ============================================================
CREATE TABLE IF NOT EXISTS `notifications` (
  `id`         INT AUTO_INCREMENT PRIMARY KEY,
  `user_id`    INT NOT NULL,
  `type`       VARCHAR(50) NOT NULL DEFAULT 'info',
  `title`      VARCHAR(150) NOT NULL,
  `body`       TEXT DEFAULT NULL,
  `link`       VARCHAR(255) DEFAULT NULL,
  `is_read`    TINYINT(1) NOT NULL DEFAULT 0,
  `created_at` DATETIME DEFAULT current_timestamp(),
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
--  SCHOOL YEAR PROMOTION LOG
-- ============================================================
CREATE TABLE IF NOT EXISTS `promotions` (
  `id`              INT AUTO_INCREMENT PRIMARY KEY,
  `from_sy_id`      INT NOT NULL,
  `to_sy_id`        INT NOT NULL,
  `promoted_by`     INT NOT NULL,
  `students_count`  INT DEFAULT 0,
  `promoted_at`     DATETIME DEFAULT current_timestamp(),
  FOREIGN KEY (`from_sy_id`) REFERENCES `school_years`(`id`),
  FOREIGN KEY (`to_sy_id`)   REFERENCES `school_years`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


-- ============================================================
--  PHASE 3 — MULTI-CHILD PARENT PORTAL
-- ============================================================

-- Parent accounts: add personal/address fields
ALTER TABLE `parent_accounts`
  ADD COLUMN IF NOT EXISTS `province`         VARCHAR(100) DEFAULT NULL AFTER `contact`,
  ADD COLUMN IF NOT EXISTS `city_municipality` VARCHAR(100) DEFAULT NULL AFTER `province`,
  ADD COLUMN IF NOT EXISTS `barangay`         VARCHAR(100) DEFAULT NULL AFTER `city_municipality`,
  ADD COLUMN IF NOT EXISTS `house_address`    VARCHAR(255) DEFAULT NULL AFTER `barangay`,
  ADD COLUMN IF NOT EXISTS `birthday`         DATE         DEFAULT NULL AFTER `house_address`,
  ADD COLUMN IF NOT EXISTS `sex`              ENUM('male','female') DEFAULT NULL AFTER `birthday`,
  ADD COLUMN IF NOT EXISTS `civil_status`     VARCHAR(50)  DEFAULT NULL AFTER `sex`,
  ADD COLUMN IF NOT EXISTS `religion`         VARCHAR(100) DEFAULT NULL AFTER `civil_status`;

-- Junction table: one parent → many students
CREATE TABLE IF NOT EXISTS `parent_student_links` (
  `id`         INT AUTO_INCREMENT PRIMARY KEY,
  `parent_id`  INT NOT NULL,
  `student_id` INT NOT NULL,
  UNIQUE KEY `unique_link` (`parent_id`, `student_id`),
  FOREIGN KEY (`parent_id`)  REFERENCES `parent_accounts`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`student_id`) REFERENCES `students`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Students: add education history + personal fields
ALTER TABLE `students`
  ADD COLUMN IF NOT EXISTS `last_school`           VARCHAR(255) DEFAULT NULL AFTER `sped_notes`,
  ADD COLUMN IF NOT EXISTS `school_year_graduated` VARCHAR(20)  DEFAULT NULL AFTER `last_school`,
  ADD COLUMN IF NOT EXISTS `school_address`        VARCHAR(255) DEFAULT NULL AFTER `school_year_graduated`,
  ADD COLUMN IF NOT EXISTS `birthday`              DATE         DEFAULT NULL AFTER `school_address`,
  ADD COLUMN IF NOT EXISTS `sex`                   ENUM('male','female') DEFAULT NULL AFTER `birthday`,
  ADD COLUMN IF NOT EXISTS `religion`              VARCHAR(100) DEFAULT NULL AFTER `sex`,
  ADD COLUMN IF NOT EXISTS `province`              VARCHAR(100) DEFAULT NULL AFTER `religion`,
  ADD COLUMN IF NOT EXISTS `city_municipality`     VARCHAR(100) DEFAULT NULL AFTER `province`,
  ADD COLUMN IF NOT EXISTS `barangay`              VARCHAR(100) DEFAULT NULL AFTER `city_municipality`;

-- ============================================================
--  PHASE 4 — FULL FEATURE SET
-- ============================================================

-- Account lockout support
ALTER TABLE `users`
  ADD COLUMN IF NOT EXISTS `failed_attempts` TINYINT(1) NOT NULL DEFAULT 0 AFTER `is_active`,
  ADD COLUMN IF NOT EXISTS `locked_at`       DATETIME DEFAULT NULL AFTER `failed_attempts`;

-- Enrollment workflow: add 'to_follow' and 'registered' statuses
ALTER TABLE `enrollments`
  MODIFY COLUMN `status` ENUM('pending','registered','to_follow','enrolled','dropped') DEFAULT 'pending';

-- Enrollment: add payment_plan and mode_of_payment
ALTER TABLE `enrollments`
  ADD COLUMN IF NOT EXISTS `payment_plan`    ENUM('annual','semi_annual','quarterly','monthly') DEFAULT 'annual' AFTER `status`,
  ADD COLUMN IF NOT EXISTS `reservation_paid` TINYINT(1) NOT NULL DEFAULT 0 AFTER `payment_plan`;

-- student_requirements: add 'to_follow' status and uploaded_by
ALTER TABLE `student_requirements`
  MODIFY COLUMN `status` ENUM('missing','to_follow','submitted','verified','rejected') DEFAULT 'missing';
ALTER TABLE `student_requirements`
  ADD COLUMN IF NOT EXISTS `uploaded_by`   INT DEFAULT NULL AFTER `verified_at`,
  ADD COLUMN IF NOT EXISTS `uploaded_by_role` VARCHAR(20) DEFAULT NULL AFTER `uploaded_by`,
  ADD COLUMN IF NOT EXISTS `reject_reason` VARCHAR(255) DEFAULT NULL AFTER `uploaded_by_role`;

-- Enrollment timeline/steps log
CREATE TABLE IF NOT EXISTS `enrollment_timeline` (
  `id`          INT AUTO_INCREMENT PRIMARY KEY,
  `enrollment_id` INT NOT NULL,
  `step`        VARCHAR(100) NOT NULL,
  `status`      ENUM('pending','in_progress','completed','rejected') DEFAULT 'pending',
  `notes`       TEXT DEFAULT NULL,
  `done_by`     INT DEFAULT NULL,
  `done_at`     DATETIME DEFAULT NULL,
  `created_at`  DATETIME DEFAULT current_timestamp(),
  FOREIGN KEY (`enrollment_id`) REFERENCES `enrollments`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Parent notifications (separate from staff notifications)
CREATE TABLE IF NOT EXISTS `parent_notifications` (
  `id`         INT AUTO_INCREMENT PRIMARY KEY,
  `parent_id`  INT NOT NULL,
  `student_id` INT DEFAULT NULL,
  `type`       VARCHAR(50) NOT NULL DEFAULT 'info',
  `title`      VARCHAR(150) NOT NULL,
  `body`       TEXT DEFAULT NULL,
  `is_read`    TINYINT(1) NOT NULL DEFAULT 0,
  `created_at` DATETIME DEFAULT current_timestamp(),
  FOREIGN KEY (`parent_id`) REFERENCES `parent_accounts`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Discount: add reservation_fee and employee_child_number fields
ALTER TABLE `discounts`
  MODIFY COLUMN `type` ENUM('employee','sibling','scholarship','reservation','other') NOT NULL;
ALTER TABLE `discounts`
  ADD COLUMN IF NOT EXISTS `child_number` TINYINT DEFAULT 1 AFTER `type`,
  ADD COLUMN IF NOT EXISTS `fixed_amount` DECIMAL(10,2) DEFAULT NULL AFTER `percentage`;

-- Fees: add reservation_fee flag
ALTER TABLE `fees`
  MODIFY COLUMN `fee_type` ENUM('tuition','miscellaneous','pta_fund','development','books','sped','reservation','other') DEFAULT 'tuition';

-- Payments: add gcash/bank_transfer receipt upload
ALTER TABLE `payments`
  MODIFY COLUMN `payment_method` ENUM('cash','check','bank_transfer','gcash','maya','other') DEFAULT 'cash';

-- Master list view support: add class_adviser to sections
ALTER TABLE `sections`
  ADD COLUMN IF NOT EXISTS `adviser_id` INT DEFAULT NULL AFTER `grade_level_id`,
  ADD COLUMN IF NOT EXISTS `capacity`   INT DEFAULT 40 AFTER `adviser_id`;
