-- =====================================================================
-- 010_production_full_catchup.sql
-- =====================================================================
-- COMPLETE, SINGLE-RUN catch-up migration.
--
-- Brings a BASELINE production database (the schema exported before any of
-- migrations 005/006/007 were applied, and still carrying the old evaluation
-- token system) all the way up to the CURRENT application model: two-tier
-- questions, anonymous evaluations, completion records, and NO tokens.
--
-- This ONE script is equivalent to running 005(response typing) + 006 + 007 +
-- 009 in order. If you run this, do NOT also run 008 or 009 — this supersedes
-- them for a fresh baseline.
--
-- Target:  MariaDB 5.5.x (also fine on MySQL 5.7 / 8.0).
-- Safety:  NOT idempotent (MariaDB 5.5 has no ADD/DROP COLUMN IF [NOT] EXISTS).
--          Run EXACTLY ONCE, on a database that still has the baseline shape.
--          >>> BACK UP PRODUCTION AND TEST ON A STAGING COPY FIRST. <<<
--
-- What it changes (nothing else in the schema needs to change — login_attempts,
-- password_reset_tokens, questions_archive and all views already exist in the
-- baseline):
--   1. responses.response_value        TEXT  -> TINYINT
--   2. evaluation_questions.scope       (new) + back-fill administrative rows
--   3. evaluations                      + scope, class_id, department_id;
--                                        course_id -> NULL-able
--   4. evaluation_completions           (new table)
--   5. token machinery                  DROP evaluations.token + evaluation_tokens
-- =====================================================================

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";

START TRANSACTION;

-- ---------------------------------------------------------------------------
-- 1. Ratings are whole numbers 1..5, not free text.
--    Baseline stores them as TEXT ('1'..'5'), which casts cleanly to TINYINT.
--    (If any non-numeric ratings exist, clean them BEFORE running this.)
-- ---------------------------------------------------------------------------
ALTER TABLE `responses`
  MODIFY COLUMN `response_value` TINYINT NOT NULL;

-- ---------------------------------------------------------------------------
-- 2. Classify each question: 'course' (per course) or 'administrative'
--    (answered once per semester — central services + the class advisor).
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
--      class_id      -> administrative evals only (attributes the advisor rating
--                       via classes.advisor_user_id, without re-linking a student)
--      department_id -> administrative evals only (institutional grouping)
--    Existing rows keep scope='course' (they are historical course evaluations).
--    The token column is intentionally NOT touched here — it is dropped in step 5.
-- ---------------------------------------------------------------------------
ALTER TABLE `evaluations`
  ADD COLUMN `scope` ENUM('course','administrative') NOT NULL DEFAULT 'course' AFTER `evaluation_id`,
  ADD COLUMN `class_id` INT(11) NULL AFTER `course_id`,
  ADD COLUMN `department_id` INT(11) NULL AFTER `class_id`,
  MODIFY COLUMN `course_id` INT(11) NULL,
  ADD KEY `idx_scope` (`scope`);

-- ---------------------------------------------------------------------------
-- 4. evaluation_completions: records THAT a student completed an evaluation
--    (eligibility + one-time use), with NO answers and no link to the answer
--    rows, so submissions stay anonymous. course_id = 0 marks the once-per-
--    semester administrative completion, so the UNIQUE key enforces both
--    "one per course" and "one administrative per semester". (MariaDB 5.5
--    allows multiple NULLs in a UNIQUE index, hence the 0 sentinel, not NULL.)
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
-- 5. Remove the old token machinery. The application no longer issues or reads
--    tokens; eligibility is computed live and one-time use is enforced by
--    evaluation_completions (step 4).
--    - evaluations has no foreign keys, so dropping the token column also drops
--      its idx_token index automatically.
--    - Nothing references evaluation_tokens, so the table drops cleanly.
-- ---------------------------------------------------------------------------
ALTER TABLE `evaluations` DROP COLUMN `token`;

DROP TABLE IF EXISTS `evaluation_tokens`;

COMMIT;

-- ---------------------------------------------------------------------------
-- Post-run sanity checks (run manually and eyeball):
--   SHOW COLUMNS FROM responses LIKE 'response_value';        -- tinyint
--   SHOW COLUMNS FROM evaluation_questions LIKE 'scope';      -- enum
--   SHOW COLUMNS FROM evaluations LIKE 'scope';               -- enum
--   SHOW COLUMNS FROM evaluations LIKE 'token';               -- (empty = gone)
--   SHOW TABLES LIKE 'evaluation_completions';                -- present
--   SHOW TABLES LIKE 'evaluation_tokens';                     -- (empty = gone)
--   SELECT scope, COUNT(*) FROM evaluation_questions GROUP BY scope;
-- ---------------------------------------------------------------------------
