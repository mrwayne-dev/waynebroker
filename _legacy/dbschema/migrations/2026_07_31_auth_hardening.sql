-- ============================================================
-- MIGRATION 2026-07-31 - auth hardening
--
-- Three fixes, all backing api/auth/.
--
-- 1. admin_password_resets
--    Referenced by api/auth/admin_forgotpassword.php and
--    admin_resetpassword.php, but never present in
--    dbschema/maveren_create.sql. It exists on this machine only
--    because admin_forgotpassword.php runs a CREATE TABLE IF NOT EXISTS
--    at request time. On a fresh deploy the FIRST admin password reset
--    would fatal. Same shape as the user-side password_resets.
--
-- 2. otp_attempts on both reset tables
--    resetpassword.php matches a client-supplied user_id against a
--    6-digit OTP with no attempt counter, and forgotpassword.php hands
--    the user_id back in its JSON response. That is a 10^6 keyspace with
--    unlimited tries - brute-forceable in minutes. The column lets the
--    endpoint burn the OTP after 5 wrong guesses.
--
-- 3. users.last_login
--    api/auth/login.php guards its UPDATE behind
--    `SHOW COLUMNS FROM users LIKE 'last_login'` because the column was
--    never in the schema, so the write silently never ran and the admin
--    users table showed a blank Last Login for everyone.
--
-- Run:  mysql -u <user> -p <db> < 2026_07_31_auth_hardening.sql
-- ============================================================

CREATE TABLE IF NOT EXISTS `admin_password_resets` (
  `id`           INT AUTO_INCREMENT PRIMARY KEY,
  `admin_id`     INT NOT NULL,
  `otp`          VARCHAR(10) NOT NULL,
  `otp_attempts` TINYINT UNSIGNED NOT NULL DEFAULT 0,
  `expires_at`   DATETIME NOT NULL,
  `created_at`   DATETIME DEFAULT CURRENT_TIMESTAMP,

  KEY `idx_apr_admin` (`admin_id`),
  CONSTRAINT `fk_apr_admin` FOREIGN KEY (`admin_id`)
    REFERENCES `admins` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- The runtime CREATE TABLE in admin_forgotpassword.php may already have
-- made this table without the counter.
SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS
           WHERE TABLE_SCHEMA = DATABASE()
             AND TABLE_NAME = 'admin_password_resets'
             AND COLUMN_NAME = 'otp_attempts');
SET @s := IF(@c = 0,
  'ALTER TABLE `admin_password_resets` ADD COLUMN `otp_attempts` TINYINT UNSIGNED NOT NULL DEFAULT 0 AFTER `otp`',
  'SELECT 1');
PREPARE stmt FROM @s; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS
           WHERE TABLE_SCHEMA = DATABASE()
             AND TABLE_NAME = 'password_resets'
             AND COLUMN_NAME = 'otp_attempts');
SET @s := IF(@c = 0,
  'ALTER TABLE `password_resets` ADD COLUMN `otp_attempts` TINYINT UNSIGNED NOT NULL DEFAULT 0 AFTER `otp`',
  'SELECT 1');
PREPARE stmt FROM @s; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS
           WHERE TABLE_SCHEMA = DATABASE()
             AND TABLE_NAME = 'users'
             AND COLUMN_NAME = 'last_login');
SET @s := IF(@c = 0,
  'ALTER TABLE `users` ADD COLUMN `last_login` DATETIME NULL DEFAULT NULL AFTER `status`',
  'SELECT 1');
PREPARE stmt FROM @s; EXECUTE stmt; DEALLOCATE PREPARE stmt;
