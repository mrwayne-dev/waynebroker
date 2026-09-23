-- ============================================================
-- MIGRATION 2026-08-01 - sign-up fields on users
--
-- Sign-up collected first name, last name, email and password, then
-- CONCATENATED first+last into `full_name` and threw the parts away
-- (api/auth/register.php). Everything else on the profile page had to be
-- typed a second time by the member after they arrived.
--
-- Three columns:
--
--   first_name / last_name  the values sign-up already asks for, kept as
--                           discrete columns instead of being discarded.
--                           `full_name` stays as the display name so nothing
--                           that reads it has to change.
--
--   location                new. There is no existing column for it. Note
--                           `login_logs.location` exists but is the geo-IP
--                           string of a SIGN-IN EVENT - a different concept
--                           entirely, deliberately not reused.
--
-- All three are DEFAULT NULL, never DEFAULT ''. The profile page must render
-- a field the member has not filled as genuinely blank, and the profile API
-- normalises '' back to NULL on write, so a non-null default would surface as
-- a value the member never entered.
--
-- ADDRESS IS DELIBERATELY NOT TOUCHED. It stays profile-only and optional.
--
-- The backfill splits existing `full_name` on the FIRST space so current
-- accounts get sensible parts rather than empty fields. Single-word names put
-- everything in first_name and leave last_name NULL, which is correct - a
-- mononym has no surname.
--
-- Idempotent via information_schema, matching 2026_07_31_auth_hardening.sql.
--
-- Run:  mysql -u <user> -p <db> < 2026_08_01_signup_fields.sql
-- ============================================================

SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS
           WHERE TABLE_SCHEMA = DATABASE()
             AND TABLE_NAME = 'users' AND COLUMN_NAME = 'first_name');
SET @s := IF(@c = 0,
  'ALTER TABLE `users` ADD COLUMN `first_name` VARCHAR(60) DEFAULT NULL AFTER `name`',
  'SELECT 1');
PREPARE stmt FROM @s; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS
           WHERE TABLE_SCHEMA = DATABASE()
             AND TABLE_NAME = 'users' AND COLUMN_NAME = 'last_name');
SET @s := IF(@c = 0,
  'ALTER TABLE `users` ADD COLUMN `last_name` VARCHAR(60) DEFAULT NULL AFTER `first_name`',
  'SELECT 1');
PREPARE stmt FROM @s; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS
           WHERE TABLE_SCHEMA = DATABASE()
             AND TABLE_NAME = 'users' AND COLUMN_NAME = 'location');
SET @s := IF(@c = 0,
  'ALTER TABLE `users` ADD COLUMN `location` VARCHAR(255) DEFAULT NULL AFTER `country`',
  'SELECT 1');
PREPARE stmt FROM @s; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Backfill only rows that have not been populated yet, so re-running this
-- migration never overwrites a name the member has since edited.
UPDATE `users`
   SET `first_name` = TRIM(SUBSTRING_INDEX(COALESCE(`full_name`, `name`), ' ', 1))
 WHERE `first_name` IS NULL
   AND COALESCE(`full_name`, `name`) <> '';

UPDATE `users`
   SET `last_name` = NULLIF(TRIM(SUBSTRING(
           COALESCE(`full_name`, `name`),
           LOCATE(' ', COALESCE(`full_name`, `name`)) + 1
       )), '')
 WHERE `last_name` IS NULL
   AND LOCATE(' ', COALESCE(`full_name`, `name`)) > 0;
