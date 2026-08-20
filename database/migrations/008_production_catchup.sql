-- 008_production_catchup.sql
-- Single catch-up migration: brings a BASELINE production database (the schema
-- exported before any of 005/006/007 were applied) up to the current tokenless,
-- two-tier-questions model in one run.
--
-- Target: MariaDB 5.5.x (also fine on MySQL 5.7/8). Run ONCE, against a database
-- that does NOT already have these columns/tables. It is NOT idempotent — MariaDB
-- 5.5 has no "ADD COLUMN IF NOT EXISTS", so do not re-run it. TEST ON A STAGING
-- COPY FIRST, then back up production before applying.
--
-- Equivalent to migrations 006 + 007 plus the response_value typing from 005.
-- The token-uniqueness constraints from 005/006-era work are intentionally left
-- out: the application no longer uses evaluation tokens, and evaluation_tokens /
-- evaluations.token are scheduled for removal.

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";

-- ---------------------------------------------------------------------------
-- 1. Ratings are whole numbers 1..5, not free text.
--    Existing values are the strings '1'..'5' and cast cleanly to TINYINT.
-- ---------------------------------------------------------------------------
ALTER TABLE `responses`
  MODIFY COLUMN `response_value` TINYINT NOT NULL;

-- ---------------------------------------------------------------------------
-- 2. Classify each question: 'course' (per course) or 'administrative'
--    (once per semester — services + class advisor).
-- ---------------------------------------------------------------------------
ALTER TABLE `evaluation_questions`
  ADD COLUMN `scope` ENUM('course','administrative') NOT NULL DEFAULT 'course' AFTER `category`;

UPDATE `evaluation_questions`
   SET `scope` = 'administrative'
 WHERE `category` IN ('Registry','Accounts','Library','Administration','Sickbay','Washroom & Surroundings');

UPDATE `evaluation_questions`
   SET `scope` = 'administrative'
 WHERE `question_text` LIKE '%class advisor%';

-- ---------------------------------------------------------------------------
-- 3. evaluations becomes an anonymous answer container:
--      scope         -> 'course' or 'administrative'
--      course_id     -> now NULL-able (administrative evaluations have no course)
--      class_id      -> administrative evals only (attributes the advisor rating)
--      department_id -> administrative evals only (institutional grouping)
--      token         -> made NULL-able; the app no longer writes it
--    Existing rows default to scope='course' (they are historical course evals).
-- ---------------------------------------------------------------------------
ALTER TABLE `evaluations`
  ADD COLUMN `scope` ENUM('course','administrative') NOT NULL DEFAULT 'course' AFTER `evaluation_id`,
  ADD COLUMN `class_id` INT(11) NULL AFTER `course_id`,
  ADD COLUMN `department_id` INT(11) NULL AFTER `class_id`,
  MODIFY COLUMN `course_id` INT(11) NULL,
  MODIFY COLUMN `token` VARCHAR(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL,
  ADD KEY `idx_scope` (`scope`);

-- ---------------------------------------------------------------------------
-- 4. evaluation_completions: records THAT a student completed an evaluation
--    (eligibility + one-time-use), with NO answers and no link to the answer
--    rows. course_id = 0 marks the once-per-semester administrative completion,
--    so the UNIQUE key enforces both "one per course" and "one administrative
--    per semester" (MariaDB 5.5 allows multiple NULLs in a UNIQUE index, hence
--    the 0 sentinel rather than NULL).
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `evaluation_completions` (
  `completion_id`    INT(11) NOT NULL AUTO_INCREMENT,
  `student_user_id`  INT(11) NOT NULL,
  `scope`            ENUM('course','administrative') NOT NULL DEFAULT 'course',
  `course_id`        INT(11) NOT NULL DEFAULT 0 COMMENT '0 = administrative (not course-specific)',
  `academic_year_id` INT(11) NOT NULL,
  `semester_id`      INT(11) NOT NULL,
  `completed_at`     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`completion_id`),
  UNIQUE KEY `uq_completion` (`student_user_id`,`course_id`,`academic_year_id`,`semester_id`),
  KEY `idx_student` (`student_user_id`),
  KEY `idx_period` (`academic_year_id`,`semester_id`),
  KEY `idx_scope` (`scope`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- Post-run sanity checks (optional — run manually and eyeball):
--   SHOW COLUMNS FROM evaluations LIKE 'scope';
--   SHOW COLUMNS FROM evaluation_questions LIKE 'scope';
--   SHOW COLUMNS FROM responses LIKE 'response_value';
--   SHOW TABLES LIKE 'evaluation_completions';
--   SELECT scope, COUNT(*) FROM evaluation_questions GROUP BY scope;
-- ---------------------------------------------------------------------------
