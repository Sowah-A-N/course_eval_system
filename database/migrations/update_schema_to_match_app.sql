-- ============================================================
-- Schema Update: Align DB with current PHP application
-- Generated : 2026-03-24
-- Apply to  : course_evaluation
--
-- Changes in this script
-- ──────────────────────
-- 1. CREATE  login_attempts          (new table — login.php rate limiting)
-- 2. ALTER   classes                 (add class_code, advisor_user_id)
-- 3. ALTER   courses                 (add credit_hours)
-- 4. INSERT  roles                   (add missing 'quality' role, role_id=6)
--
-- Safe to re-run: CREATE TABLE uses IF NOT EXISTS; ALTER columns use
-- IF NOT EXISTS (MySQL 8.0+); INSERT uses INSERT IGNORE.
-- ============================================================

SET NAMES utf8mb4;
SET foreign_key_checks = 0;

-- ============================================================
-- 1. NEW TABLE: login_attempts
--
--    Required by login.php for IP-based brute-force protection.
--    login.php queries this table on every POST to check whether
--    MAX_LOGIN_ATTEMPTS has been reached within LOGIN_LOCKOUT_TIME
--    seconds, and inserts a row on every failed authentication.
-- ============================================================

CREATE TABLE IF NOT EXISTS `login_attempts` (
  `id`                 INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `ip_address`         VARCHAR(45)   NOT NULL               COMMENT 'Supports IPv4 and IPv6',
  `username_attempted` VARCHAR(100)  NOT NULL DEFAULT ''    COMMENT 'Submitted identifier (not verified)',
  `attempted_at`       DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `idx_ip_attempted_at` (`ip_address`, `attempted_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Tracks failed login attempts for IP-based rate limiting';

-- ============================================================
-- 2. ALTER TABLE: classes
--
-- 2a. ADD class_code VARCHAR(50)
--     ─────────────────────────
--     Referenced in every CRUD file for classes:
--       admin/classes/create.php  → INSERT INTO classes (class_name, class_code, …)
--       admin/classes/edit.php    → UPDATE classes SET class_code=? …
--       admin/classes/list.php    → SELECT … c.class_code … WHERE c.class_code LIKE ?
--       admin/classes/delete.php  → display only
--       admin/advisors/list.php   → SELECT … c.class_code …
--       admin/advisors/assign.php → display only (reads class_code after SELECT *)
--       secretary/classes/*       → same CREATE / EDIT / LIST / DELETE pattern
--     Uniqueness enforced in PHP as (class_code, department_id).
--
-- 2b. ADD advisor_user_id INT NULL
--     ─────────────────────────────
--     Referenced in advisor assignment management:
--       admin/advisors/assign.php   → UPDATE classes SET advisor_user_id=? WHERE t_id=?
--       admin/advisors/unassign.php → UPDATE classes SET advisor_user_id=NULL WHERE t_id=?
--       admin/advisors/list.php     → LEFT JOIN user_details u ON c.advisor_user_id=u.user_id
--                                     + CASE WHEN c.advisor_user_id IS NOT NULL …
--       admin/classes/list.php      → LEFT JOIN user_details u ON c.advisor_user_id=u.user_id
-- ============================================================

-- class_code
ALTER TABLE `classes`
  ADD COLUMN IF NOT EXISTS `class_code` VARCHAR(50) NOT NULL DEFAULT ''
    COMMENT 'Short unique code for this class within its department'
    AFTER `class_name`;

-- Backfill existing rows so the NOT NULL constraint is immediately satisfied.
-- Derives a code from class_name (admin should update via UI to proper values).
UPDATE `classes`
SET `class_code` = UPPER(REPLACE(LEFT(`class_name`, 20), ' ', ''))
WHERE `class_code` = '';

-- Unique constraint matching the PHP duplicate-check query:
--   SELECT t_id FROM classes WHERE class_code=? AND department_id=?
ALTER TABLE `classes`
  ADD UNIQUE INDEX IF NOT EXISTS `uq_class_code_dept` (`class_code`, `department_id`);

-- advisor_user_id
ALTER TABLE `classes`
  ADD COLUMN IF NOT EXISTS `advisor_user_id` INT NULL DEFAULT NULL
    COMMENT 'FK → user_details.user_id; set by admin/advisors/assign.php'
    AFTER `department_id`;

ALTER TABLE `classes`
  ADD INDEX IF NOT EXISTS `idx_advisor_user_id` (`advisor_user_id`);

-- ============================================================
-- 3. ALTER TABLE: courses — ADD credit_hours
--
--    Actively used (not commented out) in:
--      secretary/courses/create.php →
--          INSERT INTO courses (course_code,name,credit_hours,level_id,semester_id,department_id)
--      secretary/courses/edit.php   →
--          UPDATE courses SET course_code=?,name=?,credit_hours=?,level_id=?,semester_id=?
--      secretary/exports/index.php  →
--          SELECT c.credit_hours … fputcsv(…, $row['credit_hours'], …)
--
--    Note: admin/courses/create.php does NOT include credit_hours in its INSERT,
--    so DEFAULT 0 is used for admin-created courses (secretary can edit afterward).
-- ============================================================

ALTER TABLE `courses`
  ADD COLUMN IF NOT EXISTS `credit_hours` TINYINT UNSIGNED NOT NULL DEFAULT 3
    COMMENT 'Number of credit hours for this course (1-10)'
    AFTER `semester_id`;

-- ============================================================
-- 4. INSERT roles — add missing 'quality' role (role_id = 6)
--
--    ROLE_QUALITY = 6 is defined in config/constants.php and used
--    throughout the application (session checks, role-based redirects
--    in login.php, quality/ dashboard).  The roles table only seeds
--    roles 1–5, so a quality user who logs in gets role_id=6 from
--    user_details but finds no matching row in roles — the login
--    switch falls through to the default "Invalid user role" error.
-- ============================================================

INSERT IGNORE INTO `roles` (`role_id`, `role_name`)
VALUES (6, 'quality');

-- ============================================================

SET foreign_key_checks = 1;

-- ============================================================
-- Summary of changes
-- ──────────────────
-- Table            | Type   | Detail
-- ─────────────────┼────────┼──────────────────────────────────────────────
-- login_attempts   | CREATE | New table for login rate limiting
-- classes          | ALTER  | +class_code VARCHAR(50), UNIQUE(code,dept)
--                  |        | +advisor_user_id INT NULL, INDEX
-- courses          | ALTER  | +credit_hours TINYINT UNSIGNED DEFAULT 3
-- roles            | INSERT | role_id=6 'quality'  (INSERT IGNORE)
-- ============================================================
