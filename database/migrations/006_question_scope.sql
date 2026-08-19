-- 006_question_scope.sql  (Phase 1 of the two-tier evaluation questions change)
-- Compatible with MariaDB 5.5.68+. Run ONCE per environment.
--
-- Adds a scope to each evaluation question:
--   'course'         -> answered once PER COURSE   (teaching questions)
--   'administrative' -> answered ONCE PER SEMESTER (institutional services + advisor)
--
-- Later phases add the administrative token + submission flow + reporting split;
-- this migration only introduces the classification so it can be managed in the
-- admin UI first.

ALTER TABLE `evaluation_questions`
  ADD COLUMN `scope` ENUM('course','administrative') NOT NULL DEFAULT 'course' AFTER `category`;

-- Sensible defaults — admins can re-classify any question from the Questions screen.
-- Institutional service desks and shared facilities are answered once per semester.
UPDATE `evaluation_questions`
  SET `scope` = 'administrative'
  WHERE `category` IN ('Registry','Accounts','Library','Administration','Sickbay','Washroom & Surroundings');

-- The class-advisor question is also answered once per semester (one advisor per student).
UPDATE `evaluation_questions`
  SET `scope` = 'administrative'
  WHERE `question_text` LIKE '%class advisor%';
