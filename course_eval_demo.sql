-- ================================================================
-- COURSE EVALUATION SYSTEM — COMPLETE SETUP & TEST DATA
-- Generated: 2026-06-24
-- ================================================================
-- All test accounts use password: password
-- ================================================================

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET NAMES utf8mb4;
SET foreign_key_checks = 0;
START TRANSACTION;

CREATE DATABASE IF NOT EXISTS `course_evaluation`
  DEFAULT CHARACTER SET utf8mb4
  DEFAULT COLLATE utf8mb4_unicode_ci;
USE `course_evaluation`;

-- ================================================================
-- academic_year
-- ================================================================
DROP TABLE IF EXISTS `academic_year`;
CREATE TABLE `academic_year` (
  `academic_year_id` INT AUTO_INCREMENT PRIMARY KEY,
  `start_year`       INT NOT NULL,
  `end_year`         INT AS (`start_year` + 1) PERSISTENT,
  `year_label`       VARCHAR(9) AS (CONCAT(`start_year`,'/',`start_year`+1)) PERSISTENT,
  `is_active`        TINYINT(1) DEFAULT 0,
  `created_at`       TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `academic_year` (`start_year`,`is_active`) VALUES
(2023, 0),   -- ID 1: 2023/2024
(2024, 0),   -- ID 2: 2024/2025
(2025, 1);   -- ID 3: 2025/2026 ← ACTIVE

-- ================================================================
-- semesters
-- ================================================================
DROP TABLE IF EXISTS `semesters`;
CREATE TABLE `semesters` (
  `semester_id`      INT AUTO_INCREMENT PRIMARY KEY,
  `academic_year_id` INT NOT NULL,
  `semester_name`    ENUM('First','Second') NOT NULL,
  `semester_value`   TINYINT NOT NULL,
  `is_active`        TINYINT DEFAULT 0,
  `created_at`       TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`academic_year_id`) REFERENCES `academic_year`(`academic_year_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `semesters` (`academic_year_id`,`semester_name`,`semester_value`,`is_active`) VALUES
(1,'First',1,0),   -- ID 1
(1,'Second',2,0),  -- ID 2
(2,'First',1,0),   -- ID 3
(2,'Second',2,0),  -- ID 4
(3,'First',1,0),   -- ID 5
(3,'Second',2,1);  -- ID 6 ← ACTIVE

-- ================================================================
-- department
-- ================================================================
DROP TABLE IF EXISTS `department`;
CREATE TABLE `department` (
  `t_id`       INT AUTO_INCREMENT PRIMARY KEY,
  `hod_id`     INT DEFAULT 0,
  `dep_name`   VARCHAR(100),
  `dep_code`   VARCHAR(50) UNIQUE,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `department` (`dep_name`,`dep_code`) VALUES
('Information Technology', 'ICT'),  -- ID 1
('Electrical Engineering', 'EEE'),  -- ID 2
('Marine Engineering',     'MEE'),  -- ID 3
('Transport Studies',      'DOT');  -- ID 4

-- ================================================================
-- level
-- ================================================================
DROP TABLE IF EXISTS `level`;
CREATE TABLE `level` (
  `t_id`         INT AUTO_INCREMENT PRIMARY KEY,
  `level_name`   VARCHAR(50),
  `level_number` INT UNIQUE,
  `created_at`   TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `level` (`level_name`,`level_number`) VALUES
('Level 100',100),  -- ID 1
('Level 200',200),  -- ID 2
('Level 300',300),  -- ID 3
('Level 400',400);  -- ID 4

-- ================================================================
-- programme
-- ================================================================
DROP TABLE IF EXISTS `programme`;
CREATE TABLE `programme` (
  `t_id`          INT AUTO_INCREMENT PRIMARY KEY,
  `prog_code`     VARCHAR(20) UNIQUE,
  `prog_name`     VARCHAR(100),
  `department_id` INT,
  `created_at`    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`department_id`) REFERENCES `department`(`t_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `programme` (`prog_code`,`prog_name`,`department_id`) VALUES
('BIT','BSc Information Technology',1),  -- ID 1
('BCS','BSc Computer Science',1),         -- ID 2
('BEE','BSc Electrical Engineering',2),   -- ID 3
('BME','BSc Marine Engineering',3),       -- ID 4
('BTS','BSc Transport Studies',4);        -- ID 5

-- ================================================================
-- roles
-- ================================================================
DROP TABLE IF EXISTS `roles`;
CREATE TABLE `roles` (
  `t_id`       INT AUTO_INCREMENT PRIMARY KEY,
  `role_id`    INT UNIQUE,
  `role_name`  VARCHAR(100) UNIQUE,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `roles` (`role_id`,`role_name`) VALUES
(1,'admin'),(2,'hod'),(3,'secretary'),(4,'advisor'),(5,'student'),(6,'quality');

-- ================================================================
-- classes  (includes: class_code, advisor_user_id)
-- ================================================================
DROP TABLE IF EXISTS `classes`;
CREATE TABLE `classes` (
  `t_id`               INT AUTO_INCREMENT PRIMARY KEY,
  `class_name`         VARCHAR(50) UNIQUE,
  `class_code`         VARCHAR(50) NOT NULL DEFAULT '',
  `department_id`      INT,
  `advisor_user_id`    INT NULL DEFAULT NULL,
  `year_of_completion` INT,
  `programme_id`       INT,
  `level_id`           INT,
  `created_at`         TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE INDEX `uq_class_code_dept` (`class_code`,`department_id`),
  INDEX `idx_advisor_user_id` (`advisor_user_id`),
  FOREIGN KEY (`department_id`) REFERENCES `department`(`t_id`),
  FOREIGN KEY (`programme_id`)  REFERENCES `programme`(`t_id`),
  FOREIGN KEY (`level_id`)      REFERENCES `level`(`t_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- advisor_user_id set after user_details insert
INSERT INTO `classes` (`class_name`,`class_code`,`department_id`,`year_of_completion`,`programme_id`,`level_id`) VALUES
('BIT28','BIT28',1,2028,1,1),  -- ID 1: ICT L100
('BIT27','BIT27',1,2027,1,2),  -- ID 2: ICT L200
('BIT26','BIT26',1,2026,1,3),  -- ID 3: ICT L300
('BCS28','BCS28',1,2028,2,1),  -- ID 4: ICT L100 BCS
('EEE28','EEE28',2,2028,3,1),  -- ID 5: EEE L100
('EEE27','EEE27',2,2027,3,2);  -- ID 6: EEE L200

-- ================================================================
-- courses  (includes: credit_hours)
-- ================================================================
DROP TABLE IF EXISTS `courses`;
CREATE TABLE `courses` (
  `id`            INT AUTO_INCREMENT PRIMARY KEY,
  `course_code`   VARCHAR(50) UNIQUE,
  `name`          VARCHAR(255),
  `department_id` INT,
  `level_id`      INT,
  `semester_id`   INT,
  `credit_hours`  TINYINT UNSIGNED NOT NULL DEFAULT 3,
  `created_at`    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`department_id`) REFERENCES `department`(`t_id`),
  FOREIGN KEY (`level_id`)      REFERENCES `level`(`t_id`),
  FOREIGN KEY (`semester_id`)   REFERENCES `semesters`(`semester_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- All assigned to semester_id=6 (2025/2026 Second — active)
INSERT INTO `courses` (`course_code`,`name`,`department_id`,`level_id`,`semester_id`,`credit_hours`) VALUES
('BINT101','Introduction to Computing',       1,1,6,3),  -- ID 1
('BINT102','Mathematics for IT',              1,1,6,3),  -- ID 2
('BINT103','Communication Skills',            1,1,6,2),  -- ID 3
('BINT201','Data Structures & Algorithms',    1,2,6,3),  -- ID 4
('BINT202','Database Management Systems',     1,2,6,3),  -- ID 5
('BINT301','Software Engineering',            1,3,6,3),  -- ID 6
('BINT302','Computer Networks',               1,3,6,3),  -- ID 7
('BINT401','Final Year Project',              1,4,6,6),  -- ID 8
('BEEE101','Circuit Theory',                  2,1,6,3),  -- ID 9
('BEEE102','Engineering Mathematics',         2,1,6,3);  -- ID 10

-- ================================================================
-- user_details  (includes: force_password_change, updated_at)
-- ================================================================
DROP TABLE IF EXISTS `user_details`;
CREATE TABLE `user_details` (
  `user_id`               INT AUTO_INCREMENT PRIMARY KEY,
  `role_id`               INT,
  `f_name`                VARCHAR(100),
  `l_name`                VARCHAR(100),
  `username`              VARCHAR(100) UNIQUE,
  `email`                 VARCHAR(150) UNIQUE,
  `unique_id`             VARCHAR(20) UNIQUE,
  `password`              VARCHAR(255),
  `department_id`         INT,
  `class_id`              INT,
  `level_id`              INT,
  `is_active`             TINYINT DEFAULT 1,
  `force_password_change` TINYINT(1) DEFAULT 0,
  `created_at`            TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at`            TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`role_id`)       REFERENCES `roles`(`role_id`),
  FOREIGN KEY (`department_id`) REFERENCES `department`(`t_id`),
  FOREIGN KEY (`class_id`)      REFERENCES `classes`(`t_id`),
  FOREIGN KEY (`level_id`)      REFERENCES `level`(`t_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- password = "password"
-- hash: $2y$12$olRKMrkG2EVWcT2ReasNpOoAeS6j92zDcekHK.AUEINGjq/Sfom6q
SET @pw = '$2y$12$olRKMrkG2EVWcT2ReasNpOoAeS6j92zDcekHK.AUEINGjq/Sfom6q';

-- ── Staff (IDs 1-9) ───────────────────────────────────────────
INSERT INTO `user_details`
  (`role_id`,`f_name`,`l_name`,`username`,`email`,`unique_id`,`password`,`department_id`,`class_id`,`level_id`,`force_password_change`)
VALUES
(1,'System','Administrator','admin',       'admin@rmu.edu.gh',      NULL,@pw,NULL,NULL,NULL,0),  -- ID 1
(6,'Grace', 'Ankrah',       'quality1',    'quality@rmu.edu.gh',    NULL,@pw,1,   NULL,NULL,0),  -- ID 2
(2,'Kweku', 'Asante',       'hod_ict',     'hod.ict@rmu.edu.gh',    NULL,@pw,1,   NULL,NULL,0),  -- ID 3
(2,'Emmanuel','Darko',      'hod_eee',     'hod.eee@rmu.edu.gh',    NULL,@pw,2,   NULL,NULL,0),  -- ID 4
(3,'Abena', 'Mensah',       'sec_ict',     'sec.ict@rmu.edu.gh',    NULL,@pw,1,   NULL,NULL,0),  -- ID 5
(3,'Ama',   'Boateng',      'sec_eee',     'sec.eee@rmu.edu.gh',    NULL,@pw,2,   NULL,NULL,0),  -- ID 6
(4,'Kwame', 'Ofori',        'lec_kwame',   'lec.kwame@rmu.edu.gh',  NULL,@pw,1,   NULL,NULL,0),  -- ID 7
(4,'Ama',   'Adjei',        'lec_ama',     'lec.ama@rmu.edu.gh',    NULL,@pw,1,   NULL,NULL,0),  -- ID 8
(4,'Kofi',  'Bimpong',      'lec_kofi',    'lec.kofi@rmu.edu.gh',   NULL,@pw,2,   NULL,NULL,0);  -- ID 9

-- ── Students BIT28 (ICT, Level 100, class 1) — IDs 10-17 ─────
INSERT INTO `user_details`
  (`role_id`,`f_name`,`l_name`,`username`,`email`,`unique_id`,`password`,`department_id`,`class_id`,`level_id`,`force_password_change`)
VALUES
(5,'Akosua', 'Asante',     's.akosua.a',  's.akosua.a@rmu.edu.gh',  'A1B2C3D4E5',@pw,1,1,1,1),
(5,'Bernard','Osei',       's.bernard.o', 's.bernard.o@rmu.edu.gh', 'B2C3D4E5F6',@pw,1,1,1,1),
(5,'Cynthia','Darko',      's.cynthia.d', 's.cynthia.d@rmu.edu.gh', 'C3D4E5F6A7',@pw,1,1,1,1),
(5,'Daniel', 'Mensah',     's.daniel.m',  's.daniel.m@rmu.edu.gh',  'D4E5F6A7B8',@pw,1,1,1,1),
(5,'Efua',   'Koomson',    's.efua.k',    's.efua.k@rmu.edu.gh',    'E5F6A7B8C9',@pw,1,1,1,1),
(5,'Francis','Agyei',      's.francis.a', 's.francis.a@rmu.edu.gh', 'F6A7B8C9D0',@pw,1,1,1,1),
(5,'Gloria', 'Boateng',    's.gloria.b',  's.gloria.b@rmu.edu.gh',  'A7B8C9D0E1',@pw,1,1,1,1),
(5,'Henry',  'Tetteh',     's.henry.t',   's.henry.t@rmu.edu.gh',   'B8C9D0E1F2',@pw,1,1,1,1);

-- ── Students BIT27 (ICT, Level 200, class 2) — IDs 18-22 ─────
INSERT INTO `user_details`
  (`role_id`,`f_name`,`l_name`,`username`,`email`,`unique_id`,`password`,`department_id`,`class_id`,`level_id`,`force_password_change`)
VALUES
(5,'Irene',  'Quansah',    's.irene.q',   's.irene.q@rmu.edu.gh',   'C9D0E1F2A3',@pw,1,2,2,1),
(5,'James',  'Ankrah',     's.james.a',   's.james.a@rmu.edu.gh',   'D0E1F2A3B4',@pw,1,2,2,1),
(5,'Kate',   'Ofori',      's.kate.o',    's.kate.o@rmu.edu.gh',    'E1F2A3B4C5',@pw,1,2,2,1),
(5,'Liam',   'Amoah',      's.liam.a',    's.liam.a@rmu.edu.gh',    'F2A3B4C5D6',@pw,1,2,2,1),
(5,'Mabel',  'Acheampong', 's.mabel.a',   's.mabel.a@rmu.edu.gh',   'A3B4C5D6E7',@pw,1,2,2,1);

-- ── Students BIT26 (ICT, Level 300, class 3) — IDs 23-26 ─────
INSERT INTO `user_details`
  (`role_id`,`f_name`,`l_name`,`username`,`email`,`unique_id`,`password`,`department_id`,`class_id`,`level_id`,`force_password_change`)
VALUES
(5,'Nana',   'Opoku',      's.nana.o',    's.nana.o@rmu.edu.gh',    'B4C5D6E7F8',@pw,1,3,3,1),
(5,'Olivia', 'Frimpong',   's.olivia.f',  's.olivia.f@rmu.edu.gh',  'C5D6E7F8A9',@pw,1,3,3,1),
(5,'Peter',  'Sarpong',    's.peter.s',   's.peter.s@rmu.edu.gh',   'D6E7F8A9B0',@pw,1,3,3,1),
(5,'Rachel', 'Owusu',      's.rachel.o',  's.rachel.o@rmu.edu.gh',  'E7F8A9B0C1',@pw,1,3,3,1);

-- ── Students EEE28 (EEE, Level 100, class 5) — IDs 27-30 ─────
INSERT INTO `user_details`
  (`role_id`,`f_name`,`l_name`,`username`,`email`,`unique_id`,`password`,`department_id`,`class_id`,`level_id`,`force_password_change`)
VALUES
(5,'Samuel', 'Dankwa',     's.samuel.d',  's.samuel.d@rmu.edu.gh',  'F8A9B0C1D2',@pw,2,5,1,1),
(5,'Theresa','Badu',       's.theresa.b', 's.theresa.b@rmu.edu.gh', 'A9B0C1D2E3',@pw,2,5,1,1),
(5,'Uche',   'Nkrumah',    's.uche.n',    's.uche.n@rmu.edu.gh',    'B0C1D2E3F4',@pw,2,5,1,1),
(5,'Vida',   'Agyeman',    's.vida.a',    's.vida.a@rmu.edu.gh',    'C1D2E3F4A5',@pw,2,5,1,1);

-- ── Back-fill department HOD and class advisors ───────────────
UPDATE `department` SET `hod_id`=3 WHERE `t_id`=1;
UPDATE `department` SET `hod_id`=4 WHERE `t_id`=2;
UPDATE `classes` SET `advisor_user_id`=7 WHERE `t_id`=1;
UPDATE `classes` SET `advisor_user_id`=8 WHERE `t_id`=2;
UPDATE `classes` SET `advisor_user_id`=7 WHERE `t_id`=3;
UPDATE `classes` SET `advisor_user_id`=8 WHERE `t_id`=4;
UPDATE `classes` SET `advisor_user_id`=9 WHERE `t_id`=5;
UPDATE `classes` SET `advisor_user_id`=9 WHERE `t_id`=6;

-- ================================================================
-- advisor_levels
-- ================================================================
DROP TABLE IF EXISTS `advisor_levels`;
CREATE TABLE `advisor_levels` (
  `t_id`          INT AUTO_INCREMENT PRIMARY KEY,
  `advisor_id`    INT,
  `level_id`      INT,
  `department_id` INT,
  FOREIGN KEY (`advisor_id`)    REFERENCES `user_details`(`user_id`),
  FOREIGN KEY (`level_id`)      REFERENCES `level`(`t_id`),
  FOREIGN KEY (`department_id`) REFERENCES `department`(`t_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `advisor_levels` (`advisor_id`,`level_id`,`department_id`) VALUES
(7,1,1),(7,3,1),  -- Kwame: ICT L100, L300
(8,2,1),(8,4,1),  -- Ama:   ICT L200, L400
(9,1,2),(9,2,2);  -- Kofi:  EEE L100, L200

-- ================================================================
-- course_lecturers
-- ================================================================
DROP TABLE IF EXISTS `course_lecturers`;
CREATE TABLE `course_lecturers` (
  `assignment_id`    INT AUTO_INCREMENT PRIMARY KEY,
  `course_id`        INT,
  `lecturer_user_id` INT,
  `academic_year_id` INT,
  `semester_id`      INT,
  `assigned_by`      INT,
  `assigned_at`      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `is_active`        TINYINT DEFAULT 1,
  FOREIGN KEY (`course_id`)        REFERENCES `courses`(`id`),
  FOREIGN KEY (`lecturer_user_id`) REFERENCES `user_details`(`user_id`),
  FOREIGN KEY (`academic_year_id`) REFERENCES `academic_year`(`academic_year_id`),
  FOREIGN KEY (`semester_id`)      REFERENCES `semesters`(`semester_id`),
  FOREIGN KEY (`assigned_by`)      REFERENCES `user_details`(`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- academic_year_id=3 (2025/2026), semester_id=6 (Second, active)
INSERT INTO `course_lecturers`
  (`course_id`,`lecturer_user_id`,`academic_year_id`,`semester_id`,`assigned_by`)
VALUES
(1, 7,3,6,3),  -- BINT101 → Kwame  (HOD ICT assigned)
(2, 8,3,6,3),  -- BINT102 → Ama
(3, 7,3,6,3),  -- BINT103 → Kwame
(4, 8,3,6,3),  -- BINT201 → Ama
(5, 7,3,6,3),  -- BINT202 → Kwame
(6, 7,3,6,3),  -- BINT301 → Kwame
(7, 8,3,6,3),  -- BINT302 → Ama
(8, 8,3,6,3),  -- BINT401 → Ama
(9, 9,3,6,4),  -- BEEE101 → Kofi   (HOD EEE assigned)
(10,9,3,6,4);  -- BEEE102 → Kofi

-- ================================================================
-- evaluation_questions  (20 questions, all 9 categories)
-- ================================================================
DROP TABLE IF EXISTS `evaluation_questions`;
CREATE TABLE `evaluation_questions` (
  `question_id`   INT AUTO_INCREMENT PRIMARY KEY,
  `question_text` VARCHAR(255),
  `category`      VARCHAR(50),
  `display_order` INT,
  `is_active`     TINYINT DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `evaluation_questions` (`question_text`,`category`,`display_order`) VALUES
('Course objectives and learning outcomes were clearly stated',              'Course Content & Delivery',           1),
('Course materials (notes, slides) were relevant and well-organised',        'Course Content & Delivery',           2),
('The course content was covered at an appropriate pace',                    'Course Content & Delivery',           3),
('The lecturer explained difficult concepts in an understandable way',       'Course Content & Delivery',           4),
('The lecturer was punctual and completed the required contact hours',       'Course Content & Delivery',           5),
('Assignments and assessments were clearly explained',                       'Assessment & Evaluation',             6),
('Assessment methods fairly reflected what was taught',                      'Assessment & Evaluation',             7),
('Feedback on assessments was provided in a timely manner',                  'Assessment & Evaluation',             8),
('The classroom environment was conducive to learning',                      'Teaching & Learning Environment',     9),
('There was adequate time for student questions and interaction',             'Teaching & Learning Environment',    10),
('The lecturer treated all students with respect',                           'Teaching & Learning Environment',    11),
('Washroom facilities were clean and well-maintained',                       'Facilities: Washroom & Surroundings',12),
('The campus surroundings and grounds were clean',                           'Facilities: Washroom & Surroundings',13),
('Registry staff were helpful and processed requests promptly',              'Support Services: Registry',         14),
('Library resources (books, journals) were adequate for the course',         'Support Services: Library',          15),
('Library staff provided satisfactory assistance',                           'Support Services: Library',          16),
('Administrative staff handled enquiries professionally',                    'Support Services: Administration',   17),
('Administrative processes (timetables, notices) were communicated clearly', 'Support Services: Administration',   18),
('The accounts office processed financial requests efficiently',              'Support Services: Accounts',         19),
('The sickbay provided adequate and prompt medical support',                 'Support Services: Sickbay',          20);

-- ================================================================
-- evaluation_tokens
-- One per (student, course) for the active period (yr=3, sem=6)
-- 10 = not yet used; 11-17 used for various courses
-- ================================================================
DROP TABLE IF EXISTS `evaluation_tokens`;
CREATE TABLE `evaluation_tokens` (
  `token_id`         INT AUTO_INCREMENT PRIMARY KEY,
  `student_user_id`  INT NULL,
  `course_id`        INT,
  `academic_year_id` INT,
  `semester_id`      INT,
  `token`            VARCHAR(64) UNIQUE,
  `is_used`          TINYINT DEFAULT 0,
  `used_at`          DATETIME NULL,
  `created_at`       TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`course_id`)        REFERENCES `courses`(`id`),
  FOREIGN KEY (`academic_year_id`) REFERENCES `academic_year`(`academic_year_id`),
  FOREIGN KEY (`semester_id`)      REFERENCES `semesters`(`semester_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `evaluation_tokens`
  (`student_user_id`,`course_id`,`academic_year_id`,`semester_id`,`token`,`is_used`,`used_at`)
VALUES
-- BIT28 × BINT101 (course 1): students 10-17; 10 pending, 11-16 done, 17 pending
(10,1,3,6,'tk_10_1',0,NULL),
(11,1,3,6,'tk_11_1',1,'2026-06-01 09:10:00'),
(12,1,3,6,'tk_12_1',1,'2026-06-01 09:22:00'),
(13,1,3,6,'tk_13_1',1,'2026-06-01 10:05:00'),
(14,1,3,6,'tk_14_1',1,'2026-06-02 08:45:00'),
(15,1,3,6,'tk_15_1',1,'2026-06-02 09:30:00'),
(16,1,3,6,'tk_16_1',1,'2026-06-02 10:15:00'),
(17,1,3,6,'tk_17_1',0,NULL),
-- BIT28 × BINT102 (course 2): 13 pending, rest done
(10,2,3,6,'tk_10_2',1,'2026-06-03 09:00:00'),
(11,2,3,6,'tk_11_2',1,'2026-06-03 09:20:00'),
(12,2,3,6,'tk_12_2',1,'2026-06-03 09:40:00'),
(13,2,3,6,'tk_13_2',0,NULL),
(14,2,3,6,'tk_14_2',1,'2026-06-03 10:00:00'),
(15,2,3,6,'tk_15_2',1,'2026-06-03 10:20:00'),
(16,2,3,6,'tk_16_2',1,'2026-06-03 10:40:00'),
(17,2,3,6,'tk_17_2',1,'2026-06-03 11:00:00'),
-- BIT28 × BINT103 (course 3): 15 pending
(10,3,3,6,'tk_10_3',1,'2026-06-04 09:00:00'),
(11,3,3,6,'tk_11_3',1,'2026-06-04 09:15:00'),
(12,3,3,6,'tk_12_3',1,'2026-06-04 09:30:00'),
(13,3,3,6,'tk_13_3',1,'2026-06-04 09:45:00'),
(14,3,3,6,'tk_14_3',1,'2026-06-04 10:00:00'),
(15,3,3,6,'tk_15_3',0,NULL),
(16,3,3,6,'tk_16_3',1,'2026-06-04 10:30:00'),
(17,3,3,6,'tk_17_3',1,'2026-06-04 10:45:00'),
-- BIT27 × BINT201 (course 4): 21 pending
(18,4,3,6,'tk_18_4',1,'2026-06-05 09:00:00'),
(19,4,3,6,'tk_19_4',1,'2026-06-05 09:20:00'),
(20,4,3,6,'tk_20_4',1,'2026-06-05 09:40:00'),
(21,4,3,6,'tk_21_4',0,NULL),
(22,4,3,6,'tk_22_4',1,'2026-06-05 10:20:00'),
-- BIT27 × BINT202 (course 5): 20 pending
(18,5,3,6,'tk_18_5',1,'2026-06-06 09:00:00'),
(19,5,3,6,'tk_19_5',1,'2026-06-06 09:20:00'),
(20,5,3,6,'tk_20_5',0,NULL),
(21,5,3,6,'tk_21_5',1,'2026-06-06 10:00:00'),
(22,5,3,6,'tk_22_5',1,'2026-06-06 10:20:00'),
-- BIT26 × BINT301 (course 6): 26 pending
(23,6,3,6,'tk_23_6',1,'2026-06-07 09:00:00'),
(24,6,3,6,'tk_24_6',1,'2026-06-07 09:20:00'),
(25,6,3,6,'tk_25_6',1,'2026-06-07 09:40:00'),
(26,6,3,6,'tk_26_6',0,NULL),
-- BIT26 × BINT302 (course 7): 25 pending
(23,7,3,6,'tk_23_7',1,'2026-06-08 09:00:00'),
(24,7,3,6,'tk_24_7',1,'2026-06-08 09:20:00'),
(25,7,3,6,'tk_25_7',0,NULL),
(26,7,3,6,'tk_26_7',1,'2026-06-08 10:00:00'),
-- EEE28 × BEEE101 (course 9): 30 pending
(27,9,3,6,'tk_27_9',1,'2026-06-09 09:00:00'),
(28,9,3,6,'tk_28_9',1,'2026-06-09 09:20:00'),
(29,9,3,6,'tk_29_9',1,'2026-06-09 09:40:00'),
(30,9,3,6,'tk_30_9',0,NULL),
-- EEE28 × BEEE102 (course 10): 28 pending
(27,10,3,6,'tk_27_10',1,'2026-06-10 09:00:00'),
(28,10,3,6,'tk_28_10',0,NULL),
(29,10,3,6,'tk_29_10',1,'2026-06-10 09:40:00'),
(30,10,3,6,'tk_30_10',1,'2026-06-10 10:00:00');

-- Anonymise used tokens (migration 002)
UPDATE `evaluation_tokens` SET `student_user_id`=NULL WHERE `is_used`=1;

-- ================================================================
-- evaluations  (40 rows — one per used token)
-- ================================================================
DROP TABLE IF EXISTS `evaluations`;
CREATE TABLE `evaluations` (
  `evaluation_id`    INT AUTO_INCREMENT PRIMARY KEY,
  `token`            VARCHAR(64),
  `course_id`        INT,
  `academic_year_id` INT,
  `semester_id`      INT,
  `evaluation_date`  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`course_id`)        REFERENCES `courses`(`id`),
  FOREIGN KEY (`academic_year_id`) REFERENCES `academic_year`(`academic_year_id`),
  FOREIGN KEY (`semester_id`)      REFERENCES `semesters`(`semester_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `evaluations` (`token`,`course_id`,`academic_year_id`,`semester_id`,`evaluation_date`) VALUES
-- BINT101 (evals 1-6)
('tk_11_1',1,3,6,'2026-06-01 09:10:00'),('tk_12_1',1,3,6,'2026-06-01 09:22:00'),
('tk_13_1',1,3,6,'2026-06-01 10:05:00'),('tk_14_1',1,3,6,'2026-06-02 08:45:00'),
('tk_15_1',1,3,6,'2026-06-02 09:30:00'),('tk_16_1',1,3,6,'2026-06-02 10:15:00'),
-- BINT102 (evals 7-13)
('tk_10_2',2,3,6,'2026-06-03 09:00:00'),('tk_11_2',2,3,6,'2026-06-03 09:20:00'),
('tk_12_2',2,3,6,'2026-06-03 09:40:00'),('tk_14_2',2,3,6,'2026-06-03 10:00:00'),
('tk_15_2',2,3,6,'2026-06-03 10:20:00'),('tk_16_2',2,3,6,'2026-06-03 10:40:00'),
('tk_17_2',2,3,6,'2026-06-03 11:00:00'),
-- BINT103 (evals 14-20)
('tk_10_3',3,3,6,'2026-06-04 09:00:00'),('tk_11_3',3,3,6,'2026-06-04 09:15:00'),
('tk_12_3',3,3,6,'2026-06-04 09:30:00'),('tk_13_3',3,3,6,'2026-06-04 09:45:00'),
('tk_14_3',3,3,6,'2026-06-04 10:00:00'),('tk_16_3',3,3,6,'2026-06-04 10:30:00'),
('tk_17_3',3,3,6,'2026-06-04 10:45:00'),
-- BINT201 (evals 21-24)
('tk_18_4',4,3,6,'2026-06-05 09:00:00'),('tk_19_4',4,3,6,'2026-06-05 09:20:00'),
('tk_20_4',4,3,6,'2026-06-05 09:40:00'),('tk_22_4',4,3,6,'2026-06-05 10:20:00'),
-- BINT202 (evals 25-28)
('tk_18_5',5,3,6,'2026-06-06 09:00:00'),('tk_19_5',5,3,6,'2026-06-06 09:20:00'),
('tk_21_5',5,3,6,'2026-06-06 10:00:00'),('tk_22_5',5,3,6,'2026-06-06 10:20:00'),
-- BINT301 (evals 29-31)
('tk_23_6',6,3,6,'2026-06-07 09:00:00'),('tk_24_6',6,3,6,'2026-06-07 09:20:00'),
('tk_25_6',6,3,6,'2026-06-07 09:40:00'),
-- BINT302 (evals 32-34)
('tk_23_7',7,3,6,'2026-06-08 09:00:00'),('tk_24_7',7,3,6,'2026-06-08 09:20:00'),
('tk_26_7',7,3,6,'2026-06-08 10:00:00'),
-- BEEE101 (evals 35-37)
('tk_27_9',9,3,6,'2026-06-09 09:00:00'),('tk_28_9',9,3,6,'2026-06-09 09:20:00'),
('tk_29_9',9,3,6,'2026-06-09 09:40:00'),
-- BEEE102 (evals 38-40)
('tk_27_10',10,3,6,'2026-06-10 09:00:00'),('tk_29_10',10,3,6,'2026-06-10 09:40:00'),
('tk_30_10',10,3,6,'2026-06-10 10:00:00');

-- ================================================================
-- responses  (20 questions x 40 evaluations = 800 rows)
-- Score legend:
--   BINT101 (Kwame): 4-5  evals  1-6
--   BINT102 (Ama):   4-5  evals  7-13
--   BINT103 (Kwame): 4-5  evals 14-20
--   BINT201 (Ama):   3-5  evals 21-24
--   BINT202 (Kwame): 3-4  evals 25-28
--   BINT301 (Kwame): 4-5  evals 29-31
--   BINT302 (Ama):   3-5  evals 32-34
--   BEEE101 (Kofi):  3-4  evals 35-37
--   BEEE102 (Kofi):  2-4  evals 38-40
-- ================================================================
DROP TABLE IF EXISTS `responses`;
CREATE TABLE `responses` (
  `id`             INT AUTO_INCREMENT PRIMARY KEY,
  `evaluation_id`  INT,
  `question_id`    INT,
  `response_value` TEXT,
  `created_at`     TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`evaluation_id`) REFERENCES `evaluations`(`evaluation_id`),
  FOREIGN KEY (`question_id`)   REFERENCES `evaluation_questions`(`question_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- BINT101 evals 1-6 (Kwame high)
INSERT INTO `responses` (`evaluation_id`,`question_id`,`response_value`) VALUES
(1,1,'5'),(1,2,'5'),(1,3,'4'),(1,4,'5'),(1,5,'5'),(1,6,'4'),(1,7,'5'),(1,8,'4'),(1,9,'4'),(1,10,'5'),(1,11,'5'),(1,12,'3'),(1,13,'4'),(1,14,'3'),(1,15,'4'),(1,16,'3'),(1,17,'3'),(1,18,'4'),(1,19,'3'),(1,20,'3'),
(2,1,'4'),(2,2,'5'),(2,3,'5'),(2,4,'4'),(2,5,'5'),(2,6,'5'),(2,7,'4'),(2,8,'5'),(2,9,'5'),(2,10,'4'),(2,11,'5'),(2,12,'4'),(2,13,'3'),(2,14,'4'),(2,15,'3'),(2,16,'4'),(2,17,'4'),(2,18,'3'),(2,19,'3'),(2,20,'4'),
(3,1,'5'),(3,2,'4'),(3,3,'4'),(3,4,'5'),(3,5,'4'),(3,6,'4'),(3,7,'5'),(3,8,'4'),(3,9,'4'),(3,10,'5'),(3,11,'5'),(3,12,'3'),(3,13,'3'),(3,14,'3'),(3,15,'4'),(3,16,'3'),(3,17,'3'),(3,18,'3'),(3,19,'3'),(3,20,'3'),
(4,1,'5'),(4,2,'5'),(4,3,'5'),(4,4,'4'),(4,5,'5'),(4,6,'5'),(4,7,'4'),(4,8,'5'),(4,9,'4'),(4,10,'4'),(4,11,'5'),(4,12,'4'),(4,13,'4'),(4,14,'3'),(4,15,'4'),(4,16,'4'),(4,17,'4'),(4,18,'4'),(4,19,'3'),(4,20,'3'),
(5,1,'4'),(5,2,'4'),(5,3,'4'),(5,4,'5'),(5,5,'4'),(5,6,'4'),(5,7,'4'),(5,8,'4'),(5,9,'5'),(5,10,'4'),(5,11,'5'),(5,12,'3'),(5,13,'3'),(5,14,'4'),(5,15,'3'),(5,16,'3'),(5,17,'3'),(5,18,'4'),(5,19,'3'),(5,20,'3'),
(6,1,'5'),(6,2,'5'),(6,3,'4'),(6,4,'4'),(6,5,'5'),(6,6,'4'),(6,7,'5'),(6,8,'3'),(6,9,'4'),(6,10,'4'),(6,11,'5'),(6,12,'3'),(6,13,'3'),(6,14,'3'),(6,15,'4'),(6,16,'3'),(6,17,'4'),(6,18,'3'),(6,19,'3'),(6,20,'3');

-- BINT102 evals 7-13 (Ama good)
INSERT INTO `responses` (`evaluation_id`,`question_id`,`response_value`) VALUES
(7,1,'4'),(7,2,'5'),(7,3,'4'),(7,4,'4'),(7,5,'5'),(7,6,'4'),(7,7,'4'),(7,8,'4'),(7,9,'4'),(7,10,'4'),(7,11,'5'),(7,12,'3'),(7,13,'3'),(7,14,'3'),(7,15,'4'),(7,16,'3'),(7,17,'3'),(7,18,'3'),(7,19,'3'),(7,20,'3'),
(8,1,'4'),(8,2,'4'),(8,3,'5'),(8,4,'4'),(8,5,'4'),(8,6,'5'),(8,7,'4'),(8,8,'4'),(8,9,'5'),(8,10,'4'),(8,11,'4'),(8,12,'4'),(8,13,'3'),(8,14,'4'),(8,15,'4'),(8,16,'4'),(8,17,'4'),(8,18,'4'),(8,19,'3'),(8,20,'3'),
(9,1,'5'),(9,2,'4'),(9,3,'4'),(9,4,'5'),(9,5,'4'),(9,6,'4'),(9,7,'5'),(9,8,'4'),(9,9,'4'),(9,10,'5'),(9,11,'5'),(9,12,'3'),(9,13,'4'),(9,14,'3'),(9,15,'3'),(9,16,'3'),(9,17,'3'),(9,18,'3'),(9,19,'4'),(9,20,'3'),
(10,1,'4'),(10,2,'4'),(10,3,'3'),(10,4,'4'),(10,5,'5'),(10,6,'4'),(10,7,'4'),(10,8,'5'),(10,9,'4'),(10,10,'3'),(10,11,'5'),(10,12,'3'),(10,13,'3'),(10,14,'3'),(10,15,'4'),(10,16,'3'),(10,17,'3'),(10,18,'3'),(10,19,'3'),(10,20,'3'),
(11,1,'5'),(11,2,'5'),(11,3,'4'),(11,4,'4'),(11,5,'5'),(11,6,'5'),(11,7,'4'),(11,8,'4'),(11,9,'5'),(11,10,'4'),(11,11,'5'),(11,12,'4'),(11,13,'4'),(11,14,'4'),(11,15,'4'),(11,16,'4'),(11,17,'4'),(11,18,'4'),(11,19,'3'),(11,20,'4'),
(12,1,'4'),(12,2,'4'),(12,3,'4'),(12,4,'4'),(12,5,'4'),(12,6,'4'),(12,7,'4'),(12,8,'3'),(12,9,'4'),(12,10,'4'),(12,11,'4'),(12,12,'3'),(12,13,'3'),(12,14,'3'),(12,15,'3'),(12,16,'3'),(12,17,'3'),(12,18,'3'),(12,19,'3'),(12,20,'3'),
(13,1,'5'),(13,2,'4'),(13,3,'5'),(13,4,'5'),(13,5,'4'),(13,6,'4'),(13,7,'5'),(13,8,'4'),(13,9,'4'),(13,10,'5'),(13,11,'5'),(13,12,'3'),(13,13,'3'),(13,14,'3'),(13,15,'4'),(13,16,'3'),(13,17,'4'),(13,18,'3'),(13,19,'3'),(13,20,'3');

-- BINT103 evals 14-20 (Kwame high)
INSERT INTO `responses` (`evaluation_id`,`question_id`,`response_value`) VALUES
(14,1,'5'),(14,2,'5'),(14,3,'5'),(14,4,'4'),(14,5,'5'),(14,6,'4'),(14,7,'4'),(14,8,'5'),(14,9,'4'),(14,10,'4'),(14,11,'5'),(14,12,'3'),(14,13,'3'),(14,14,'3'),(14,15,'3'),(14,16,'3'),(14,17,'3'),(14,18,'3'),(14,19,'3'),(14,20,'3'),
(15,1,'4'),(15,2,'4'),(15,3,'4'),(15,4,'5'),(15,5,'4'),(15,6,'5'),(15,7,'4'),(15,8,'4'),(15,9,'5'),(15,10,'4'),(15,11,'5'),(15,12,'4'),(15,13,'4'),(15,14,'4'),(15,15,'4'),(15,16,'4'),(15,17,'4'),(15,18,'4'),(15,19,'4'),(15,20,'3'),
(16,1,'5'),(16,2,'4'),(16,3,'4'),(16,4,'4'),(16,5,'5'),(16,6,'4'),(16,7,'5'),(16,8,'4'),(16,9,'4'),(16,10,'5'),(16,11,'5'),(16,12,'3'),(16,13,'3'),(16,14,'3'),(16,15,'3'),(16,16,'3'),(16,17,'3'),(16,18,'3'),(16,19,'3'),(16,20,'3'),
(17,1,'4'),(17,2,'5'),(17,3,'5'),(17,4,'4'),(17,5,'4'),(17,6,'4'),(17,7,'4'),(17,8,'4'),(17,9,'4'),(17,10,'4'),(17,11,'4'),(17,12,'3'),(17,13,'3'),(17,14,'3'),(17,15,'4'),(17,16,'3'),(17,17,'3'),(17,18,'3'),(17,19,'3'),(17,20,'3'),
(18,1,'5'),(18,2,'5'),(18,3,'4'),(18,4,'5'),(18,5,'5'),(18,6,'5'),(18,7,'5'),(18,8,'4'),(18,9,'5'),(18,10,'5'),(18,11,'5'),(18,12,'4'),(18,13,'4'),(18,14,'3'),(18,15,'4'),(18,16,'3'),(18,17,'4'),(18,18,'4'),(18,19,'3'),(18,20,'3'),
(19,1,'4'),(19,2,'4'),(19,3,'4'),(19,4,'4'),(19,5,'4'),(19,6,'4'),(19,7,'4'),(19,8,'4'),(19,9,'4'),(19,10,'4'),(19,11,'4'),(19,12,'3'),(19,13,'3'),(19,14,'3'),(19,15,'3'),(19,16,'3'),(19,17,'3'),(19,18,'3'),(19,19,'3'),(19,20,'3'),
(20,1,'5'),(20,2,'5'),(20,3,'5'),(20,4,'5'),(20,5,'5'),(20,6,'4'),(20,7,'5'),(20,8,'4'),(20,9,'5'),(20,10,'4'),(20,11,'5'),(20,12,'3'),(20,13,'3'),(20,14,'4'),(20,15,'4'),(20,16,'3'),(20,17,'3'),(20,18,'4'),(20,19,'3'),(20,20,'3');

-- BINT201 evals 21-24 (Ama mid-high)
INSERT INTO `responses` (`evaluation_id`,`question_id`,`response_value`) VALUES
(21,1,'4'),(21,2,'4'),(21,3,'3'),(21,4,'4'),(21,5,'5'),(21,6,'4'),(21,7,'3'),(21,8,'4'),(21,9,'4'),(21,10,'3'),(21,11,'5'),(21,12,'3'),(21,13,'3'),(21,14,'3'),(21,15,'4'),(21,16,'3'),(21,17,'3'),(21,18,'3'),(21,19,'3'),(21,20,'3'),
(22,1,'5'),(22,2,'4'),(22,3,'4'),(22,4,'5'),(22,5,'4'),(22,6,'5'),(22,7,'4'),(22,8,'4'),(22,9,'5'),(22,10,'4'),(22,11,'5'),(22,12,'4'),(22,13,'4'),(22,14,'3'),(22,15,'4'),(22,16,'4'),(22,17,'4'),(22,18,'4'),(22,19,'3'),(22,20,'3'),
(23,1,'3'),(23,2,'4'),(23,3,'4'),(23,4,'3'),(23,5,'4'),(23,6,'3'),(23,7,'4'),(23,8,'3'),(23,9,'3'),(23,10,'4'),(23,11,'4'),(23,12,'3'),(23,13,'3'),(23,14,'3'),(23,15,'3'),(23,16,'3'),(23,17,'3'),(23,18,'3'),(23,19,'3'),(23,20,'3'),
(24,1,'5'),(24,2,'5'),(24,3,'4'),(24,4,'4'),(24,5,'5'),(24,6,'4'),(24,7,'5'),(24,8,'5'),(24,9,'4'),(24,10,'5'),(24,11,'5'),(24,12,'3'),(24,13,'3'),(24,14,'4'),(24,15,'4'),(24,16,'3'),(24,17,'4'),(24,18,'3'),(24,19,'3'),(24,20,'3');

-- BINT202 evals 25-28 (Kwame mid)
INSERT INTO `responses` (`evaluation_id`,`question_id`,`response_value`) VALUES
(25,1,'3'),(25,2,'4'),(25,3,'3'),(25,4,'4'),(25,5,'3'),(25,6,'3'),(25,7,'4'),(25,8,'3'),(25,9,'4'),(25,10,'3'),(25,11,'4'),(25,12,'3'),(25,13,'3'),(25,14,'3'),(25,15,'3'),(25,16,'3'),(25,17,'3'),(25,18,'3'),(25,19,'3'),(25,20,'3'),
(26,1,'4'),(26,2,'3'),(26,3,'4'),(26,4,'3'),(26,5,'4'),(26,6,'4'),(26,7,'3'),(26,8,'4'),(26,9,'3'),(26,10,'4'),(26,11,'4'),(26,12,'3'),(26,13,'3'),(26,14,'3'),(26,15,'3'),(26,16,'3'),(26,17,'3'),(26,18,'3'),(26,19,'3'),(26,20,'3'),
(27,1,'3'),(27,2,'3'),(27,3,'3'),(27,4,'4'),(27,5,'3'),(27,6,'3'),(27,7,'3'),(27,8,'3'),(27,9,'3'),(27,10,'3'),(27,11,'4'),(27,12,'3'),(27,13,'3'),(27,14,'3'),(27,15,'3'),(27,16,'3'),(27,17,'3'),(27,18,'3'),(27,19,'3'),(27,20,'3'),
(28,1,'4'),(28,2,'4'),(28,3,'4'),(28,4,'3'),(28,5,'4'),(28,6,'4'),(28,7,'4'),(28,8,'3'),(28,9,'4'),(28,10,'3'),(28,11,'5'),(28,12,'4'),(28,13,'3'),(28,14,'3'),(28,15,'4'),(28,16,'3'),(28,17,'3'),(28,18,'4'),(28,19,'3'),(28,20,'3');

-- BINT301 evals 29-31 (Kwame high)
INSERT INTO `responses` (`evaluation_id`,`question_id`,`response_value`) VALUES
(29,1,'5'),(29,2,'5'),(29,3,'5'),(29,4,'5'),(29,5,'5'),(29,6,'5'),(29,7,'5'),(29,8,'4'),(29,9,'5'),(29,10,'5'),(29,11,'5'),(29,12,'4'),(29,13,'4'),(29,14,'4'),(29,15,'4'),(29,16,'4'),(29,17,'4'),(29,18,'4'),(29,19,'3'),(29,20,'4'),
(30,1,'4'),(30,2,'5'),(30,3,'4'),(30,4,'5'),(30,5,'4'),(30,6,'4'),(30,7,'5'),(30,8,'4'),(30,9,'4'),(30,10,'5'),(30,11,'5'),(30,12,'3'),(30,13,'3'),(30,14,'3'),(30,15,'4'),(30,16,'3'),(30,17,'4'),(30,18,'3'),(30,19,'3'),(30,20,'3'),
(31,1,'5'),(31,2,'4'),(31,3,'5'),(31,4,'4'),(31,5,'5'),(31,6,'5'),(31,7,'4'),(31,8,'5'),(31,9,'5'),(31,10,'4'),(31,11,'5'),(31,12,'4'),(31,13,'4'),(31,14,'4'),(31,15,'3'),(31,16,'4'),(31,17,'3'),(31,18,'4'),(31,19,'3'),(31,20,'3');

-- BINT302 evals 32-34 (Ama varied)
INSERT INTO `responses` (`evaluation_id`,`question_id`,`response_value`) VALUES
(32,1,'5'),(32,2,'4'),(32,3,'5'),(32,4,'4'),(32,5,'5'),(32,6,'4'),(32,7,'4'),(32,8,'5'),(32,9,'4'),(32,10,'4'),(32,11,'5'),(32,12,'3'),(32,13,'3'),(32,14,'3'),(32,15,'4'),(32,16,'3'),(32,17,'3'),(32,18,'3'),(32,19,'3'),(32,20,'3'),
(33,1,'3'),(33,2,'3'),(33,3,'4'),(33,4,'3'),(33,5,'4'),(33,6,'3'),(33,7,'4'),(33,8,'3'),(33,9,'3'),(33,10,'4'),(33,11,'4'),(33,12,'3'),(33,13,'3'),(33,14,'3'),(33,15,'3'),(33,16,'3'),(33,17,'3'),(33,18,'3'),(33,19,'3'),(33,20,'3'),
(34,1,'4'),(34,2,'5'),(34,3,'3'),(34,4,'5'),(34,5,'4'),(34,6,'5'),(34,7,'3'),(34,8,'4'),(34,9,'5'),(34,10,'3'),(34,11,'5'),(34,12,'4'),(34,13,'3'),(34,14,'3'),(34,15,'4'),(34,16,'3'),(34,17,'4'),(34,18,'3'),(34,19,'3'),(34,20,'3');

-- BEEE101 evals 35-37 (Kofi mid)
INSERT INTO `responses` (`evaluation_id`,`question_id`,`response_value`) VALUES
(35,1,'3'),(35,2,'3'),(35,3,'3'),(35,4,'4'),(35,5,'3'),(35,6,'3'),(35,7,'3'),(35,8,'3'),(35,9,'3'),(35,10,'3'),(35,11,'4'),(35,12,'3'),(35,13,'3'),(35,14,'3'),(35,15,'3'),(35,16,'3'),(35,17,'3'),(35,18,'3'),(35,19,'3'),(35,20,'3'),
(36,1,'4'),(36,2,'3'),(36,3,'4'),(36,4,'3'),(36,5,'4'),(36,6,'4'),(36,7,'3'),(36,8,'4'),(36,9,'4'),(36,10,'3'),(36,11,'4'),(36,12,'3'),(36,13,'3'),(36,14,'3'),(36,15,'3'),(36,16,'3'),(36,17,'3'),(36,18,'3'),(36,19,'3'),(36,20,'3'),
(37,1,'3'),(37,2,'4'),(37,3,'3'),(37,4,'3'),(37,5,'3'),(37,6,'3'),(37,7,'4'),(37,8,'3'),(37,9,'3'),(37,10,'4'),(37,11,'4'),(37,12,'3'),(37,13,'3'),(37,14,'3'),(37,15,'4'),(37,16,'3'),(37,17,'3'),(37,18,'3'),(37,19,'3'),(37,20,'3');

-- BEEE102 evals 38-40 (Kofi lower mid)
INSERT INTO `responses` (`evaluation_id`,`question_id`,`response_value`) VALUES
(38,1,'3'),(38,2,'2'),(38,3,'3'),(38,4,'3'),(38,5,'2'),(38,6,'3'),(38,7,'2'),(38,8,'3'),(38,9,'3'),(38,10,'2'),(38,11,'3'),(38,12,'3'),(38,13,'3'),(38,14,'3'),(38,15,'3'),(38,16,'3'),(38,17,'3'),(38,18,'3'),(38,19,'3'),(38,20,'3'),
(39,1,'4'),(39,2,'3'),(39,3,'3'),(39,4,'3'),(39,5,'3'),(39,6,'3'),(39,7,'3'),(39,8,'2'),(39,9,'3'),(39,10,'3'),(39,11,'4'),(39,12,'3'),(39,13,'3'),(39,14,'3'),(39,15,'3'),(39,16,'3'),(39,17,'3'),(39,18,'3'),(39,19,'3'),(39,20,'3'),
(40,1,'3'),(40,2,'3'),(40,3,'2'),(40,4,'4'),(40,5,'3'),(40,6,'3'),(40,7,'3'),(40,8,'3'),(40,9,'2'),(40,10,'3'),(40,11,'3'),(40,12,'3'),(40,13,'2'),(40,14,'3'),(40,15,'3'),(40,16,'3'),(40,17,'3'),(40,18,'3'),(40,19,'3'),(40,20,'3');

-- ================================================================
-- audit_logs
-- ================================================================
DROP TABLE IF EXISTS `audit_logs`;
CREATE TABLE `audit_logs` (
  `log_id`      INT AUTO_INCREMENT PRIMARY KEY,
  `user_id`     INT,
  `action_type` VARCHAR(50),
  `table_name`  VARCHAR(50),
  `record_id`   INT,
  `old_values`  TEXT,
  `new_values`  TEXT,
  `ip_address`  VARCHAR(45),
  `created_at`  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`user_id`) REFERENCES `user_details`(`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ================================================================
-- login_attempts
-- ================================================================
DROP TABLE IF EXISTS `login_attempts`;
CREATE TABLE `login_attempts` (
  `id`                 INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `ip_address`         VARCHAR(45)  NOT NULL,
  `username_attempted` VARCHAR(100) NOT NULL DEFAULT '',
  `attempted_at`       DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `idx_ip_attempted_at` (`ip_address`,`attempted_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ================================================================
-- password_reset_tokens
-- ================================================================
DROP TABLE IF EXISTS `password_reset_tokens`;
CREATE TABLE `password_reset_tokens` (
  `id`         INT NOT NULL AUTO_INCREMENT,
  `user_id`    INT NOT NULL,
  `token_hash` VARCHAR(64) UNIQUE,
  `expires_at` DATETIME NOT NULL,
  `used_at`    DATETIME NULL DEFAULT NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `ip_address` VARCHAR(45) NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_token_hash` (`token_hash`),
  INDEX `idx_user_id`    (`user_id`),
  INDEX `idx_expires_at` (`expires_at`),
  FOREIGN KEY (`user_id`) REFERENCES `user_details`(`user_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ================================================================
-- VIEW: view_active_period
-- ================================================================
DROP VIEW IF EXISTS `view_active_period`;
CREATE VIEW `view_active_period` AS
SELECT
    ay.academic_year_id,
    ay.year_label   AS academic_year,
    s.semester_id,
    s.semester_name,
    s.semester_value
FROM `academic_year` ay
JOIN `semesters` s ON s.academic_year_id = ay.academic_year_id
WHERE ay.is_active = 1
  AND s.is_active  = 1
LIMIT 1;

-- ================================================================
SET foreign_key_checks = 1;
COMMIT;

-- ================================================================
-- TEST ACCOUNT SUMMARY  (password for all accounts: password)
-- ----------------------------------------------------------------
-- Role       Username      Dept   Notes
-- admin      admin         -      Full system access
-- quality    quality1      ICT    Quality reports
-- hod        hod_ict       ICT    Manages ICT
-- hod        hod_eee       EEE    Manages EEE
-- secretary  sec_ict       ICT
-- secretary  sec_eee       EEE
-- lecturer   lec_kwame     ICT    BINT101,103,202,301 / advisor BIT28,BIT26
-- lecturer   lec_ama       ICT    BINT102,201,302,401 / advisor BIT27,BCS28
-- lecturer   lec_kofi      EEE    BEEE101,102         / advisor EEE28,EEE27
-- student    s.akosua.a    ICT    BIT28 / Level 100
-- student    s.irene.q     ICT    BIT27 / Level 200
-- student    s.nana.o      ICT    BIT26 / Level 300
-- student    s.samuel.d    EEE    EEE28 / Level 100
-- ----------------------------------------------------------------
-- ACTIVE PERIOD: 2025/2026 Second Semester
--   academic_year_id=3  |  semester_id=6
-- ----------------------------------------------------------------
-- REPORT DATA COVERAGE
--   Courses with evaluations : BINT101-103, BINT201-202,
--                               BINT301-302, BEEE101, BEEE102
--   Token completion rates   : 75-88% (some intentionally pending)
--   Score spread             : BEEE102 avg ~2.9 -> BINT101/301 avg ~4.5
--   ICT students             : 24  |  EEE students: 7
--   Levels with data         : 100, 200, 300 (400 has course, no tokens)
-- ================================================================
