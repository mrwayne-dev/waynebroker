-- ============================================================
-- MIGRATION 2026-08-27 - simplify the plan shelf from six tiers to three
--
-- The inherited shelf published six tiers (three weekly, three monthly)
-- named after trees, which was both more choice than the product needs
-- and named for the previous brand. Maveren publishes three:
--
--     Maveren Weekly Core      weekly   1.10% / wk   13 wks
--     Maveren Weekly Prime     weekly   1.65% / wk   26 wks
--     Maveren Monthly Reserve  monthly  6.00% / mo   12 mo
--
-- Two weekly and one monthly, so both cadence paths in
-- api/cron/investment_cron.php stay exercised and the "Two rhythms"
-- section on the marketing site stays truthful.
--
-- NOT a DELETE. `investments.plan_id` references these rows, so deleting
-- ids 4-6 would either fail on the FK or orphan live positions. They are
-- flipped to status='hidden' instead: every read path already filters on
-- status='active' (pages/public/_partials/plans-section.php,
-- api/backend/invest.php), so a hidden plan disappears from the shelf
-- while its history survives.
--
-- Live positions are unaffected either way: `investments` snapshots
-- plan_name / cadence / roi_percent / duration_days at purchase, so the
-- cron pays from the snapshot, never from this table.
--
-- Idempotent: keyed on id, and re-running writes the same values.
--
-- Run:  mysql -u <user> -p <db> < 2026_08_27_plans_simplify.sql
-- ============================================================

UPDATE `plans` SET
  `title`         = 'Maveren Weekly Core',
  `cadence`       = 'weekly',
  `roi_percent`   = 1.10,
  `duration_days` = 91,
  `min_amount`    = 250.00,
  `max_amount`    = 25000.00,
  `risk`          = 'Low',
  `description`   = 'A 13-week entry position paying out every Friday.',
  `summary`       = 'The lightest way in. Put capital to work for a quarter, take a payout every week, and get your principal back at the end of it.',
  `details`       = 'Capital is deployed into short-duration fixed-income instruments. Payouts are credited to your Maveren wallet each week and are available to withdraw immediately. Principal returns in full at maturity.',
  `icon`          = 'ph-plant',
  `accent`        = 'orange',
  `status`        = 'active'
WHERE `id` = 1;

UPDATE `plans` SET
  `title`         = 'Maveren Weekly Prime',
  `cadence`       = 'weekly',
  `roi_percent`   = 1.65,
  `duration_days` = 182,
  `min_amount`    = 1000.00,
  `max_amount`    = 100000.00,
  `risk`          = 'Moderate',
  `description`   = 'A 26-week position with a stronger weekly rate.',
  `summary`       = 'Half a year, twenty-six payouts, and a materially better rate than the entry tier for committing the extra term.',
  `details`       = 'A blended book of fixed income and investment-grade credit. Weekly payouts are credited automatically; principal is returned at maturity. Early exit forfeits unpaid periods.',
  `icon`          = 'ph-trend-up',
  `accent`        = 'orange',
  `status`        = 'active'
WHERE `id` = 2;

UPDATE `plans` SET
  `title`         = 'Maveren Monthly Reserve',
  `cadence`       = 'monthly',
  `roi_percent`   = 6.00,
  `duration_days` = 360,
  `min_amount`    = 5000.00,
  `max_amount`    = 500000.00,
  `risk`          = 'Moderate',
  `description`   = 'A 12-month position paying out on the same date each month.',
  `summary`       = 'Twelve clean monthly payouts across a full year. Simple to forecast, easy to plan around, and our strongest published rate.',
  `details`       = 'A balanced allocation across fixed income and dividend equity. Your payout lands on the same calendar day each month and is immediately withdrawable. Capital is at risk; performance is reported quarterly. Principal returns at maturity.',
  `icon`          = 'ph-buildings',
  `accent`        = 'orange',
  `status`        = 'active'
WHERE `id` = 3;

-- Everything beyond the three published tiers is retired, not deleted.
UPDATE `plans` SET `status` = 'hidden' WHERE `id` > 3;
