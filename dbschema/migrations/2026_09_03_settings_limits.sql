-- ============================================================
-- MIGRATION 2026-09-03 - deposit and withdrawal limits
--
-- Extends the settings table added earlier the same day with the
-- guardrails the money paths were missing:
--
--   deposit_min_amount    secure_exchange deposits had NO floor at all -
--                         only amount > 0, so a 1-cent checkout was a
--                         valid request. deposit_address transfers had a
--                         per-address minimum but no platform-wide one.
--   deposit_max_amount    no ceiling existed on either method.
--   withdrawal_max_amount no per-request ceiling existed.
--
-- All seeded at 0, meaning "no limit", for the same reason the
-- withdrawal minimum was: this adds the ability to set limits and must
-- not invent business rules that start refusing real members' money the
-- moment it deploys. The owner sets real figures on the Settings page.
--
-- The deposit minimum composes with deposit_addresses.min_amount rather
-- than replacing it: the stricter of the two applies, so a per-coin
-- network minimum can still be higher than the platform floor.
-- ============================================================

INSERT INTO settings (setting_key, setting_value) VALUES
    ('deposit_min_amount',    '0'),
    ('deposit_max_amount',    '0'),
    ('withdrawal_max_amount', '0')
ON DUPLICATE KEY UPDATE setting_key = setting_key;
