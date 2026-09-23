-- ============================================================
-- MIGRATION 2026-08-27 - member KYC (identity verification)
--
-- Members submit an identity document plus a selfie; an admin approves
-- or rejects with a reason; withdrawals are refused until approved.
-- Deposits and new investments are NOT gated - only withdrawals.
--
-- TWO PLACES HOLD STATE, deliberately:
--
--   kyc_submissions  the full history. A rejected member resubmits and
--                    gets a second row, so the audit trail of what was
--                    sent and who reviewed it survives the retry.
--
--   users.kyc_status the denormalised current state. The withdrawal
--                    guard in api/backend/wallet.php and the badge on
--                    every dashboard page read it on nearly every
--                    request; making them join and aggregate over
--                    submissions to answer "is this member cleared?"
--                    would put a sort on the hot path for a single
--                    enum. api/admin/kyc.php writes both inside one
--                    transaction, so they cannot diverge.
--
-- The uploaded files live in uploads/kyc/{user_id}/ behind a
-- `Require all denied` .htaccess and are served only through
-- api/admin/kyc_file.php, which checks for an admin session first.
-- They are identity documents: unlike avatars, they must never be
-- fetchable by URL.
--
-- Idempotent via information_schema, matching 2026_08_01_signup_fields.sql.
--
-- Run:  mysql -u <user> -p <db> < 2026_08_27_kyc.sql
-- ============================================================

CREATE TABLE IF NOT EXISTS `kyc_submissions` (
  `id`            INT NOT NULL AUTO_INCREMENT,
  `user_id`       INT NOT NULL,
  `id_type`       ENUM('passport','national_id','drivers_license') NOT NULL,
  `id_number`     VARCHAR(80)  NOT NULL,
  `full_name`     VARCHAR(150) NOT NULL COMMENT 'Name exactly as printed on the document',
  `date_of_birth` DATE         NOT NULL,
  `country`       VARCHAR(80)  NOT NULL COMMENT 'Country of issue',
  -- Stored paths, NOT web-reachable. drivers_license/national_id need a
  -- back; a passport does not, so doc_back is nullable.
  `doc_front`     VARCHAR(255) NOT NULL,
  `doc_back`      VARCHAR(255) DEFAULT NULL,
  `selfie`        VARCHAR(255) NOT NULL,
  `status`        ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  `reject_reason` VARCHAR(255) DEFAULT NULL,
  `reviewed_by`   INT          DEFAULT NULL,
  `reviewed_at`   DATETIME     DEFAULT NULL,
  `created_at`    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  -- The admin queue is "oldest pending first"; this index serves it whole.
  KEY `idx_kyc_status`  (`status`, `created_at`),
  -- "this member's latest submission" on every dashboard load.
  KEY `idx_kyc_user`    (`user_id`, `created_at`),
  CONSTRAINT `kyc_fk_user`
    FOREIGN KEY (`user_id`)     REFERENCES `users` (`id`)  ON DELETE CASCADE,
  CONSTRAINT `kyc_fk_reviewer`
    FOREIGN KEY (`reviewed_by`) REFERENCES `admins` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 'none' rather than NULL: every read is a direct comparison, and a
-- three-valued NULL here would make `kyc_status <> 'approved'` silently
-- fail to match the members who have never submitted - which is exactly
-- the group the withdrawal guard most needs to catch.
SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS
           WHERE TABLE_SCHEMA = DATABASE()
             AND TABLE_NAME = 'users' AND COLUMN_NAME = 'kyc_status');
SET @s := IF(@c = 0,
  "ALTER TABLE `users` ADD COLUMN `kyc_status` ENUM('none','pending','approved','rejected') NOT NULL DEFAULT 'none' AFTER `status`",
  'SELECT 1');
PREPARE stmt FROM @s; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Lets the admin dashboard count members awaiting review without a scan.
SET @c := (SELECT COUNT(*) FROM information_schema.STATISTICS
           WHERE TABLE_SCHEMA = DATABASE()
             AND TABLE_NAME = 'users' AND INDEX_NAME = 'idx_users_kyc_status');
SET @s := IF(@c = 0,
  'ALTER TABLE `users` ADD INDEX `idx_users_kyc_status` (`kyc_status`)',
  'SELECT 1');
PREPARE stmt FROM @s; EXECUTE stmt; DEALLOCATE PREPARE stmt;
