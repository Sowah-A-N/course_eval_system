-- =====================================================================
-- Migration 009: Remove the tokenized-evaluation machinery
-- =====================================================================
--
-- The application no longer issues per-student evaluation tokens. Access is
-- controlled by the active academic period plus the anonymous completion
-- records in `evaluation_completions` (migration 007). These objects are now
-- dormant and are removed here.
--
-- Target: MariaDB 5.x (production). Run once, after 006/007/008 are applied.
--
-- Safe to run because:
--   * No foreign key in any other table references `evaluation_tokens`.
--   * `evaluations.token` is used only by two single-column indexes
--     (uq_evaluations_token, idx_token); dropping the column removes them.
--   * `student/evaluate/submit.php` inserts evaluations WITHOUT a token, so
--     no live code path writes or reads this column any longer.
--
-- NOTE: MariaDB 5.x has no "DROP COLUMN IF EXISTS". If this migration has
-- already been applied, the ALTER below will error harmlessly ("check that
-- column/key exists") and can be ignored.
-- =====================================================================

-- 1. Drop the leftover token column from the anonymous evaluations table.
--    (Automatically drops uq_evaluations_token and idx_token.)
ALTER TABLE `evaluations` DROP COLUMN `token`;

-- 2. Drop the now-unused token table entirely.
DROP TABLE IF EXISTS `evaluation_tokens`;
