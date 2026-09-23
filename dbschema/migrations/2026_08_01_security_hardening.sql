-- ============================================================
-- MIGRATION 2026-08-01 - security hardening
--
-- 1. rate_limit_hits
--
--    Replaces `login_attempts`, which had three problems:
--      * it was created by a CREATE TABLE IF NOT EXISTS that ran on EVERY
--        login request, and existed in no schema file or migration;
--      * it was keyed on IP alone with no scope, so ten failed member
--        logins from one IP also locked out ADMIN login from that IP;
--      * only api/auth/login.php and admin_login.php ever used it, leaving
--        registration, password reset, OTP verification and every wallet
--        write action completely unthrottled.
--
--    `scope` separates the buckets (login, admin_login, register, reset,
--    otp, deposit, withdraw, invest, contact). `subject` is the thing being
--    counted - an IP, or an account identifier when one is known - so a
--    single endpoint can throttle per-IP and per-account independently
--    without the two interfering.
--
--    Rows are disposable. Nothing reads history; the sweep in
--    api/utilities/security.php deletes anything older than the widest
--    window on write, so the table stays small without a cron.
--
-- 2. email_verifications.otp_attempts
--
--    api/auth/verify_email.php had NO attempt counter: a 6-digit code with
--    unlimited guesses, and success opens a fully privileged session. It
--    was the only unlimited-guess path in the system terminating in
--    authentication. password_resets already gained this column on
--    2026-07-31; this mirrors it.
--
-- Idempotent via information_schema, matching the other migrations.
--
-- Run:  mysql -u <user> -p <db> < 2026_08_01_security_hardening.sql
-- ============================================================

CREATE TABLE IF NOT EXISTS `rate_limit_hits` (
  `id`         INT AUTO_INCREMENT PRIMARY KEY,
  -- What is being limited: login, admin_login, register, reset, otp,
  -- deposit, withdraw, invest, contact.
  `scope`      VARCHAR(32)  NOT NULL,
  -- Who/what is being counted: an IP, or an email / user id when known.
  `subject`    VARCHAR(190) NOT NULL,
  `created_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,

  -- The counting query is always (scope, subject, created_at > ?), so this
  -- composite covers it end to end.
  KEY `idx_rl_lookup` (`scope`, `subject`, `created_at`),
  -- Supports the housekeeping sweep.
  KEY `idx_rl_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS
           WHERE TABLE_SCHEMA = DATABASE()
             AND TABLE_NAME = 'email_verifications'
             AND COLUMN_NAME = 'otp_attempts');
SET @s := IF(@c = 0,
  'ALTER TABLE `email_verifications` ADD COLUMN `otp_attempts` TINYINT UNSIGNED NOT NULL DEFAULT 0 AFTER `otp`',
  'SELECT 1');
PREPARE stmt FROM @s; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- login_attempts is superseded. Left in place rather than dropped so an
-- in-flight deploy cannot break; it can be dropped once this is live:
--   DROP TABLE IF EXISTS `login_attempts`;
