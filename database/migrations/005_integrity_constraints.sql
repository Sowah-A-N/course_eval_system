-- 005_integrity_constraints.sql
-- Data-integrity hardening. Compatible with MariaDB 5.5.68+ and MySQL 5.7/8.
-- Run ONCE against the course_evaluation database, e.g.:
--   mysql -u <user> -p course_evaluation < database/migrations/005_integrity_constraints.sql
--
-- Idempotency note: ALTER ... ADD UNIQUE will error if the constraint already
-- exists — that just means the migration has already been applied.

-- ---------------------------------------------------------------------------
-- I4: evaluation ratings are 1..5 integers, not free text.
-- The application already validates 1..5 on submit; this makes the column
-- refuse non-numeric data at the storage layer. (MariaDB 5.5 does not enforce
-- CHECK constraints, so the TINYINT type is the guard.)
-- Existing values are the strings '1'..'5' and cast cleanly to TINYINT.
-- ---------------------------------------------------------------------------
ALTER TABLE `responses`
    MODIFY COLUMN `response_value` TINYINT NOT NULL;

-- ---------------------------------------------------------------------------
-- I3: a token maps to at most one evaluation (defence-in-depth against a
-- double submission slipping past the application's FOR UPDATE guard).
-- If this ALTER fails on a duplicate, investigate the offending tokens first
-- (do NOT blindly delete evaluation data).
-- ---------------------------------------------------------------------------
ALTER TABLE `evaluations`
    ADD UNIQUE KEY `uq_evaluations_token` (`token`);

-- ---------------------------------------------------------------------------
-- I6: prevent duplicate PENDING tokens for the same student/course/period.
-- Used tokens keep student_user_id populated here; the uniqueness only needs to
-- cover pending issuance. Any pre-existing pending duplicates are removed first
-- (keeping the earliest row) so the ADD UNIQUE can succeed.
-- ---------------------------------------------------------------------------
DELETE t1 FROM `evaluation_tokens` t1
    INNER JOIN `evaluation_tokens` t2
        ON  t1.`student_user_id`  = t2.`student_user_id`
        AND t1.`course_id`        = t2.`course_id`
        AND t1.`academic_year_id` = t2.`academic_year_id`
        AND t1.`semester_id`      = t2.`semester_id`
    WHERE t1.`student_user_id` IS NOT NULL
      AND t1.`token_id` > t2.`token_id`;

ALTER TABLE `evaluation_tokens`
    ADD UNIQUE KEY `uq_pending_token` (`student_user_id`, `course_id`, `academic_year_id`, `semester_id`);
