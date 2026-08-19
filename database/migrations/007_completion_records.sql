-- 007_completion_records.sql  (Phase 2 of the evaluation redesign)
-- Compatible with MariaDB 5.5.68+. Run ONCE per environment, AFTER 006.
--
-- Non-destructive: adds the columns and table that the new (tokenless) flow
-- needs. The existing `evaluation_tokens` table is left in place and untouched
-- so nothing breaks until the Phase 3 code switches over. See
-- docs/EVALUATION_REDESIGN.md.

-- ---------------------------------------------------------------------------
-- evaluations becomes a purely anonymous answer container:
--   scope         -> 'course' (per course) or 'administrative' (once per semester)
--   course_id     -> now NULL-able (NULL/administrative evaluations have no course)
--   class_id      -> set on administrative evaluations so the class-advisor rating
--                    can be attributed to an advisor (via classes.advisor_user_id)
--                    WITHOUT linking back to a student
--   department_id -> optional grouping for institutional reports
--   token         -> made NULL-able; the new flow stops writing it (dropped in cleanup)
-- ---------------------------------------------------------------------------
ALTER TABLE `evaluations`
  ADD COLUMN `scope` ENUM('course','administrative') NOT NULL DEFAULT 'course' AFTER `evaluation_id`,
  ADD COLUMN `class_id` INT(11) NULL AFTER `course_id`,
  ADD COLUMN `department_id` INT(11) NULL AFTER `class_id`,
  MODIFY COLUMN `course_id` INT(11) NULL,
  MODIFY COLUMN `token` VARCHAR(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL,
  ADD KEY `idx_scope` (`scope`);

-- ---------------------------------------------------------------------------
-- evaluation_completions: records THAT a student completed an evaluation
-- (eligibility + one-time-use), holding NO answers and no link to the answer
-- rows. course_id = 0 marks the once-per-semester administrative completion,
-- so the UNIQUE key enforces both "one per course" and "one administrative per
-- semester" (MariaDB 5.5 allows multiple NULLs in a UNIQUE index, hence the 0
-- sentinel rather than NULL).
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
