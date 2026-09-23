-- ============================================================
-- MIGRATION 2026-07-31 - contact_messages
--
-- The public contact form has existed since launch but has never had a
-- backend. It POSTed to /contact/submit, which matched no rewrite rule in
-- .htaccess (the marketing routes are anchored with a trailing `$`), so
-- every submission fell through to the catch-all and rendered the 404
-- page. Messages were silently discarded.
--
-- This table is the store behind the new api/public/contact.php.
--
-- `ip` and `user_agent` are kept for the submission-rate check and for
-- abuse triage, nothing else. `attachment_path` is a web path under
-- /uploads/contact/, NULL when no file was sent.
--
-- Deliberately a real migration rather than the CREATE TABLE IF NOT EXISTS
-- at request time used by api/auth/forgotpassword.php - that idiom
-- duplicates the schema in PHP and drifts from dbschema/maveren_create.sql.
--
-- Run:  mysql -u <user> -p <db> < 2026_07_31_contact_messages.sql
-- ============================================================

CREATE TABLE IF NOT EXISTS `contact_messages` (
  `id`              INT AUTO_INCREMENT PRIMARY KEY,
  `name`            VARCHAR(150)  NOT NULL,
  `email`           VARCHAR(190)  NOT NULL,
  `type`            VARCHAR(40)   NOT NULL DEFAULT 'general',
  `service`         VARCHAR(40)   DEFAULT NULL,
  `subject`         VARCHAR(200)  NOT NULL,
  `message`         TEXT          NOT NULL,
  `attachment_path` VARCHAR(255)  DEFAULT NULL,
  `status`          ENUM('new','read','archived') NOT NULL DEFAULT 'new',
  `ip`              VARCHAR(45)   DEFAULT NULL,
  `user_agent`      VARCHAR(255)  DEFAULT NULL,
  `created_at`      DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,

  KEY `idx_contact_created` (`created_at`),
  KEY `idx_contact_status`  (`status`),
  -- Backs the per-address cooldown in api/public/contact.php.
  KEY `idx_contact_email`   (`email`, `created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
