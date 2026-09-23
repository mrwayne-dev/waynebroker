-- ============================================================
-- MIGRATION 2026-07-31 - transactions.method += 'deposit_address'
--
-- Adds the manual crypto-transfer deposit route: the member is shown
-- one of the published `deposit_addresses` rows, transfers to it, and
-- an admin credits the wallet once the transfer is confirmed.
--
-- `wallet_address` was deliberately NOT reused. That value already
-- means "the member's own payout address" on a withdrawal - see
-- bank_details.method, the withdraw_request whitelist in
-- api/backend/wallet.php, and the withdrawal email formatter that
-- prints details.coin / details.address from member-supplied data.
-- Reusing it would make `WHERE method = 'wallet_address'` meaningless
-- without also reading `type`, permanently.
--
-- Appending to the END of the ENUM keeps every existing value at its
-- current ordinal, so MySQL 8 performs this in place with no table
-- copy and no lock.
--
-- Run:  mysql -u <user> -p <db> < 2026_07_31_deposit_address_method.sql
-- ============================================================

ALTER TABLE `transactions`
  MODIFY COLUMN `method` ENUM(
    'secure_exchange',
    'cash_mailing',
    'wire_transfer',
    'local_bank',
    'wallet_address',
    'wallet',
    'system',
    'deposit_address'
  ) DEFAULT NULL,
  ALGORITHM=INPLACE, LOCK=NONE;
