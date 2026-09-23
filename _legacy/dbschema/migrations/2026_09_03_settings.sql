-- ============================================================
-- MIGRATION 2026-09-03 - platform settings
--
-- A small key/value table for operational numbers an admin needs to
-- change without a deploy. The first one is the minimum withdrawal
-- amount, enforced in api/backend/wallet.php on withdraw_request.
--
-- WHY A TABLE RATHER THAN .env
-- .env is deploy-time configuration, read once per request and edited
-- over SSH. This is a business rule the owner is expected to change
-- from the admin panel while the site is running, so it belongs in
-- the database where a write is immediately visible to every request.
--
-- `key` is reserved in MySQL, hence setting_key.
--
-- Values are stored as text and cast on read. The table is expected to
-- hold a handful of rows of mixed shape (money, counts, flags), and a
-- typed column per shape would mean either a sparse row or a new
-- migration every time a setting of a new kind is added.
-- ============================================================

CREATE TABLE IF NOT EXISTS settings (
    setting_key   VARCHAR(64)  NOT NULL,
    setting_value TEXT         NOT NULL,
    updated_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP
                               ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (setting_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Seeded at 0, which means "no minimum".
--
-- Deliberately NOT seeded to a real figure. This migration adds the
-- ability to set a minimum; it must not invent a business rule that
-- starts refusing withdrawals the moment it is deployed. The owner
-- sets the real number on the Settings page, and until they do,
-- withdrawals behave exactly as they did before.
INSERT INTO settings (setting_key, setting_value)
VALUES ('withdrawal_min_amount', '0')
ON DUPLICATE KEY UPDATE setting_key = setting_key;
