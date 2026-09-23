-- ============================================================
-- MIGRATION 2026-07-30 - deposit_addresses
--
-- Replaces the two hardcoded TEXT columns on `settings`
-- (cash_mailing_address, wallet_deposit_address) with a real table,
-- so an admin can publish one address per chain instead of one in
-- total.
--
-- Run:  mysql -u <user> -p <db> < 2026_07_30_deposit_addresses.sql
--
-- Step 4 (dropping `settings`) is deliberately left commented out.
-- Run it only once the deployed code is verified - until then the old
-- endpoints still read the old table.
-- ============================================================

START TRANSACTION;

-- ------------------------------------------------------------
-- 1. The new table
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `deposit_addresses` (
  `id`            INT NOT NULL AUTO_INCREMENT,
  `asset`         VARCHAR(12)   NOT NULL COMMENT 'BTC, ETH, USDT',
  `network`       VARCHAR(32)   NOT NULL COMMENT 'bitcoin, erc20, trc20, bep20, solana',
  `label`         VARCHAR(80)   NOT NULL COMMENT 'What the member sees: "USDT - TRC20 (Tron)"',
  `address`       VARCHAR(255)  NOT NULL,
  `memo_tag`      VARCHAR(120)  DEFAULT NULL COMMENT 'XRP destination tag / XLM memo / TON comment',
  `memo_label`    VARCHAR(40)   DEFAULT NULL COMMENT 'NULL hides the memo row entirely',
  `min_amount`    DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `confirmations` TINYINT UNSIGNED NOT NULL DEFAULT 0,
  `instructions`  TEXT          DEFAULT NULL,
  `qr_path`       VARCHAR(255)  DEFAULT NULL COMMENT 'Reserved: QR feature not built yet',
  `is_active`     TINYINT(1)    NOT NULL DEFAULT 1,
  `sort_order`    SMALLINT      NOT NULL DEFAULT 0,
  `created_by`    INT           DEFAULT NULL,
  `updated_by`    INT           DEFAULT NULL,
  `created_at`    TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`    TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  -- The real business rule: one live address per chain. It also turns a
  -- duplicate into a clean 23000 the endpoint can translate, instead of an
  -- exists-check that two admins could race.
  UNIQUE KEY `uniq_asset_network` (`asset`, `network`),
  KEY `idx_da_active_sort` (`is_active`, `sort_order`, `asset`),
  CONSTRAINT `deposit_addresses_fk_created_by`
    FOREIGN KEY (`created_by`) REFERENCES `admins` (`id`) ON DELETE SET NULL,
  CONSTRAINT `deposit_addresses_fk_updated_by`
    FOREIGN KEY (`updated_by`) REFERENCES `admins` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- 2. Carry the one existing wallet address across.
--
-- It lands INACTIVE on purpose: `settings` never recorded which chain the
-- address belonged to, and publishing a Bitcoin address under an Ethereum
-- label loses a member's money. An admin has to confirm it first.
--
-- LEFT(...,255) is not padding - the source column is TEXT, and in STRICT
-- mode one over-long row would abort the entire migration.
--
-- GUARDED on `settings` existing. This step only means anything on a database
-- that predates deposit_addresses; a FRESH install created from
-- maveren_create.sql has no `settings` table at all, and the bare statement
-- aborted the whole migration with
--     ERROR 1146 (42S02): Table '<db>.settings' doesn't exist
-- which meant the documented install path (create script, then migrations)
-- could never complete. Legacy databases behave exactly as before.
-- ------------------------------------------------------------
SET @has_settings := (SELECT COUNT(*) FROM information_schema.TABLES
                      WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'settings');

SET @s := IF(@has_settings = 1, '
INSERT INTO `deposit_addresses`
  (`asset`, `network`, `label`, `address`, `instructions`, `is_active`, `sort_order`)
SELECT
  ''OTHER'',
  ''legacy'',
  ''Legacy wallet address - confirm the network before activating'',
  LEFT(TRIM(s.`wallet_deposit_address`), 255),
  ''Migrated from settings.wallet_deposit_address on 2026-07-30.'',
  0,
  900
FROM `settings` s
WHERE s.`id` = 1
  AND s.`wallet_deposit_address` IS NOT NULL
  AND TRIM(s.`wallet_deposit_address`) <> ''''
', 'SELECT 1');
PREPARE stmt FROM @s; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ------------------------------------------------------------
-- 3. cash_mailing_address is NOT migrated - the method is retired.
--    Keep a copy for one release in case support needs to look it up.
--    Guarded for the same reason as step 2.
-- ------------------------------------------------------------
SET @s := IF(@has_settings = 1,
  'CREATE TABLE IF NOT EXISTS `settings_archive_20260730` AS SELECT * FROM `settings`',
  'SELECT 1');
PREPARE stmt FROM @s; EXECUTE stmt; DEALLOCATE PREPARE stmt;

COMMIT;

-- ------------------------------------------------------------
-- 4. Run only after the deploy is verified:
-- DROP TABLE `settings`;
-- ------------------------------------------------------------
