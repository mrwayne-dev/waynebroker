# Maveren Capital - Architecture Audit

| Field | Value |
|---|---|
| Last updated | 2026-09-18 |
| Auditor | Claude (Opus 5), via Claude Code, at the repository owner's request |
| Commit SHA / branch inspected | `64c1f2ec9bb29e372c3db4e5f841d31413d8cd48` on `main` (working tree clean) |
| Method | Static reading of the repository. No production server, production database, or live traffic was inspected. |

---

## Scope and coverage statement

The repository is small enough to audit in one pass (232 tracked files, roughly 14,000 lines of first-party PHP/JS/SQL/shell, excluding CSS and vendored libraries).

**Read line by line:**
- Every tracked `.php` file under `api/`, `config/`, `dbschema/`, `scripts/`, `index.php`
- Every `.sql` file under `dbschema/` (the untracked `deposit_addresses_live.sql` was read for structure only; its values are not reproduced here)
- `.htaccess` (root, `uploads/`, `uploads/kyc/`, `cache/`), `.user.ini`, `.env.example`, `.gitignore`, `composer.json`, `composer.lock`
- `scripts/deploy.sh`, `scripts/make_env_production.sh`
- `assets/js/api.js` (wrapper and auth sections), `assets/js/admin/transactions.js` (render path), `assets/js/admin/admin.js` (render paths), page partials under `pages/*/_partials/`

**Read partially / by search:**
- `pages/**/*.php` view templates: searched for auth guards, unescaped output, inline scripts
- `assets/js/*.js` and `assets/js/admin/*.js`: searched for HTML sinks (`innerHTML`, `.html(`, `.append(`) and escaping helpers; not every file read end to end
- `assets/css/*.css`: breakpoints and file usage only
- `api/utilities/email_temps.php`: template inventory and raw-HTML slots only
- `api/utilities/i18n.php`: fully read to line 400, driver section by search

**Not audited:**
- `vendor/` (PHPMailer 6.11.1, Composer autoloader)
- Minified third-party JS/CSS (`jquery.min.js`, `bootstrap.min.js`, `chart.min.js`, `bootstrap-select.min.js`)
- Local `.env*` files: key names only were inspected, values were not read into this document
- The production host, its PHP/MySQL versions, its crontab, logs and database contents

**Confidence label convention used below:**
- High: every claim verified against code in this commit
- Medium: code verified, but behaviour depends on the server environment that was not inspected
- Low: largely inferred, or depends on owner knowledge

Markers: `[INFERRED]` = reasoned from code but not observed running. `[UNKNOWN]` = cannot be determined from the repository.

---

## Table of contents

0. [Phase 0 - Orientation summary](#0-phase-0---orientation-summary)
1. [Project identity](#1-project-identity)
2. [Tech stack](#2-tech-stack)
3. [Architecture pattern](#3-architecture-pattern)
4. [Directory and module map](#4-directory-and-module-map)
5. [Data layer](#5-data-layer)
6. [Backend application layer](#6-backend-application-layer)
7. [Authentication and authorization](#7-authentication-and-authorization)
8. [Security posture](#8-security-posture)
9. [Frontend](#9-frontend)
10. [UI/UX findings](#10-uiux-findings)
11. [DevOps, infra, deployment](#11-devops-infra-deployment)
12. [Performance](#12-performance)
13. [Testing and quality](#13-testing-and-quality)
14. [Observability](#14-observability)
15. [Integrations inventory](#15-integrations-inventory)
16. [Business logic and workflows](#16-business-logic-and-workflows)
17. [Known issues, debt, and risk](#17-known-issues-debt-and-risk)
18. [Inconsistencies and contradictions](#18-inconsistencies-and-contradictions)
19. [Unknowns and owner-input needed](#19-unknowns-and-owner-input-needed)
20. [Recommendations](#20-recommendations)

---

## 0. Phase 0 - Orientation summary

| Question | Answer | Evidence |
|---|---|---|
| Entry point | Apache/LiteSpeed serves each PHP file directly. `index.php` only starts a session and redirects to `pages/public/index.php`. Clean URLs come from `.htaccess` rewrites. | `index.php:9-13`, `.htaccess:110-138` |
| Framework(s) | None. Plain procedural PHP. Only Composer dependency is PHPMailer. | `composer.json`, `composer.lock` |
| PHP version | Code uses `match`, `str_starts_with`, `str_contains`, `readonly`-free PHP 8 syntax, so PHP >= 8.0 is required. Local CLI is 8.3.6. Production version `[UNKNOWN]`; comment in `security.php:47-48` says "bcrypt cost 10 on PHP 8.3". | `api/backend/wallet.php:469`, `dbschema/migrate.php:590-594` |
| Repo shape | Single app, single repo, no monorepo tooling | Directory tree |
| Rendering | Server-rendered PHP page shells, hydrated by jQuery / vanilla JS that calls JSON endpoints under `/api/` ("multi-page app with AJAX islands") | `pages/user/dashboard.php`, `assets/js/api.js:115` |
| Frontend / backend / fullstack | Fullstack in one deployable | - |
| Product type | Consumer-facing investment platform: members deposit crypto, buy fixed-rate "plans", receive scheduled ROI credits, withdraw. Admin back office. | `dbschema/maveren_create.sql:306-363`, `api/cron/investment_cron.php` |
| Actors | Guests (marketing site, contact form), members (`users`), admins (`admins`, roles `super_admin`/`manager`/`support`), NOWPayments (IPN webhook), cron (`investment_cron.php`), translation providers (outbound only) | Sections 6-7 |
| Money flow | In: crypto via NOWPayments hosted invoice (auto-credit on IPN) or manual transfer to operator-published addresses (admin credits). Internal: wallet balance -> plan principal; cron credits ROI to wallet; principal returned at maturity. Out: member requests withdrawal to bank or crypto address; admin marks complete (payout itself happens off-platform). | Section 16 |
| Data flow (10,000 ft) | Browser -> `.htaccess` rewrite -> PHP page shell -> JS `fetchApi()` -> `api/**.php` -> PDO -> MySQL. Side channels: PHPMailer SMTP for every state change; NOWPayments REST + IPN; translation APIs via output buffer; Smartsupp chat widget in browser. | Section 3 |

---

## 1. Project identity

**Confidence: High** for code-derived facts. Low for business facts (see Section 19).

### 1.1 Name and tagline
- Product name: **Maveren Capital** (`config/constants.php:6`, `APP_SHORT` = `MVC`)
- Tagline: "Steady yield. Plainly stated." (`config/constants.php:12`)
- Production domain: `maverencapital.com` (`config/constants.php:11`, `scripts/deploy.sh:22,256`)

### 1.2 Description (auditor's words, after reading the code)
A web platform where a member registers with email + password (email OTP verification), funds an internal USD-denominated wallet with cryptocurrency, and moves wallet funds into one of three fixed-term "plans" that pay a fixed percentage per week or per month. A daily cron job credits those payouts to the wallet and returns principal at maturity. Members must pass manual identity verification (KYC: document + selfie upload) before they can request a withdrawal. Admins approve manual deposits, approve or decline withdrawals, review KYC, manage plans and the published crypto deposit addresses, edit balances, and broadcast email. The public site is a marketing front with a contact form and a 134-language server-side translator.

### 1.3 Business model
- Revenue mechanism: **Not found in codebase - needs owner input.** No fee, spread, commission, or management-charge logic exists anywhere. Searched `api/`, `dbschema/` for `fee`, `commission`, `spread`, `charge`: no matches in business logic.
- Yield source: **Not found in codebase.** See Section 16.9 for what the code does and does not do. Plan copy states capital "is deployed into short-duration fixed-income instruments" (`dbschema/maveren_create.sql:391`). No code integrates with any broker, custodian, exchange, bank, or market-data source.

### 1.4 Personas (from roles and tables)
| Persona | Table / session key | Evidence |
|---|---|---|
| Guest | none | Public routes `.htaccess:116-117`, `api/public/contact.php` |
| Member | `users`, `$_SESSION['user_id']` | `api/auth/login.php:112` |
| Admin - super_admin | `admins.role` | `api/utilities/security.php:465-467` |
| Admin - manager | `admins.role` | same |
| Admin - support | `admins.role` | same |
| `users.role = 'admin'` | exists in ENUM, editable in admin UI, **grants nothing** (see 7.7) | `dbschema/maveren_create.sql:102`, `api/admin/users.php:220` |

### 1.5 Domains
| Host | Serves | Evidence |
|---|---|---|
| `maverencapital.com` | Everything: marketing, member dashboard (`/dashboard*`), admin (`/admin*`), API (`/api/*`) | `.htaccess` |
| `www.` | Redirected to apex (if the rewrite fires - see 18.1) | `.htaccess:76-81` |

No subdomain separation between public site, member app, admin panel and API.

### 1.6 Repo layout (depth 2, tracked files)

```
/                         web root (entire repo is deployed into the docroot)
|-- index.php             redirect to pages/public/index.php
|-- .htaccess             routing, file denies, security headers, CSP
|-- .user.ini             PHP upload limits for FPM/CGI hosts
|-- composer.json/.lock   PHPMailer only (lock is gitignored but present)
|-- api/
|   |-- admin/            15 admin JSON endpoints
|   |-- auth/             10 login/register/reset/verify endpoints (member + admin)
|   |-- backend/          9 member JSON endpoints + email.php (mailer)
|   |-- cron/             investment_cron.php
|   |-- payments/         NOWPayments invoice creation + IPN webhook
|   |-- public/           contact.php, set_language.php
|   `-- utilities/        security.php, helpers.php, settings.php, i18n.php, email_temps.php, logout.php
|-- assets/
|   |-- css/              hand-written CSS (bootstrap.css, dashboard.css, mvc-*.css ...)
|   |-- js/               page scripts, api.js wrapper, vendored jQuery/Bootstrap/Chart.js
|   |-- js/admin/         admin page scripts
|   |-- fonts/ images/ favicon/ icon/
|-- config/               env.php (loader), database.php, constants.php, roles.php, assets.php, test_db.php, certs/cacert.pem
|-- dbschema/             maveren_create.sql (baseline), migrate.php (runner), migrations/*.sql
|-- pages/
|   |-- public/           marketing + auth pages
|   |-- user/             member dashboard pages
|   `-- admin/            admin pages
|-- scripts/              deploy.sh, make_env_production.sh, seed/test/role/CSP/i18n CLIs
|-- uploads/              .htaccess guards only (content gitignored)
|-- cache/                .htaccess only (i18n cache gitignored)
`-- _planning/            one colour palette PNG
```

Untracked but present locally: `.env`, `.env.local`, `.env.production`, `.env.dbpass`, `logs/`, `shots/`, `cache/i18n/`, `uploads/*`, `vendor/`, `dbschema/deposit_addresses_live.sql`, `.claude/`.

### 1.7 License and ownership signals
- No `LICENSE` file. No license field in `composer.json`. **Needs owner input.**
- `.gitignore:40` states the repo is a "public repo". Whether the GitHub repository is actually public is `[UNKNOWN]`.
- First commit `1ef8c91` (2026-08-27): "Baseline: inherited Aldernorth Capital codebase, history detached". Subsequent commits purge "TitanX / HRC / Crestmark" lineage (`63d741d`). File headers still reference "Lymora" (`index.php:2`, `api/payments/create_crypto_payment.php:5`). `.env.local` comments reference "the old TitanX merchant account" for NOWPayments.
- All 34 commits authored by `mrwayne-dev`.

---

## 2. Tech stack

**Confidence: High** for repo contents. Medium for production runtime versions.

| Concern | What is used | Version / evidence |
|---|---|---|
| Language | PHP | >= 8.0 required by syntax; 8.3.6 locally; production `[UNKNOWN]` |
| Backend framework | None (procedural PHP, one file per endpoint) | - |
| Frontend framework | None. jQuery + vanilla JS | jQuery 3.7.1 (`assets/js/jquery.min.js` header) |
| CSS framework | Bootstrap 5.0.2 JS (`assets/js/bootstrap.min.js`), `bootstrap.css` (6 lines, minified, version not in header), bootstrap-select | - |
| Charts | Chart.js 4.4.1 (self-hosted) | `assets/js/chart.min.js` header |
| Bundler / transpiler | None. Files served as authored. Cache-busting via `?v=filemtime` | `config/assets.php` |
| Database | MySQL or MariaDB (InnoDB, utf8mb4). `migrate.php` notes "MariaDB client on this host prints 'mysql: Deprecated program name'" | `dbschema/migrate.php:582-584`; server version `[UNKNOWN]` |
| Read/write split, replicas | None. Single PDO DSN | `config/database.php:18` |
| Caching layer | None for data. File-based cache for translations only (`cache/i18n/`) | `api/utilities/i18n.php:42` |
| Search | SQL `LIKE '%...%'` | `api/admin/users.php:154`, `api/admin/transactions.php` |
| Queue / broker | None. All email sent synchronously in-request | `api/backend/email.php:176` |
| Realtime | None (no WebSockets/SSE). Smartsupp chat widget is third-party | `.htaccess:215` CSP |
| Scheduling | System crontab, one entry, daily 01:00 | `scripts/deploy.sh:241` |
| File storage | Local disk under `uploads/` | `api/backend/kyc.php:458`, `upload_avatar.php:977` |
| CDN | None. Fonts, icons, chart lib self-hosted | `.htaccess:192-199` |
| Email | SMTP via PHPMailer 6.11.1, implicit TLS 465 default | `api/backend/email.php:136-147`, `config/env.php:77-83` |
| SMS / voice | None found (searched `twilio`, `sms`, `vonage`) | - |
| Payments | NOWPayments (crypto invoices + IPN) | `api/payments/*` |
| Translation | MyMemory (default), Google Cloud Translation v2, DeepL | `api/utilities/i18n.php`, `config/env.php:106-108` |
| Geo-IP | ipapi.co (unauthenticated HTTPS GET on every login) | `api/utilities/helpers.php:32-59` |
| Live chat | Smartsupp (browser script) | `assets/js/smartsupp.js` |
| Analytics / telemetry | None found (searched `gtag`, `analytics`, `plausible`, `posthog`, `mixpanel`) | - |
| Error tracking / APM | None. PHP error log to `logs/php-error.log` | `config/env.php:141-145` |
| Feature flags | None. `settings` table holds four money limits only | `api/admin/settings.php:38-43` |
| Hosting | cPanel shared hosting, "Spaceship", host alias `hostingserver2`, LiteSpeed per `.htaccess:216-217` | `scripts/deploy.sh:5,21` |

---

## 3. Architecture pattern

**Confidence: High**

- **Style:** Monolith. Transaction-script pattern: each endpoint file parses input, authorizes, runs SQL, sends email, and returns JSON. No service, repository, or domain layer.
- **API style:** RPC over HTTP. Most endpoints take an `action` field in a JSON body and `switch` on it (`api/backend/wallet.php:141`, `api/admin/plans.php:227`). Response envelope `{status, message, data}` is conventional but not universal (`api/backend/card_usage.php` returns `{success, totals, percentages}`).
- **Rendering per route type:**
  - Marketing pages: fully server-rendered PHP (`pages/public/*.php`)
  - Member / admin pages: PHP shell with sidebar/topbar, data loaded client-side from `/api/*`
  - Emails: PHP string templates with `{{placeholder}}` substitution
- **Multi-tenancy:** None. Single tenant.
- **Event-driven vs request-driven:** Request-driven, plus one daily batch job. No domain events, listeners, or queues.
- **Cross-cutting policy module:** `api/utilities/security.php` centralizes password hashing, rate limiting, session hardening, CSRF, admin role gating, and security logging. Every endpoint opts in by calling the functions; nothing enforces them automatically.

Request lifecycle (member API call):

```
Browser JS  fetchApi('/api/backend/wallet.php', {action:'withdraw_request', ...})
  -> header X-CSRF-Token from <meta name="csrf-token">
.htaccess   ^api/(.*)$ -> api/$1  (pass-through)
wallet.php  mvcSessionStart() -> mvcCsrfEnforce() -> isset($_SESSION['user_id'])
            -> require config (env.php parses .env on every request)
            -> getPDO() (new connection, SET time_zone)
            -> switch(action) -> SQL -> sendEmail() x2 (synchronous SMTP)
            -> JSON
```

---

## 4. Directory and module map

**Confidence: High**

| Folder | Purpose | Key files | Notes |
|---|---|---|---|
| `api/auth/` | Session establishment for both actor types | `login.php`, `register.php`, `verify_email.php`, `forgotpassword.php`, `resetpassword.php`, `admin_*.php`, `logout.php` | `api/auth/logout.php` appears unused by the UI: the topbar links to `/dashboard.logout` (`pages/user/_partials/topbar.php:173`) |
| `api/backend/` | Member-facing JSON API + mailer | `wallet.php` (deposits/withdrawals), `invest.php`, `kyc.php`, `profile.php`, `dashboard.php`, `transactions.php`, `upload_avatar.php`, `card_usage.php`, `email.php` | Header of `invest.php` says `/api/backend/investment.php` (stale) |
| `api/admin/` | Back-office JSON API | `process_deposit.php`, `process_withdrawal.php`, `wallets.php`, `kyc.php`, `kyc_file.php`, `plans.php`, `deposit_addresses.php`, `users.php`, `settings.php`, `announcements.php`, `email.php`, `dashboard.php`, `transactions.php`, `get_pending_*.php` | Role gates vary per file (7.6) |
| `api/payments/` | NOWPayments | `create_crypto_payment.php`, `now_webhook.php` | |
| `api/cron/` | Batch | `investment_cron.php` | |
| `api/public/` | Unauthenticated POST endpoints | `contact.php`, `set_language.php` | |
| `api/utilities/` | Shared code | `security.php`, `helpers.php`, `settings.php`, `i18n.php`, `email_temps.php` (981 lines, 36 templates), `logout.php` | |
| `config/` | Bootstrapping | `env.php` (.env parser -> constants), `database.php` (`getPDO()`), `constants.php`, `roles.php`, `assets.php`, `test_db.php`, `certs/cacert.pem` | `roles.php` defines constants that do not match the DB enum (7.6) |
| `dbschema/` | Schema | `maveren_create.sql`, `migrate.php`, 10 migrations | |
| `pages/` | Views | `public/`, `user/`, `admin/`, each with `_partials/` | |
| `scripts/` | Ops CLIs | `deploy.sh`, `make_env_production.sh`, `seed_test_accounts.php`, `set_admin_role.php`, `csp_hashes.php`, `i18n_warm.php` | All PHP scripts refuse non-CLI SAPIs |
| `assets/` | Static | | `assets/js/admin/utilities.js` is referenced by no page (dead) |

**Bounded contexts (informal):** Identity (auth, profile, KYC), Ledger (wallets, transactions), Investing (plans, investments, cron), Payments (NOWPayments, deposit addresses), Communications (email, announcements, contact), Localization (i18n).

**Shared libraries / SDKs:** none first-party. PHPMailer only.

**Generated vs handwritten:** All first-party code is handwritten. `vendor/` is Composer-generated. `cache/i18n/` is runtime-generated. `.env.production` is generated by `scripts/make_env_production.sh`.

---

## 5. Data layer

**Confidence: High** for schema as defined in repo. **Low** for actual production schema (not inspected; see drift notes).

### 5.1 Engine
- MySQL/MariaDB, InnoDB, `utf8mb4_unicode_ci`. Version `[UNKNOWN]`.
- Connection: PDO, `ERRMODE_EXCEPTION`, `FETCH_ASSOC`, emulated prepares (default). A new connection per `getPDO()` call; some requests call it more than once (e.g. `api/utilities/logout.php:925`, `api/payments/create_crypto_payment.php:252` inside a request that already holds one).
- Session time zone is forced to PHP's offset on every connection (`config/database.php:39`) so `NOW()` and `date()` agree. PHP zone is `America/New_York` (`config/constants.php:14`).

### 5.2 Schema sources
The schema is defined by `dbschema/maveren_create.sql` (baseline, destructive: it `DROP`s every table) plus 10 migrations applied by `php dbschema/migrate.php`, which records filename + SHA-256 in `schema_migrations`.

| Migration | Effect |
|---|---|
| `2026_07_30_deposit_addresses.sql` | Creates `deposit_addresses`; migrates legacy `settings.wallet_deposit_address` (inactive); archives legacy `settings` to `settings_archive_20260730` |
| `2026_07_31_auth_hardening.sql` | `admin_password_resets`; `otp_attempts` on reset tables; `users.last_login` |
| `2026_07_31_contact_messages.sql` | `contact_messages` |
| `2026_07_31_deposit_address_method.sql` | Adds `deposit_address` to `transactions.method` ENUM |
| `2026_08_01_security_hardening.sql` | `rate_limit_hits`; `email_verifications.otp_attempts` |
| `2026_08_01_signup_fields.sql` | `users.first_name`, `last_name`, `location` + backfill |
| `2026_08_27_kyc.sql` | `kyc_submissions`; `users.kyc_status` + index |
| `2026_08_27_plans_simplify.sql` | Rewrites plans 1-3, hides id > 3 |
| `2026_09_03_settings.sql` | New key/value `settings` table; seeds `withdrawal_min_amount=0` |
| `2026_09_03_settings_limits.sql` | Seeds `deposit_min_amount`, `deposit_max_amount`, `withdrawal_max_amount` = 0 |

Lexical order puts `2026_09_03_settings.sql` before `..._settings_limits.sql` (`.` sorts before `_`), which is the required order. Verified.

### 5.3 ERD (resulting schema after baseline + all migrations)

```
admins 1---N deposit_addresses (created_by, updated_by; ON DELETE SET NULL)
admins 1---N admin_password_resets (CASCADE)
admins 1---N kyc_submissions.reviewed_by (SET NULL)

users 1---1 wallets              (by convention; wallets.user_id is NOT unique)
users 1---N transactions         (CASCADE)
users 1---N investments          (CASCADE)
users 1---N kyc_submissions      (CASCADE)
users 1---N bank_details         (CASCADE)
users 1---N password_resets      (CASCADE)
users 1---N email_verifications  (CASCADE)
plans 1---N investments          (plan_id, SET NULL; terms are snapshotted)

login_logs     (user_type, user_id) polymorphic, no FK
rate_limit_hits, contact_messages, announcements, settings, schema_migrations: standalone
```

#### Table inventory

| Table | PK | Columns (abridged to meaningful ones) | Indexes / FKs | Read/written by code? |
|---|---|---|---|---|
| `users` | `id` | `name`, `first_name`, `last_name`, `full_name`, `email` (unique), `password`, `email_verified` tinyint, `role` ENUM('user','admin'), `status` ENUM('active','disabled'), `kyc_status` ENUM('none','pending','approved','rejected') NOT NULL DEFAULT 'none', `last_login`, `profile_picture`, `phone`, `country`, `location`, `address`, `created_at` | `uniq_users_email`, `idx_users_kyc_status` | Yes (27 files) |
| `admins` | `id` | `name`, `full_name`, `email` (unique), `password`, `role` ENUM('super_admin','manager','support') DEFAULT 'manager', `status`, `profile_picture`, `last_login`, `created_at` | `uniq_admins_email` | Yes |
| `wallets` | `id` | `user_id`, `balance` DECIMAL(12,2), `total_deposited`, `total_withdrawn`, `total_investments`, `total_earnings`, `pending_withdrawals`, `cash_mailing_address` TEXT, `wallet_deposit_address` TEXT, `created_at` | `idx_user_wallet` (non-unique), FK users CASCADE | Yes. The two TEXT address columns are never read or written. |
| `transactions` | `id` | `user_id`, `type` VARCHAR(50) (free text: `deposit`,`withdraw`,`investment`,`roi_payout`,`investment_release`), `method` ENUM(8 values), `details` JSON, `amount` DECIMAL(12,2), `reference` (unique), `status` ENUM('pending','completed','failed') DEFAULT 'completed', `created_at` | `uniq_txn_reference`, `idx_user_transaction`, `idx_txn_method`, FK users CASCADE | Yes |
| `investments` | `id` | `user_id`, `plan_id`, snapshot `plan_name`,`cadence`,`roi_percent`,`duration_days`; `amount` DECIMAL(15,2), `payouts_total`, `payouts_made`, `next_payout_date` DATE, `maturity_date` DATE, `roi_earned`, `status` ENUM('active','completed','cancelled') | `idx_inv_user`, `idx_inv_status`, `idx_inv_next_payout`, `idx_inv_maturity`, FKs | Yes |
| `plans` | `id` | `title`, `cadence`, `roi_percent` DECIMAL(5,2) per period, `duration_days`, `min_amount`, `max_amount`, `risk`, `description`, `summary`, `details`, `icon`, `accent`, `status` ENUM('active','hidden') | `idx_plans_cadence` | Yes |
| `deposit_addresses` | `id` | `asset`, `network`, `label`, `address`, `memo_tag`, `memo_label`, `min_amount`, `confirmations`, `instructions`, `qr_path` (reserved, unused), `is_active`, `sort_order`, `created_by`, `updated_by`, timestamps | `uniq_asset_network`, `idx_da_active_sort`, FKs admins | Yes |
| `kyc_submissions` | `id` | `user_id`, `id_type` ENUM, `id_number`, `full_name`, `date_of_birth`, `country`, `doc_front`, `doc_back`, `selfie` (paths), `status`, `reject_reason`, `reviewed_by`, `reviewed_at`, timestamps | `idx_kyc_status(status,created_at)`, `idx_kyc_user(user_id,created_at)`, FKs | Yes |
| `bank_details` | `id` | `user_id`, `method` ENUM('local_bank','wallet_address'), `details` JSON | `idx_bank_user`, FK | **No code reads or writes it** (0 references) |
| `password_resets` | `id` | `user_id`, `otp` VARCHAR(10), `otp_attempts`, `expires_at`, `created_at` | `idx_reset_user`, FK | Yes |
| `admin_password_resets` | `id` | `admin_id`, `otp`, `otp_attempts`, `expires_at`, `created_at` | index + FK (named differently in baseline vs migration) | Yes |
| `email_verifications` | `id` | `user_id`, `otp`, `otp_attempts` (migration), `expires_at`, `created_at` | `idx_verify_user`, FK | Yes |
| `login_logs` | `id` | `user_type`, `user_id`, `ip`, `browser`, `location`, `created_at` | `idx_login_user` | Written only (`helpers.php:60`); no UI reads it |
| `announcements` | `id` | `title`, `body`, `category`, `status`, timestamps | PK only | Yes |
| `contact_messages` | `id` | `name`, `email`, `type`, `service`, `subject`, `message`, `attachment_path`, `status`, `ip`, `user_agent`, `created_at` | 3 indexes | Written only; **no admin UI reads it** (messages are only emailed) |
| `rate_limit_hits` | `id` | `scope`, `subject`, `created_at` | `idx_rl_lookup`, `idx_rl_created` | Yes |
| `settings` | `setting_key` | `setting_value` TEXT, `updated_at` | PK | Yes (4 keys) |
| `schema_migrations` | `filename` | `checksum`, `applied_at` | PK | `migrate.php` only |

**Tables referenced by code but not defined anywhere:**
- `admin_logs`: written by `logAdminAction()` (`api/utilities/helpers.php:9-19`), which is never called.
- `donations`: queried by the `donors` broadcast group (`api/admin/email.php:102-106`). Selecting that group throws, the exception is caught and logged, and the admin sees "No active user recipients".

**Tables that may exist in production but not in the repo schema** (from code comments):
- `login_attempts`: orphan from old code (`api/utilities/helpers.php:128-146`, `2026_08_01_security_hardening.sql:63-65`)
- `settings_archive_20260730`: created by migration step 3 on legacy DBs
- Legacy `settings` (id-keyed, with `wallet_deposit_address`): the 07-30 migration says to drop it manually; see drift risk in 17.2

### 5.4 Relationship notes
- `wallets.user_id` has no UNIQUE constraint. Several endpoints auto-create a wallet when none is found (`api/backend/dashboard.php:92,131`, `api/backend/invest.php:382`). Two concurrent first-loads can create two wallet rows `[INFERRED]`; `getUserWallet()` then reads an arbitrary one (`api/backend/wallet.php:116-120`).
- `login_logs` is polymorphic (`user_type`), with no FK.

### 5.5 Soft deletes, timestamps, audit columns
- No soft deletes anywhere. `DELETE FROM users` (`api/admin/users.php:271`) cascades to wallets, transactions, investments, KYC rows, OTP rows.
- `created_at` on all tables; `updated_at` only on `deposit_addresses`, `kyc_submissions`, `announcements`, `settings`.
- Audit columns: `deposit_addresses.created_by/updated_by`, `kyc_submissions.reviewed_by/reviewed_at`. No other actor attribution on any write.

### 5.6 Enums vs strings
- ENUM: `users.role/status/kyc_status`, `admins.role/status`, `transactions.method/status`, `investments.cadence/status`, `plans.cadence/status`, `kyc_submissions.id_type/status`, `contact_messages.status`, `announcements.status`, `bank_details.method`, `login_logs.user_type`.
- Free VARCHAR where an enum would be expected: `transactions.type` (VARCHAR(50)), `plans.risk`, `announcements.category`, `contact_messages.type/service`.

### 5.7 JSON columns
| Column | Shape (observed writers) |
|---|---|
| `transactions.details` (deposit) | `{initiated_at, method, deposit_address?: {id, asset, network, label, address, memo_tag, memo_label, min_amount, confirmations, instructions, snapshot_at}, user_marked_paid?, marked_paid_at?, tx_hash?, provider?, provider_payment_id?, provider_response?, created_invoice_url?, invoice_created_at?, provider_payload?, ipn_received_at?, completed_at?, provider_final_status?, last_ipn_status?, failure_notified?}` (`api/backend/wallet.php:251-255,403-409`, `create_crypto_payment.php:408-413`, `now_webhook.php:620-660`) |
| `transactions.details` (withdraw) | `{method, withdraw_details: <client-supplied object, unvalidated>, requested_at}` (`wallet.php:528-532`) |
| `transactions.details` (investment / roi / release / bonus) | `{investment_id, plan_id?, plan_name, cadence?, periods_paid?, per_payout?, principal?, roi_paid_total?, note?, admin_action?}` |
| `bank_details.details` | Unused |

`withdraw_details` is stored exactly as the client sent it. No schema validation beyond `json_encode`.

### 5.8 Migration health
- 10 migrations, individually idempotent via `information_schema` guards. Ledger with checksums. No squashing.
- `migrate.php` shells out to the `mysql` client with a 0600 defaults file (`dbschema/migrate.php:506-521`).
- Baseline `maveren_create.sql` **is not idempotent in the safe sense**: it drops all tables. `deploy.sh` only runs it when the database has zero non-ledger tables (`scripts/deploy.sh:212-220`).
- Two request-time schema writers remain, the pattern the migration system was built to remove: `api/admin/announcements.php:51` (`CREATE TABLE IF NOT EXISTS announcements` on every request).

### 5.9 Seeders and factories
- `maveren_create.sql:385-404` seeds 3 plans.
- `scripts/seed_test_accounts.php`: creates a demo member and admin with a known password, CLI-only, refuses unless the environment is development.
- `dbschema/deposit_addresses_live.sql` (untracked): upserts live addresses and deactivates every row not listed (see 17.1 H-6).
- No factories.

### 5.10 Query style
Raw SQL through PDO prepared statements everywhere. No ORM, no query builder. Interpolation into SQL occurs only for: allowlisted table/column names (`security.php:94,280`, `logout.php:926`, `kyc_file.php:47`), integer-cast LIMIT/OFFSET (`admin/users.php:181`, `admin/kyc.php:109`, `admin/wallets.php:541`, `admin/transactions.php:513`), a constant (`mvcOtpExpirySql()`), and an int-cast user id (`api/backend/kyc.php:273-274`). No injectable interpolation of request data was found.

### 5.11 N+1 risk areas
| Location | Pattern |
|---|---|
| `api/admin/transactions.php:540-544` (CSV export) | One `SELECT email FROM users` per exported row |
| `api/cron/investment_cron.php` | Per investment: 3 statements + 1 SMTP send, serial. Linear in active positions. |
| `api/admin/email.php:188-214` | One synchronous SMTP session per recipient inside one HTTP request |
| `api/backend/wallet.php` `get_wallet_summary` | 5 queries + 4 settings reads + 1 UPDATE on every call (a read endpoint that writes) |

### 5.12 Index audit
- Missing: `transactions(type, status)` or `(status, type)`. Every admin dashboard, wallet list and pending queue filters on `type='deposit'/'withdraw' AND status='pending'` (`api/admin/dashboard.php`, `api/admin/wallets.php:500-512`), and those filters currently use `idx_user_transaction` or a full scan.
- Missing: `transactions(created_at)`. Every recent-activity list orders by it.
- Missing: `users(status)` for admin filters. Minor at current scale.
- Missing: UNIQUE on `wallets.user_id` (5.4).
- `plans`: fine. `investments`: adequate for cron (`status`, `next_payout_date`, `maturity_date` indexed separately; a composite `(status, next_payout_date)` would be tighter).

### 5.13 Transactions (DB) usage
| Location | Atomic unit | Locking |
|---|---|---|
| `api/backend/wallet.php:534-549` withdraw | wallet debit + transaction insert | **Balance check happens before BEGIN, no FOR UPDATE, UPDATE has no balance guard** (17.1 C-1) |
| `api/backend/invest.php:374-424` start | wallet debit + investment + txn | `SELECT ... FOR UPDATE` on wallet. Correct. |
| `api/backend/invest.php:484-530` unlock | investment close + wallet credit + txn | FOR UPDATE on investment. Correct. |
| `api/cron/investment_cron.php` step 1 | investment update + wallet credit + txn | **No lock; UPDATE guarded only by `status='active'`; wallet credited even if 0 rows updated** |
| `api/cron/investment_cron.php` step 2 | close + credit + txn | CAS on `status='active'`, checks rowCount. Correct. |
| `api/payments/now_webhook.php:653-681` | txn complete + wallet credit | **No lock, no CAS** (17.1 C-3) |
| `api/admin/process_deposit.php` | CAS + credit | FOR UPDATE + CAS. Correct. |
| `api/admin/process_withdrawal.php` | CAS + wallet | CAS. Correct, but sends email before COMMIT. |
| `api/admin/plans.php` bonus/term/close | multi-statement | FOR UPDATE, but statements go through `executeQuery()` which **swallows exceptions**, so a failed statement does not roll back (17.1 H-4) |
| `api/admin/kyc.php:186-203` | submission + users.kyc_status | Transaction, CAS on submission but `users` update is unconditional |
| `api/backend/kyc.php:487-512` | submission + users.kyc_status | Transaction |
| `api/admin/wallets.php:459-479` | single UPDATE | Transaction around one absolute-value write |

### 5.14 Money representation
- `DECIMAL(12,2)` for wallets/transactions, `DECIMAL(15,2)` for investments/plans.
- PHP side casts every amount to `float` (`(float) $data['amount']`), then binds the float. Sub-cent inputs are accepted (`amount > 0`) and rounded by MySQL on insert: a request for `0.001` passes validation and stores `0.00` `[INFERRED]`.
- No currency column; everything is implicitly USD (`config/constants.php:13`).

### 5.15 Data lifecycle, retention, privacy
- No retention or purge job for any table except `rate_limit_hits` (probabilistic 1-in-50 sweep of rows older than 24h, `security.php:185-187`).
- KYC documents: kept indefinitely in `uploads/kyc/{user_id}/`. Deleting a user cascades DB rows but **leaves the files on disk** (no unlink in `api/admin/users.php:262-275`).
- Contact attachments: kept indefinitely in web-reachable `uploads/contact/`.
- No data-export or erasure (GDPR/NDPR subject-access) tooling. No consent capture at sign-up (`api/auth/register.php`), no privacy-policy route (`.htaccess:117` lists no such page).

---

## 6. Backend application layer

**Confidence: High**

### 6.1 Routing map - pages (`.htaccess`)

| URL | File | Guard |
|---|---|---|
| `/` | `pages/public/index.php` | none |
| `/about`, `/contact`, `/login`, `/register`, `/forgotpassword`, `/platform`, `/solutions`, `/plans` | `pages/public/{name}.php` | none |
| `/dashboard` | `pages/user/dashboard.php` | `isset($_SESSION['user_id'])` else redirect `/login` |
| `/dashboard.{invest,wallet,transactions,profile,kyc}` | `pages/user/*.php` | same |
| `/dashboard.logout` | `pages/user/logout.php` | none (GET logout) |
| `/admin`, `/admin.{dashboard,users,wallets,transactions,announcements,plans,kyc,settings}`, `/admin.deposit-addresses` | `pages/admin/*.php` | `isset($_SESSION['admin_id'])` (verified by grep: 1 guard each) |
| `/admin.{login,register,forgotpassword}` | `pages/admin/*.php` | none |
| `/admin.logout` | `pages/admin/logout.php` | none (GET logout) |
| `*.md` | 404 page | |
| anything not a real file/dir | `pages/public/error.php` | |

`pages/**/*.php` are also directly reachable by their file path (e.g. `/pages/user/wallet.php`), because only real-file checks gate the catch-all (`.htaccess:136-138`). Guards live in each file, so this is not an auth bypass.

### 6.2 Routing map - API

Every file under `api/` is reachable at its path (`.htaccess:110-111`). Summary (method, auth, CSRF, role, actions):

| Endpoint | Auth | CSRF | Role gate | Actions |
|---|---|---|---|---|
| `api/auth/login.php` | - | yes | - | POST |
| `api/auth/register.php` | - | yes | - | POST |
| `api/auth/verify_email.php` | - | yes | - | POST (`resend` flag) |
| `api/auth/forgotpassword.php` / `resetpassword.php` | - | yes | - | POST |
| `api/auth/admin_login.php` / `admin_register.php` / `admin_forgotpassword.php` / `admin_resetpassword.php` | - | yes | invite code for register | POST |
| `api/auth/logout.php` | user | yes (only if no session was active) | - | POST |
| `api/backend/wallet.php` | user | yes | - | `initiate_deposit`, `confirm_deposit_payment`, `withdraw_request` (POST); `get_pending_deposits`, `get_wallet_summary`, `get_deposit_networks` (GET or POST) |
| `api/backend/invest.php` | user | yes, but **GET accepted for all actions** | - | `get_plans`, `preview`, `get_summary`, `get_active`, `get_matured`, `start_investment`, `unlock_investment` |
| `api/backend/kyc.php` | user | yes | - | `status`, `submit` (multipart) |
| `api/backend/profile.php` | user | yes | - | `get_profile`, `update_profile`, `change_password` |
| `api/backend/dashboard.php` | user | yes | - | `get_wallet`, `get_data` |
| `api/backend/transactions.php` | user | yes | - | list/filter/export |
| `api/backend/upload_avatar.php` | user | yes | - | multipart |
| `api/backend/card_usage.php` | user | **no** (read-only) | - | GET |
| `api/payments/create_crypto_payment.php` | **none when called directly** | **no** | - | direct-call block (17.1 C-2) |
| `api/payments/now_webhook.php` | HMAC-SHA512 | n/a | - | POST |
| `api/cron/investment_cron.php` | CLI or `REMOTE_ADDR` in 127.0.0.1/::1 | n/a | - | - |
| `api/public/contact.php` | - | yes | - | POST multipart |
| `api/public/set_language.php` | - | yes | - | POST form |
| `api/admin/dashboard.php` | admin | no (read) | **none** | GET |
| `api/admin/transactions.php` | admin | no (read) | **none** | GET, CSV export |
| `api/admin/get_pending_deposits.php` / `get_pending_withdrawals.php` | admin | no (read) | **none** | GET |
| `api/admin/process_deposit.php` | admin | yes | OPERATOR | complete/cancel |
| `api/admin/process_withdrawal.php` | admin | yes | OPERATOR | complete/cancel |
| `api/admin/users.php` | admin | yes | OPERATOR (all methods) | list, `edit_user`, `delete_user`, `send_email` |
| `api/admin/plans.php` | admin | yes | OPERATOR | plans CRUD, `edit_investment`, `investment_bonus`, `investment_term`, `investment_close` |
| `api/admin/announcements.php` | admin | yes, but GET accepted | OPERATOR | `get_list`, `add`, `edit`, `delete` |
| `api/admin/settings.php` | admin | yes, but GET accepted | ALL for `get`, OWNER for `update` | |
| `api/admin/wallets.php` | admin | yes | OWNER | list, `update_balance` |
| `api/admin/deposit_addresses.php` | admin | yes, but GET fallback | OWNER | add/edit/toggle/delete |
| `api/admin/kyc.php` | admin | yes | `['support_admin','super_admin']` (see 7.6) | `counts`, `list`, `detail`, `review` |
| `api/admin/kyc_file.php` | admin | n/a (GET stream) | same | GET |
| `api/admin/email.php` | admin | yes | custom check accepting `manager`, `super_admin`, `support_admin` | broadcast |

Role sets: `MVC_ROLE_ALL = [super_admin, manager, support]`, `MVC_ROLE_OPERATOR = [super_admin, manager]`, `MVC_ROLE_OWNER = [super_admin]` (`api/utilities/security.php:465-467`).

### 6.3 Controllers
Every endpoint is a "fat controller": validation, SQL, business rules, email composition and response formatting in one file. Largest: `api/admin/plans.php` (790 lines), `api/backend/wallet.php` (743), `api/backend/invest.php` (565).

### 6.4 Services / repositories
None. Helper duplication instead:
- `jsonResponse()` defined in `wallet.php`, `invest.php`, `profile.php` (and variants `kycRespond`, `jsonOut`, `settingsOut`, `sendResponse`, `respond`, `contactResponse`)
- `executeQuery()` defined four times with **different semantics**: swallow-and-return-false in `admin/users.php`, `admin/wallets.php`, `admin/plans.php`, `admin/transactions.php`; rethrow in `admin/deposit_addresses.php`
- `generateReference()` in `wallet.php:112`, `invest.php:77`, `investment_cron.php:641`
- Cadence helpers duplicated in `invest.php:88-102`, `investment_cron.php:633-640` and `admin/plans.php:121,524-528` with a comment "must stay identical"

### 6.5 Domain events / listeners
None.

### 6.6 Jobs and queues
None. All email is sent synchronously inside the request (and in `process_withdrawal.php`, inside an open DB transaction).

### 6.7 Scheduled tasks
| Job | Schedule | What it does |
|---|---|---|
| `api/cron/investment_cron.php` | `0 1 * * *` (installed by `scripts/deploy.sh:241`); file header says `0 2 * * *` | Step 1: for each `active` investment with `next_payout_date <= CURDATE()`, pay every due period (catch-up capped at `payouts_total`), credit wallet, insert `roi_payout` txn, email member. Step 2: for each `active` investment with `maturity_date <= CURDATE()`, mark completed, credit principal, insert `investment_release` txn, email. Output appended to `logs/cron.log` and `logs/investment_cron.log`. Exit 1 if any error. |

No other cron. No retries beyond "next day's run". No alerting on failure `[INFERRED]`.

### 6.8 Console commands
`dbschema/migrate.php` (`--status`, `--dry-run`, `--baseline`, `--resync`), `scripts/seed_test_accounts.php`, `scripts/set_admin_role.php` (`--email --role`, `--list`, refuses to remove the last super_admin), `scripts/csp_hashes.php` (`--check`), `scripts/i18n_warm.php` (`--status`, `--clear`), `config/test_db.php` (browser-or-CLI connection test; web access denied by `.htaccess:41`).

### 6.9 Incoming webhooks
`api/payments/now_webhook.php`:
- Signature: HMAC-SHA512 of a recursively key-sorted re-serialization of the decoded JSON, compared with `hash_equals` (`now_webhook.php:514-568`). Fails closed.
- Idempotency: read-then-write on `transactions.status` (`:611-617`); not atomic (17.1 C-3).
- Success statuses accepted: `finished`, `confirmed`, `successful`, `paid`, `payment_received`, or `is_paid`/`paid` truthy when no status field (`:632-643`).
- Amount credited: payload `price_amount`/`amount` if numeric, else the stored amount (`:646-650`).
- Does not check that the referenced transaction is `type='deposit'` and `method='secure_exchange'`.
- Terminal failure statuses `failed`/`expired`/`refunded` mark the txn failed and email once.

### 6.10 Outgoing webhooks
None.

### 6.11 Third-party API clients
Hand-written cURL in `create_crypto_payment.php` (NOWPayments), `i18n.php` (MyMemory/Google/DeepL, `curl_multi`), `file_get_contents` in `helpers.php:40-42` (ipapi.co, 2s timeout). Each is in-line, no shared HTTP client, no retries except NOWPayments' opt-in insecure retry.

### 6.12 Rate limiting
DB-backed sliding window (`rate_limit_hits`), fails open (`security.php:144-163`).

| Scope | Limit | Subjects recorded | Subjects checked |
|---|---|---|---|
| `login` / `admin_login` | 10 / 15 min | IP + email (failures only) | IP + email |
| `register` | 5 / 60 min | IP | IP (admin_register shares this bucket) |
| `reset` | 5 / 15 min | IP only | IP + email (email bucket never filled) |
| `otp` | 10 / 15 min | IP | IP |
| `deposit` | 20 / 15 min | IP + user_id | IP + user_id |
| `withdraw` | 10 / 60 min | IP + user_id | IP + user_id |
| `invest` | 20 / 15 min | **IP only** | IP + user_id (user bucket never filled) |
| `contact` | 5 / 60 min | IP | IP |
| `kyc_submit` | 6 / 60 min | IP + user_id | IP + user_id |

Client IP is `REMOTE_ADDR` (`security.php:194-197`). Behind a reverse proxy or CDN every client would share one IP `[INFERRED]`; production topology `[UNKNOWN]`.

Not rate-limited: `verify_email` resend (90s cooldown per account only), `change_password` (current-password guessing), all admin endpoints, `create_crypto_payment.php` direct calls.

### 6.13 API versioning
None. Paths are unversioned.

---

## 7. Authentication and authorization

**Confidence: High** (code), Medium (runtime session settings depend on host `php.ini`).

### 7.1 Mechanism
PHP native file sessions (default handler; storage path `[UNKNOWN]`). Two independent identities share one session namespace: `$_SESSION['user_id']` for members, `$_SESSION['admin_id']` for admins. A single browser session can hold both.

### 7.2 Password hashing
Argon2id, `memory_cost=47104` (46 MiB), `time_cost=3`, `threads=1` (`security.php:38-55`). Legacy bcrypt hashes are rehashed on successful login (`security.php:85-99`, `login.php:97`, `admin_login.php:72`). Minimum length 8 characters; no other policy, no breached-password check. Passwords are `trim()`med before hashing and verifying (`register.php:37`, `login.php:34`).

### 7.3 Password reset
- 6-digit numeric OTP (`random_int`), stored plaintext in `password_resets.otp` / `admin_password_resets.otp`, 10-minute expiry stamped by MySQL (`security.php:257-260`), 2-minute resend cooldown, burned after 5 wrong guesses, deleted after use.
- Responses do not reveal whether the account exists (`forgotpassword.php:63-70`).
- Reset keyed by email, not user id (`resetpassword.php:30-60`).
- **Existing sessions are not invalidated** on reset or password change. There is no session store to enumerate.

### 7.4 Email verification
- Same OTP scheme in `email_verifications`, created at registration. Login blocks unverified accounts and returns the `user_id` to the client (`login.php:100-107`). The client then calls `verify_email.php` with `user_id` + `otp`.
- Successful verification opens a full session (`verify_email.php:179-184`).
- Resend is callable for any `user_id` without authentication; 90s cooldown per account.
- `verify_email.php` distinguishes "Account not found" from "already verified", which enumerates user ids `[minor]`.

### 7.5 2FA / MFA, social login, SSO, passkeys
None found (searched `totp`, `webauthn`, `google_oauth`, `2fa`). Not present for admins either.

### 7.6 Authorization model
- **Members:** binary (logged in or not). Ownership checks are implicit: every member query filters `WHERE user_id = $_SESSION['user_id']`, and `unlock_investment` checks ownership explicitly (`invest.php:493`). No IDOR found in member endpoints.
- **Admins:** role read from DB on every gated request via `mvcRequireAdminRole()`, fails closed (`security.php:418-461`).
- **`config/roles.php` mismatch:** defines `ROLE_SUPPORT_ADMIN = 'support_admin'`, `ROLE_SUPER_ADMIN = 'super_admin'`, `ROLE_USER = 'user'`. The `admins.role` enum is `super_admin | manager | support`. `api/admin/kyc.php:53` and `api/admin/kyc_file.php:36` gate on `[ROLE_SUPPORT_ADMIN, ROLE_SUPER_ADMIN]` = `['support_admin','super_admin']`. **Result: only `super_admin` can reach KYC review.** `manager` and `support` get 403, although the comment says "Any admin may review" (`kyc_file.php:35`). `hasPermission()` and the `$permissions` map in `roles.php` are unused.
- Endpoints with no role gate beyond "is an admin": `api/admin/dashboard.php`, `api/admin/transactions.php` (full ledger + CSV export with emails), `get_pending_deposits.php`, `get_pending_withdrawals.php`.
- `scripts/set_admin_role.php` header describes `support` as "read-only". Support can read the ledger and pending queues, but cannot read KYC (enum mismatch) or edit anything.

### 7.7 `users.role = 'admin'`
Admins can set a member's `users.role` to `admin` (`api/admin/users.php:220`). `$_SESSION['role']` is set from it at login (`login.php:115`) but **nothing reads `$_SESSION['role']`** (verified by grep). The only effects: the member is excluded from broadcast emails (`admin/email.php:78` filters `role='user'`) and cannot be deleted from the UI (`users.php:267`).

### 7.8 Admin provisioning
- Self-service at `/admin.register` with a shared `ADMIN_INVITE_CODE` from `.env`, compared with `hash_equals`; empty code refuses (`admin_register.php:45`).
- **Every invite-code registrant becomes `super_admin`** (`admin_register.php:92`), i.e. full control of wallet balances and the deposit addresses members pay into.
- The registration endpoint opens an admin session **without regenerating the session id** (`admin_register.php:102-109`; `security.php:342-343` notes this was supposed to be fixed).
- Brute-forcing the invite code is limited only by the `register` bucket (5/hour per IP). Code strength `[UNKNOWN]`.
- No UI to list, demote, disable or delete admins. Only `scripts/set_admin_role.php` over SSH.

### 7.9 Sessions
- Cookie: `HttpOnly`, `Secure` when HTTPS detected (proxy-aware), `SameSite=Strict`, `cookie_lifetime=86400`, `use_strict_mode=1` (`security.php:316-336`).
- Server-side idle expiry: `session.gc_maxlifetime` not set in code; host default `[UNKNOWN]`. `SESSION_LIFETIME` in `.env` is read by nothing.
- Remember-me: none (24h cookie for everyone).
- Session id regenerated on login and email verification; not on admin registration.
- Logout: POST `api/auth/logout.php` (unused by UI) or **GET** `/dashboard.logout` / `/admin.logout` (no CSRF; SameSite=Strict mitigates cross-site triggering). Logout destroys the whole session, including any co-resident admin/member identity.

### 7.10 CSRF
One per-session 32-byte token in `<meta name="csrf-token">`, sent as `X-CSRF-Token` by `fetchApi()`; also accepted as `csrf_token` form or JSON field; `hash_equals` compare; GET/HEAD/OPTIONS exempt (`security.php:502-584`). Coverage gaps: endpoints that **accept state-changing actions via GET** are therefore exempt:
- `api/backend/invest.php:68` (`json ?: $_POST ?: $_GET`): `start_investment`, `unlock_investment`
- `api/admin/announcements.php:41`: `add`, `edit`, `delete`
- `api/admin/settings.php:36`: `update` (OWNER)

The only control left for these is `SameSite=Strict`. `api/backend/wallet.php:60-83` shows the fix pattern (GET limited to read-only actions).

### 7.11 Account lockout and brute force
Per-IP and per-email throttles (6.12). Accounts are never locked; throttles expire after the window. Admin logins notify the admin by email; member logins notify the member **and** the operator (`login.php:147-158`).

### 7.12 API keys / tokens
None issued. No API for third parties.

### 7.13 Impersonation / admin-as-user
None. No audit trail on privileged actions except `logs/security.log` entries for KYC review, KYC document view, role denials and CSRF rejections.

### 7.14 Known auth issues (summary)
1. KYC role gate uses non-existent role names (only super_admin passes).
2. Every invite-code admin is super_admin; no in-app admin management.
3. `admin_register.php` does not regenerate the session id.
4. State-changing actions over GET bypass CSRF in 3 endpoints.
5. No MFA for admins who can set balances and payment addresses.
6. Password change/reset does not revoke other sessions.
7. Email change requires no password confirmation or re-verification (`api/backend/profile.php` `update_profile`); it notifies old and new address.
8. Profile email change does not check `admins.email` for collisions, unlike registration.

---

## 8. Security posture

**Confidence: High** for code. Medium where behaviour depends on Apache vs LiteSpeed handling of `.htaccess`.

### 8.1 Input validation
Ad hoc per endpoint: `filter_var` for emails, length caps, allowlists for enums, `is_numeric` for settings, `finfo` MIME sniffing for uploads. No shared validator. Amounts are `(float)` casts with `> 0` checks.

### 8.2 Output escaping
- PHP views: `htmlspecialchars` used consistently in partials (verified by grep of `<?=` without escaping: remaining hits are integers, constants or already-escaped variables).
- Emails: every placeholder escaped except the allowlisted raw keys `details_html` and `message_body` (`api/backend/email.php:83-93`). Callers escape these first (`contact.php:238`, `admin/users.php:305`, `admin/email.php:154`, `wallet.php:556-575`).
- Client-side: `escapeHtml`/`esc` helpers exist in `dashboard.js`, `kyc.js`, `admin.js`, `admin/kyc.js`, `admin/deposit_addresses.js`.
  - **Unescaped:** `assets/js/admin/transactions.js:107-127` interpolates `tx.reference`, `tx.user_name`, `tx.type`, `tx.status` into a template string passed to `.append()`. `user_name` comes from `users.full_name`, which any member can set to arbitrary text via `profile.php` `update_profile`. This is a **stored HTML-injection path into the admin panel**. CSP blocks inline script and `javascript:` URLs (no `'unsafe-inline'` in `script-src`), which limits the impact to markup injection (e.g. fake UI, `<img>` beacons since `img-src https:` is allowed).
  - `api/admin/get_pending_deposits.php` and `api/admin/dashboard.php` HTML-escape on the server and the client escapes again: double-escaping (`&amp;` shown to admins).

### 8.3 SQL injection
No injectable paths found (5.10).

### 8.4 XSS surface
See 8.2. No `v-html`/`dangerouslySetInnerHTML` (not those frameworks). 59 `innerHTML`/`insertAdjacentHTML`/`.html(` sinks in first-party JS; all but the admin transactions table either use static strings or escaped values, based on sampling admin JS (full review of every member-page sink not completed).

### 8.5 Headers (`.htaccess:144-220`)
| Header | Value |
|---|---|
| `X-Frame-Options` | `SAMEORIGIN` |
| `X-Content-Type-Options` | `nosniff` |
| `Referrer-Policy` | `strict-origin-when-cross-origin` |
| `Permissions-Policy` | `camera=(), microphone=(), geolocation=()` |
| `Strict-Transport-Security` | `max-age=31536000; includeSubDomains; preload` |
| `Content-Security-Policy` | `default-src 'self'`; `script-src 'self'` + 3 sha256 hashes + Smartsupp; `style-src 'self' 'unsafe-inline'` + Smartsupp; `img-src 'self' data: https:`; `connect-src` + Smartsupp + wss; `frame-src` Smartsupp; `object-src 'none'`; `base-uri 'self'`; `form-action 'self'`; `frame-ancestors 'self'`; `upgrade-insecure-requests` |
| `X-Powered-By` | unset |

CSP hash drift: the current inline scripts hash to `DMg7...` (public head) and `ZpKR...` (user + admin head). The policy also carries `+dV20...`, which matches no inline script in this commit. The comment block (`.htaccess:170-173`) lists four different hash prefixes (`1pFct`, `kH7VA`, `Mwjuc`, `HYKzX`), none of which is in the policy. Behaviour is correct; documentation is stale.

The KYC camera flow: `Permissions-Policy: camera=()` disables camera access for the page. If the KYC selfie step ever uses `getUserMedia`, it will be blocked. Current KYC uses file inputs (`api/backend/kyc.php`), so no conflict today `[INFERRED]`.

### 8.6 CORS
No CORS headers anywhere; wildcard CORS was removed (`api/backend/invest.php:17-35`). Correct for a same-origin app.

### 8.7 Secrets management
- `.env` parsed on every request by `config/env.php`; required keys abort boot if empty.
- `.env`, `.env.*`, dotfiles denied over HTTP (`.htaccess:28-36`); `config/`, `dbschema/`, `logs/`, `scripts/`, `vendor/` return 403 (`.htaccess:41`).
- `scripts/deploy.sh` excludes `.env`, `.env.local`, `.env.production` from rsync but **not `.env.dbpass`** or `.claude/`, so both are copied into the production docroot (`deploy.sh:84-103`). `.env.dbpass` is covered by the dotfile deny. `.claude/settings.local.json` is inside a dot-directory whose file basename does not start with a dot; `<FilesMatch "^\.">` matches basenames only, so it is **likely web-readable** `[INFERRED; not verified on server]`.
- Hardcoded non-secret identifiers: Smartsupp site key (`assets/js/smartsupp.js:31`, public by design), support email and site URL in `email_temps.php:19-23`.
- No hardcoded passwords or API keys found in tracked code (searched `password\s*=\s*'`, `api_key`, `sk_`, `Bearer`).
- `.env.example` documents keys; local `.env` also carries `NOWPAYMENTS_PUBLIC_KEY` (unused) and `PASSWORD_HASH_COST`, `SESSION_LIFETIME` (unused).

### 8.8 Encryption at rest
None at application level. KYC `id_number`, `date_of_birth`, full name, and document images are stored in plaintext columns/files. OTPs stored plaintext.

### 8.9 PII inventory
| Data | Where | Protection |
|---|---|---|
| Name, email, phone, country, location, address | `users` | DB access only |
| Identity document number, DOB, issuing country | `kyc_submissions` | DB access only; UI masks all but last 4 of id number to the member |
| Passport/ID images, selfie | `uploads/kyc/{uid}/` | `Require all denied` + random filenames + admin-only streamer with path containment and MIME allowlist (`api/admin/kyc_file.php`); files `0640`, dir `0750` |
| Bank account / IBAN / crypto payout address | `transactions.details.withdraw_details` JSON; also emailed in HTML to `SMTP_TO` | DB + mailbox |
| IP, user agent, geo-IP location per login | `login_logs`, `contact_messages`, `logs/security.log`, emails | none |
| Contact form messages + attachments | `contact_messages`, `uploads/contact/` (**web-reachable** by URL; 128-bit random filename) | obscurity of filename |
| Member IP + name sent to third parties | ipapi.co (IP on every login), translation provider (see below), SMTP relay | - |
| Rendered member pages | Translation provider + `cache/i18n/` | see below |

**Translation data flow:** `mvcI18nStart()` is called from `pages/user/_partials/head.php:9` as well as the public head. For a member who picks a non-English language, the full rendered dashboard HTML is parsed and every text node is sent to the configured translator (MyMemory by default, as URL query parameters over GET). The member dashboard renders the member's full name server-side (`pages/user/dashboard.php:95`, topbar `:165`). No element carries `translate="no"` or `class="notranslate"` (grep of `pages/`: 0 hits). So member names are sent to the translation provider and stored in `cache/i18n/{locale}.json` and in whole-page cache files `cache/i18n/pages/{locale}/*.html` `[INFERRED from code path; cache contents not inspected]`.

### 8.10 File uploads
| Upload | Validation | Storage | Served |
|---|---|---|---|
| Avatar | `finfo` MIME png/jpeg/webp, 10 MB, GD re-encode to 512px square (strips payloads if GD present) | `uploads/profiles/{uid}_{time}.{ext}` | Public URL |
| KYC | `is_uploaded_file`, `finfo` png/jpeg/webp/pdf (selfie no pdf), 10 MB each | `uploads/kyc/{uid}/{field}_{date}_{rand16hex}.{ext}` | Admin streamer only |
| Contact attachment | `finfo` pdf/jpeg/png/doc/docx, 5 MB | `uploads/contact/{rand32hex}.{ext}` | **Public URL**, emailed to operator |

`uploads/.htaccess` removes PHP handlers and denies script extensions. PHP limits: 12M/14M (`.user.ini`, `.htaccess:235-242`).

KYC PDFs are served `inline` with the admin origin (`kyc_file.php:85`). A crafted PDF renders in the browser's PDF viewer; risk is low but non-zero.

### 8.11 SSRF
Outbound URLs are fixed hosts (NOWPayments, translation APIs, ipapi.co). `getLocationFromIP($ip)` interpolates `REMOTE_ADDR` into a fixed-host URL path. No user-controlled URL fetch found.

### 8.12 Open redirects
- `api/public/set_language.php:58-60` accepts any `return` starting with `/` but not `//`. A value such as `/\example.com` passes and some browsers normalize backslash to slash `[INFERRED]`. Requires a valid CSRF token, which limits exploitation.
- Login redirects are server-chosen constants.

### 8.13 Mass assignment
Not applicable (no ORM). Updates list columns explicitly.

### 8.14 Dependencies
- PHPMailer 6.11.1 (2025-09-30): current as of lock date; no known CVE against this version `[INFERRED; not checked against a live advisory DB]`.
- jQuery 3.7.1: current major. Bootstrap JS 5.0.2 (2021): old but no known XSS CVE in 5.0.x JS `[INFERRED]`. Chart.js 4.4.1.
- `composer.lock` is gitignored (`.gitignore:30`) yet present, so installs are not reproducible from the repo; `vendor/` is shipped by rsync from the developer's machine.

### 8.14b Unauthenticated endpoints reachable from the internet
| Endpoint | Exposure |
|---|---|
| `api/payments/create_crypto_payment.php` (direct request) | **Creates live NOWPayments invoices with the merchant API key for any POSTed amount, and overwrites `transactions.details` for any `reference` supplied** (`create_crypto_payment.php:438-447`, `:404-416`). Returns the raw provider response. |
| `api/payments/now_webhook.php` | Signature-protected |
| `api/cron/investment_cron.php` | Allowed when `REMOTE_ADDR` is 127.0.0.1/::1. If any local reverse proxy fronts PHP, every request appears local `[INFERRED]`. Not in the `.htaccess` deny list. |
| `api/public/*`, `api/auth/*` | By design, CSRF + rate limited |

---

## 9. Frontend

**Confidence: Medium** (JS sampled, not fully read).

| Aspect | Finding |
|---|---|
| Framework | None. jQuery 3.7.1 + vanilla JS, one or more scripts per page |
| Meta-framework | None |
| State management | None; DOM is state. Theme in `localStorage['mvc-theme']`; language in `mvc_lang` cookie |
| Routing | Server-side via `.htaccess` |
| Styling | Hand-written CSS: `mvc-design.css` (marketing), `mvc-dashboard.css` (dashboard re-skin, loaded last to win the cascade), plus inherited template CSS (`dashboard.css` 8,812 lines, `main.css` 3,293). Inline `style=""` attributes in places (CSP allows). |
| Design system | Custom tokens (e.g. `--color-bg-page`, `--space-2` in `pages/public/_partials/footer.php`). Phosphor icons, Switzer font, self-hosted |
| Forms | HTML forms intercepted by JS, posted via `fetchApi()`; server re-validates |
| API layer | `assets/js/api.js`: `fetchApi()` (POST JSON + CSRF header), `fetchApiGet()`, unified `{status,message,data}` handling, toast helpers. Some admin scripts use `$.ajax` directly (`assets/js/admin/transactions.js`) |
| Client auth | Cookie session only; no tokens in JS. Pages redirect server-side when unauthenticated. 401 responses surface as error toasts; no automatic redirect to login was found in `fetchApi()` |
| Bundling / code splitting | None. `?v=mtime` cache busting (`config/assets.php`) |
| Images | PNG logos, avatars re-encoded to 512px |
| i18n | Server-side output-buffer translation of whole pages (134 languages claimed in commit `7806cb9`). `assets/js/translate.js` is the language picker; its comment still mentions "Google's element.js" (`translate.js:22`, `pages/user/_partials/head.php:105-107`), which `i18n.php:7-12` says is retired |
| Accessibility | 248 `aria-*` attributes across `pages/`; landmark elements in 18 page files; `prefers-reduced-motion` handled (6 rules) |
| Theming | Light/dark via `data-theme` on `<html>`, set pre-paint by a hashed inline script |
| Breakpoints | 575, 640, 768/767, 1200 px (mixed) |
| Dead code | `assets/js/admin/utilities.js` (not referenced by any page; also contains a stray `a` token at line 16 that would throw `ReferenceError` if that branch ran); `assets/css/min.css/*.min.css` (three 0-byte files); `api/auth/logout.php` (UI uses the page route) |

---

## 10. UI/UX findings

**Confidence: Low-Medium.** Based on code and commit messages, not on using the UI. The `shots/` screenshots were not reviewed.

- **Confirmation for destructive actions:** themed `mvcConfirm()` replaces `window.confirm()` (`pages/user/_partials/head.php:81-85`, commit `fdea47d`).
- **Feedback:** toast system in `api.js:254`; some screens use inline messages (`displayMessage`), so the feedback surface is not uniform.
- **Money-rule disclosure:** withdrawal/deposit min/max are returned by `get_wallet_summary` so the form can show limits up front (`wallet.php:681-687`).
- **KYC gate UX:** a blocked withdrawal returns `code: kyc_required`, which the dashboard turns into a link to `/dashboard.kyc` (`wallet.php:461-463`).
- **Admin pending withdrawals** list shows user, amount, method, reference, but **not the payout destination** (`get_pending_withdrawals.php:756-785` selects no `details`). Admins rely on the notification email (`details_html`) to know where to send money.
- **Contact messages** have no admin inbox; they exist only in email and the DB.
- **Announcements** support categories and drafts; members see the 5 latest published.
- **Double-escaped text** in admin dashboard lists (8.2).
- **Admin user list** column labelled "last_login" shows `created_at` (`api/admin/users.php:178`).
- **Member CSV export** exports only the current page of results (`api/backend/transactions.php` export runs after `LIMIT/OFFSET`).
- **Onboarding:** register -> OTP step in the same form -> dashboard. No guided first-deposit flow found.
- **Empty states:** present in dashboard and admin tables (`mvc-empty` class).

---

## 11. DevOps, infra, deployment

**Confidence: Medium** (derived from `scripts/deploy.sh`; server not inspected).

| Concern | Finding |
|---|---|
| Hosting | Shared cPanel account on "Spaceship", SSH alias `hostingserver2`, docroot `/home/uvammbciwx/maverencapital.com` (`deploy.sh:5,21-22`). Web server LiteSpeed (`.htaccess:216-217`); host time zone America/New_York (`investment_cron.php:47-51`) |
| Co-tenancy | The same cPanel account and **the same database user** (`uvammbciwx_michael`, `ALL PRIVILEGES`) serve at least five other sites named in `deploy.sh:164-166`: arqoracapital, averoninvestments, crestvalebank, goldcrestmining, providencemining. A compromise of any one site's `.env` yields read/write on all of their databases. |
| Orchestration | None (no Docker, Compose, K8s) |
| Reverse proxy / LB | `[UNKNOWN]`. Code is proxy-aware for HTTPS detection only |
| CI/CD | None. No `.github/`, no pipeline files. Deploys are manual: `./scripts/deploy.sh` from the developer's machine |
| Deploy steps | 1) SSH preflight 2) regenerate `.env.production` from local `.env` + `.env.dbpass` 3) check required keys non-empty 4) `rsync -az --delete` repo -> docroot (with excludes) 5) `scp` `.env.production` -> `.env` (chmod 600) and `deposit_addresses_live.sql` 6) create dirs/perms 7) ensure DB exists, grant shared user, verify creds 8) baseline if empty 9) `migrate.php` 10) **apply `deposit_addresses_live.sql` every time** 11) add crontab line if missing 12) curl 6 public URLs |
| Environments | local (Laragon/XAMPP per `.htaccess:64`, or Apache locally per `deploy.sh:122-123`) and production. No staging. |
| Zero downtime | No. rsync overwrites files in place, then migrations run; during that window new code can run against the old schema. |
| Migrations in deploy | Yes, automatically, after file sync, with no pre-migration backup |
| Backups | **Not found in codebase.** No backup script, no dump before migration. cPanel backups `[UNKNOWN]`. The crontab is backed up before edit (`deploy.sh:240`). |
| DNS / domains | `[UNKNOWN]` |
| TLS | `[UNKNOWN]` (cPanel AutoSSL `[INFERRED]`). HSTS with `preload` is sent. |
| Logs | Files in `logs/`: `php-error.log`, `security.log` (JSON lines or plain), `email.log`, `investment_cron.log`, `cron.log`, `wallet_debug.log`. No rotation, no shipping. |
| Monitoring / uptime / alerting | None found |
| Error tracking / metrics | None |
| Feature flags | None |

Deploy-time hazards (details in Section 17): live deposit addresses re-applied and non-listed addresses deactivated on every deploy; `.env.dbpass` and `.claude/` shipped to docroot; comment says live SQL is "removed again once applied" but no removal step exists (`deploy.sh:105-113,225-228`).

---

## 12. Performance

**Confidence: Medium**

- **Per-request overhead:** `.env` file parsed on every request; new PDO connection per `getPDO()` call (some requests open 2-3); `SET time_zone` per connection.
- **Synchronous email:** most write endpoints send 2 SMTP messages inline. Login sends 2 (member alert + operator alert) plus a geo-IP HTTP call (2s timeout). SMTP latency is directly added to user-facing latency.
- **Admin broadcast:** N sequential SMTP sessions in one HTTP request; will hit PHP `max_execution_time` at a few hundred recipients `[INFERRED]`.
- **Translation:** cold page render with a non-English locale can issue up to 120 translator calls (12 in parallel, 12s timeout) (`i18n.php:46-48`); DOM parse ~1s per page (comment `:87-89`); whole-page cache mitigates. A circuit breaker stops calls for 30 minutes after quota errors.
- **Read endpoints that write:** `get_wallet_summary` and `api/backend/dashboard.php` both issue UPDATEs to `wallets` on read.
- **Caching:** HTTP expiry headers for static assets (`.htaccess:247-255`); no data caching.
- **Indexes:** see 5.12.
- **At 10x traffic** `[INFERRED]`: first limits are shared-hosting PHP worker count combined with synchronous SMTP; `rate_limit_hits` insert on every guarded request; full scans on `transactions` for admin pending counts; cron runtime grows linearly with active positions and sends one email per payout.

---

## 13. Testing and quality

**Confidence: High**

| Item | Finding |
|---|---|
| Unit tests | None. No `tests/`, no PHPUnit in `composer.json` |
| Integration / E2E | None. `shots/` screenshots indicate manual headless-Chrome checks (`.gitignore:60-62`) |
| Static analysis | None configured (no PHPStan/Psalm config). `php -l` on all tracked PHP files: **no syntax errors** (PHP 8.3.6, run during this audit) |
| JS linting | None (no ESLint). `.hintrc` for webhint only |
| Code style | No formatter config. Mixed indentation (e.g. `api/backend/wallet.php` indents the top-level block; `process_withdrawal.php:343-383` unindented) |
| Test data | `scripts/seed_test_accounts.php` |
| CI gates | None |
| Manual QA gaps | No automated check of: the money paths (deposit/withdraw/invest/cron), IPN signature verification against a real NOWPayments payload, role matrix, CSP hash freshness (`scripts/csp_hashes.php --check` exists but is not wired to anything) |

Code comments are unusually detailed and document the reasoning behind most past fixes. That is the main source of institutional knowledge.

---

## 14. Observability

**Confidence: High**

| Item | Finding |
|---|---|
| Log levels | None. `error_log()` free text, plus `logSecurityEvent()` JSON lines, plus `mvcSecurityLog()` plain lines to the same `security.log` (two formats in one file) |
| Request / correlation IDs | None |
| Business event logging | `email.log` (every send), `investment_cron.log`, `wallet_debug.log` (NOWPayments errors, written with `mkdir 0777`) |
| Audit log for sensitive operations | **Absent for almost everything.** `logAdminAction()` exists but is never called and its table does not exist. Not audited: balance edits, deposit/withdrawal approvals, deposit address changes, plan/rate changes, bonuses, user edits/deletes, broadcasts, settings changes. Audited in `security.log`: KYC review, KYC document view, role denial, CSRF rejection, KYC submission, withdrawal blocked by KYC, login lockout, contact honeypot. |
| Dashboards | None |

---

## 15. Integrations inventory

**Confidence: High** (code), Low (costs/contracts).

| Service | Purpose | SDK | Credentials | Failure mode | Cost signal |
|---|---|---|---|---|---|
| NOWPayments | Crypto invoices + IPN | cURL, hand-written | `NOWPAYMENTS_API_KEY`, `NOWPAYMENTS_IPN_SECRET` in `.env` | Invoice creation failure returns an error to the member; pending txn row remains. IPN signature mismatch -> 403 -> deposits stay pending with no alert. | Provider fees `[UNKNOWN]` |
| SMTP relay (SpaceMail per `deploy.sh:52`) | All email | PHPMailer | `SMTP_*` | Failure logged, request continues; registration/OTP report failure honestly (`mvcEmailSent`) | `[UNKNOWN]` |
| MyMemory / Google Translate / DeepL | Page translation | cURL | `I18N_DRIVER`, `I18N_API_KEY`, `I18N_EMAIL` | Falls back to English; circuit breaker | Google/DeepL per-character billing if selected; commit `24500f0` says the Google driver was selected |
| ipapi.co | Geo-IP on login | `file_get_contents` | none | "Unknown Location" | Free tier rate limits `[UNKNOWN]` |
| Smartsupp | Live chat | browser script | site key in JS | Widget absent | `[UNKNOWN]` |
| cPanel `uapi` | DB provisioning during deploy | CLI | SSH key | Deploy aborts | - |

---

## 16. Business logic and workflows

**Confidence: High**

### 16.1 Signup -> verification -> first action
```
pages/public/register.php -> api.js -> POST api/auth/register.php
  rate limit 'register' (5/h/IP), validate first/last/email/password(>=8)
  reject if email in users OR admins
  INSERT users (email_verified=0) ; INSERT wallets(balance 0)
  INSERT email_verifications (6-digit, 10 min) ; send 'email_verification'
  -> client shows OTP step with user_id
POST api/auth/verify_email.php {user_id, otp}
  rate limit 'otp', 5 wrong guesses burns code
  UPDATE users.email_verified=1 ; regenerate session ; set $_SESSION[user_id,...]
  send 'welcome_user' -> redirect /dashboard
```

### 16.2 Login
`api/auth/login.php`: throttle -> lookup -> `password_verify` -> status check -> rehash -> unverified branch returns `requires_verification` -> regenerate session -> `last_login` -> email member `login_alert` + operator `admin_user_login_notification` (with IP, browser, geo-IP location) -> `login_logs` -> `/dashboard`.

### 16.3 Deposit - crypto checkout (NOWPayments)
```
wallet.php initiate_deposit {amount, method:'secure_exchange'}
  rate limit, platform min/max from settings
  INSERT transactions (deposit, pending, ref MVC-DEP-...)
  createCryptoPayment() -> POST https://api.nowpayments.io/v1/invoice
     ipn_callback_url = APP_URL/api/payments/now_webhook.php
  store invoice details on txn ; email member + operator ; return redirect_url
Member pays on NOWPayments
now_webhook.php (IPN) -> verify HMAC -> find txn by order_id
  success status -> txn completed + wallet.balance += price_amount, total_deposited +=
  failed/expired/refunded -> txn failed, email once
```

### 16.4 Deposit - manual transfer to published address
```
wallet.php get_deposit_networks -> active deposit_addresses
initiate_deposit {method:'deposit_address', deposit_address_id}
  snapshot address into txn.details (so rotation never changes past instructions)
  email 'deposit_details_provided' with address ; email operator
confirm_deposit_payment {reference, tx_hash?}  ("I have paid")
  details.user_marked_paid=true ; email operator + member
Admin: api/admin/process_deposit.php {id, action:'complete'|'cancel'}
  FOR UPDATE + CAS pending -> completed ; wallet += amount ; commit ; email
```
No on-chain verification is automated; the admin checks a block explorer manually.

### 16.5 Invest
```
invest.php start_investment {plan_id, amount}
  plan active, min<=amount<=max
  projectInvestment(): per_payout = round(amount*roi/100,2);
                       payouts = floor(duration/7|30);
                       maturity = walk schedule forward payouts times
  BEGIN ; wallet FOR UPDATE ; balance check ; balance -= amount
  INSERT investments (snapshot terms) ; INSERT transactions(investment, completed) ; COMMIT
  email member + operator
```

### 16.6 Payout and maturity (cron)
See 6.7. Also a member-triggered path: `invest.php unlock_investment` releases principal for a matured active position if the cron has not yet done so. It records the release as `type='investment'`, while the cron uses `type='investment_release'`.

Edge case `[INFERRED]`: if a member calls `unlock_investment` on maturity day after midnight and before the 01:00 cron, the position becomes `completed` and the final period's ROI (normally paid by cron step 1 on that day) is never paid, because step 1 only selects `status='active'`.

### 16.7 Withdrawal
```
wallet.php withdraw_request {amount, method:'local_bank'|'wallet_address', details}
  rate limit ; users.kyc_status must be 'approved' ; min/max from settings
  read wallet ; if balance < amount -> error          <-- outside transaction
  BEGIN ; balance -= amount ; pending_withdrawals += amount ; INSERT txn(withdraw,pending) ; COMMIT
  email member 'withdrawal_initiated' ; email operator with bank/crypto details (HTML)
Admin process_withdrawal.php
  complete -> CAS pending->completed ; pending_withdrawals -= ; total_withdrawn += ; email ; COMMIT
  cancel   -> CAS pending->failed ; balance += ; pending_withdrawals -= ; email ; COMMIT
```
The actual payout (bank transfer or crypto send) happens outside the system; nothing records the outgoing transaction hash or bank reference.

### 16.8 Moderation / approval flows
- KYC: member `submit` (3 files + fields) -> `users.kyc_status='pending'` -> admin `review` approve/reject (reason required) -> email. Rejected members can resubmit; history retained.
- Manual deposits and all withdrawals require admin approval.

### 16.9 Where the yield comes from (observed facts only)
- ROI credits are produced solely by `api/cron/investment_cron.php`, which increments `wallets.balance` and `investments.roi_earned` by `amount * roi_percent / 100` per period.
- Admins can also add arbitrary "bonus" ROI (`api/admin/plans.php` `investment_bonus`, up to 1,000,000 per action), change a live position's rate (`edit_investment`, 0-999.99%), and set any wallet balance to any value (`api/admin/wallets.php` `update_balance`).
- The codebase contains **no** integration with, or record of, any external investment activity: no brokerage/custody/exchange/bank API, no asset or holdings table, no NAV or performance data, no reconciliation between member balances and funds held.
- Published plan terms: 1.10% per week for 13 weeks (Low risk), 1.65% per week for 26 weeks (Moderate), 6.00% per month for 12 months (Moderate) (`dbschema/maveren_create.sql:388-404`). Marketing copy says capital is deployed into fixed-income instruments and investment-grade credit, and elsewhere "Capital is at risk ... rates are not guaranteed".
- Whether an off-platform investment operation exists that funds these credits is `[UNKNOWN]` and is listed in Section 19 as the most important owner question.

### 16.10 Refund / reversal
- Withdrawal cancel refunds the wallet (16.7).
- Deposit cancel marks the txn failed (no money had moved).
- `investment_close` (admin) supports `settle` or `cancel` (refund principal).
- No reversal for a completed deposit or completed withdrawal. Corrections are only possible via the absolute `update_balance`, which leaves no ledger entry.

### 16.11 Notifications
36 email templates (`api/utilities/email_temps.php`). Every money event emails the member and usually the operator (`SMTP_TO`). No in-app notifications except announcements. No SMS/push.

---

## 17. Known issues, debt, and risk

Severity is the auditor's judgement. File references point to the exact code.

### 17.1 Critical and high

| ID | Severity | Issue | Evidence | Consequence |
|---|---|---|---|---|
| C-1 | Critical | Withdrawal double-spend race. Balance is checked before `BEGIN`, with no `FOR UPDATE`, and the debit `UPDATE` has no `AND balance >= ?` guard. `balance` is a signed DECIMAL. | `api/backend/wallet.php:524-549` | N parallel `withdraw_request` calls each pass the check and each debit, driving the balance negative and creating N pending withdrawals. Rate limits do not stop simultaneous requests (all read the count before any record lands). |
| C-2 | Critical | Unauthenticated invoice creation and transaction tampering. The "Allow direct call for testing" block runs whenever the file is requested directly, with no session, CSRF, or rate limit. | `api/payments/create_crypto_payment.php:438-447`, `:404-416` | Anyone can create invoices on the live merchant account for arbitrary amounts, and can overwrite `details` (invoice URL, provider id) of any transaction whose `reference` they know or guess. |
| C-3 | High | IPN idempotency is non-atomic. Status is read, then `UPDATE ... WHERE id = ?` with no `AND status='pending'` and no row lock. | `api/payments/now_webhook.php:611-670` | Two concurrent deliveries of the same `finished` IPN (provider retry, or `confirmed` then `finished`) can both credit the wallet. |
| H-1 | High | Admin audit trail absent. `logAdminAction()` never called; `admin_logs` table does not exist. | `api/utilities/helpers.php:9-19` | Balance overrides, approvals, address changes, bonuses, rate edits and user deletions leave no record of who did what. |
| H-2 | High | Absolute balance override without ledger entry. | `api/admin/wallets.php:58-81` | `SUM(transactions)` can no longer be reconciled to `wallets.balance`; money can be created or removed invisibly. |
| H-3 | High | Hard user deletion cascades financial and KYC records; KYC files left orphaned on disk. | `api/admin/users.php:262-275`, FKs in `maveren_create.sql` | Irrecoverable loss of transaction history; identity documents kept with no DB owner. |
| H-4 | High | `executeQuery()` in `admin/plans.php` (and `users.php`, `wallets.php`, `transactions.php`) swallows `PDOException`. Used inside multi-statement transactions. | `api/admin/plans.php:45-54`, bonus/term/close blocks | A failed wallet UPDATE is silently skipped and the transaction commits: e.g. `roi_earned` increases and a `roi_payout` txn is recorded with no wallet credit. |
| H-5 | High | Shared database user with ALL PRIVILEGES across six sites on one cPanel account. | `scripts/deploy.sh:24,162-186`, `make_env_production.sh:285-296` | Any compromise of a sibling site exposes Maveren's members, KYC data and balances, and vice versa. |
| H-6 | High | Every deploy re-applies `deposit_addresses_live.sql`, which upserts the listed addresses and **deactivates every other row**. | `scripts/deploy.sh:225-228`, foot of `dbschema/deposit_addresses_live.sql` | Addresses an admin adds or changes in the UI are silently reverted or hidden on the next deploy. Admin edits to listed rows are overwritten. |
| H-7 | High | State-changing actions accepted via GET, exempt from CSRF. | `api/backend/invest.php:68`, `api/admin/announcements.php:41`, `api/admin/settings.php:36` | Only `SameSite=Strict` prevents cross-site triggering of `start_investment`, `unlock_investment`, announcement delete, and settings update. |
| H-8 | High | KYC review locked to `super_admin` by a role-name mismatch. | `config/roles.php:7`, `api/admin/kyc.php:53`, `api/admin/kyc_file.php:36` | Managers and support staff cannot process KYC; withdrawals queue behind the owner. |
| H-9 | High | Every invite-code admin registration yields `super_admin`; no in-app admin management; no MFA. | `api/auth/admin_register.php:92` | One leaked invite code grants control of deposit addresses and balances. |
| H-10 | High | Member PII (names) sent to a third-party translator and cached on disk for non-English readers of member pages. | `pages/user/_partials/head.php:9`, `pages/user/dashboard.php:95`, `api/utilities/i18n.php` | Privacy/regulatory exposure; no disclosure found. |
| H-11 | High | Stored HTML injection into the admin transactions table via member-controlled `full_name`. | `assets/js/admin/transactions.js:107-127` | Mitigated by CSP (no script execution) but allows UI spoofing inside the admin panel. |

### 17.2 Medium

| ID | Issue | Evidence |
|---|---|---|
| M-1 | Cron step 1 has no row lock or CAS on `next_payout_date`; wallet is credited even if the investment UPDATE matched 0 rows. Concurrent cron runs (manual + scheduled) could double-pay. | `api/cron/investment_cron.php` step 1 |
| M-2 | `unlock_investment` before the daily cron on maturity day skips the final ROI payout (16.6). | `api/backend/invest.php:478-530` |
| M-3 | `wallets.user_id` not unique; auto-create in 3 places. | 5.4 |
| M-4 | `invest` rate-limit per-account bucket never recorded; `reset` per-email bucket never recorded. | `invest.php:340-341`, `forgotpassword.php:49-50` |
| M-5 | `admin_register.php` opens a privileged session without `session_regenerate_id`. | `api/auth/admin_register.php:102-109` |
| M-6 | Email change needs no password and no re-verification. | `api/backend/profile.php` `update_profile` |
| M-7 | `process_withdrawal.php` sends email before `COMMIT`, holding row locks during SMTP. `process_deposit.php` explicitly fixed this. | `api/admin/process_withdrawal.php:161-172` and `:202-214` |
| M-8 | Cron endpoint web-reachable from loopback; no `.htaccess` deny. | `api/cron/investment_cron.php:18-21`, `.htaccess:41` |
| M-9 | `.htaccess` environment detection: `SetEnv` is not conditional on `RewriteCond`, and variables set by `SetEnv` are not visible to `mod_rewrite` on Apache. `MVC_ENV` therefore ends up `prod` on every host, and it overrides `APP_ENV` in `config/env.php:47`. The HTTPS/non-www redirect rule may never fire. | `.htaccess:65-85`, `config/env.php:47` `[INFERRED; LiteSpeed behaviour not verified]` |
| M-10 | Contact attachments stored under a web-reachable path; operator email link built as `APP_URL . 'uploads/...'` with no slash (broken link when `APP_URL` has no trailing slash, which is the documented form). | `api/public/contact.php:158-165,236`, `.env.example:26` |
| M-11 | `.env.dbpass` and `.claude/` rsynced to docroot. | `scripts/deploy.sh:84-103` |
| M-12 | No backups before automated migrations. | Section 11 |
| M-13 | Pending-withdrawal admin view omits payout destination. | `api/admin/get_pending_withdrawals.php` |
| M-14 | Admin broadcast `donors` group queries a non-existent table. | `api/admin/email.php:102-106` |
| M-15 | Announcements table created at request time on every call. | `api/admin/announcements.php:51` |
| M-16 | KYC review updates `users.kyc_status` unconditionally even if the submission CAS matched 0 rows (concurrent reviewers can leave history and current state disagreeing). | `api/admin/kyc.php:189-196` |
| M-17 | Webhook does not assert `type='deposit'` / `method='secure_exchange'` on the referenced transaction. | `api/payments/now_webhook.php:598-608` |
| M-18 | Open redirect edge case with backslash in `set_language.php`. | `api/public/set_language.php:58-60` |
| M-19 | Float money handling; sub-cent amounts accepted then rounded by MySQL. | 5.14 |

### 17.3 Performance concerns
Synchronous SMTP in every write path; broadcast loop; missing `transactions(type,status)` and `(created_at)` indexes; read endpoints issuing writes; `.env` parse and fresh DB connection per request. See Section 12.

### 17.4 Technical debt
- No framework, no shared request/response layer, duplicated helpers with divergent semantics (6.4).
- Three copies of cadence arithmetic that must stay identical.
- Inherited template CSS (`dashboard.css` 8.8k lines, `main.css` 3.3k) overridden by later re-skin files.
- `error_reporting(0)` at the top of many endpoints (e.g. `api/auth/login.php:6-7`, `wallet.php:2-3`) until `env.php` loads and re-enables it; errors before that point are not logged.
- Relative `require` paths in some files (`api/backend/card_usage.php:10-11`, `api/admin/users.php:21-22`, `process_deposit.php:8-10`) depend on the working directory.
- Two log formats in one `security.log`.

### 17.5 Deprecated / aging dependencies
Bootstrap JS 5.0.2 (2021). Otherwise current as of lock dates. `composer.lock` not tracked.

### 17.6 Dead code
`assets/js/admin/utilities.js`; `assets/css/min.css/*.min.css` (0 bytes); `api/auth/logout.php`; `config/roles.php` `$permissions` and `hasPermission()`; `bank_details` table; `wallets.cash_mailing_address`, `wallets.wallet_deposit_address`; `deposit_addresses.qr_path`; `MAX_WITHDRAWAL_ATTEMPTS` constant; `.env` keys `SESSION_LIFETIME`, `PASSWORD_HASH_COST`, `NOWPAYMENTS_PUBLIC_KEY`; `sendEmail()` `debug` and `cc_admin` options (no callers found).

### 17.7 TODO / FIXME / HACK
Search over `api/`, `pages/`, `assets/js/` (excluding minified), `config/`, `scripts/`, `dbschema/`: **0** `TODO`/`FIXME`/`HACK` markers (1 false positive: an `XXX` inside a BIC placeholder, `pages/user/wallet.php:511`). Known issues are documented in prose comments instead.

### 17.8 Inconsistent patterns
Response envelopes (`status` vs `success`); input parsing (JSON only vs JSON-or-POST-or-GET); role gating (some admin endpoints ungated, one custom check in `admin/email.php`); server-side vs client-side HTML escaping; transaction `type` naming for principal release (`investment` vs `investment_release`); `dashboard.php` formats `method` with `ucfirst` while the rest uses `formatPaymentMethod()` (`api/backend/dashboard.php:201`).

### 17.9 Undocumented behaviour
- Admin receives an email on every member login.
- `get_wallet_summary` rewrites `total_earnings` and `total_investments` on read.
- Deploy resets deposit addresses (H-6).

---

## 18. Inconsistencies and contradictions

| # | Source A says | Source B says |
|---|---|---|
| 18.1 | `.htaccess:64-70` sets `MVC_ENV=dev` on localhost and `prod` elsewhere | `SetEnv` is unconditional (`RewriteCond` only gates `RewriteRule`); the last `SetEnv` wins, and `config/env.php:47` lets `MVC_ENV` override `APP_ENV` |
| 18.2 | `api/cron/investment_cron.php:13`: `0 2 * * *` | `scripts/deploy.sh:241`: `0 1 * * *`; cron comments at `:47-53` also say 01:00 |
| 18.3 | `scripts/set_admin_role.php:6-9`: register "hardcodes role='manager'" | `api/auth/admin_register.php:92`: `$role = 'super_admin'` |
| 18.4 | `api/admin/kyc_file.php:35`: "Any admin may review" | Gate admits only `super_admin` (7.6) |
| 18.5 | `config/roles.php`: roles `user`, `support_admin`, `super_admin` | `admins.role` ENUM: `super_admin`, `manager`, `support` |
| 18.6 | `.htaccess:150-173`: "exactly five inline script blocks", four hashes listed by prefix | Policy has 3 hashes; current code has 2 distinct inline scripts; listed prefixes match none |
| 18.7 | `i18n.php:14-17`: "four inline-script hashes" | See 18.6 |
| 18.8 | `i18n.php:7-12`: Google widget retired, translation is server-side | `translate.js:22` and `pages/user/_partials/head.php:105-107`: Google's element.js "fetched only when a language other than English is actually chosen" |
| 18.9 | `.env.example:31-45`: SMTP_USER etc. required | Local `.env` also carries `SESSION_LIFETIME`, `PASSWORD_HASH_COST`, `NOWPAYMENTS_PUBLIC_KEY`, none read by code |
| 18.10 | `deploy.sh:105-107`: live addresses SQL "sent separately and removed again once applied" | No removal command exists in `deploy.sh` |
| 18.11 | `maveren_create.sql:82`: `settings` is "legacy, retained so an old install drops cleanly" | `2026_09_03_settings.sql` creates a new, differently shaped `settings` table under the same name. On a legacy DB that never dropped the old table, `CREATE TABLE IF NOT EXISTS settings` is a no-op and the seeding `INSERT` fails on missing columns `[INFERRED]` |
| 18.12 | `migrate.php:424-429` header: "six loose .sql files" | 10 migrations now |
| 18.13 | `api/backend/invest.php:3`: `/api/backend/investment.php` | File is `invest.php` |
| 18.14 | `index.php:2`: "Entry point for Lymora"; `create_crypto_payment.php:5`: "Lymora/Maveren" | Product is Maveren Capital |
| 18.15 | `.gitignore:40`: "public repo" | The same repo tracks `scripts/deploy.sh` with host alias, docroot, DB name and shared DB user name |
| 18.16 | `set_admin_role.php:14`: `support` is "read-only" | `support` can read the full ledger + CSV export; cannot read KYC |
| 18.17 | Admin users table header "Last login" | Value is `created_at` (`api/admin/users.php:178`) |
| 18.18 | Principal release by cron: `type='investment_release'` | By member unlock: `type='investment'` (`api/backend/invest.php:526-528`) |
| 18.19 | Two modules solve "one-time code cooldown" differently | `mvcOtpCooldownRemaining` (DB clock) vs `contact.php:113` (`time() - strtotime(created_at)`, the pattern the codebase replaced elsewhere) |
| 18.20 | Settings page ceiling 1,000,000 | Plan `max_amount` seed 500,000; bonus limit 1,000,000; no single source |

---

## 19. Unknowns and owner-input needed

1. **Yield source and custody.** What external activity, if any, generates the returns credited by the cron, and where are member funds held? The code has no representation of it (16.9). This determines whether the marketing copy is accurate and what regulatory regime applies.
2. **Regulatory status.** Is the operator licensed or registered to offer these products, and in which jurisdictions? Is there a privacy policy, terms of service, risk disclosure, and a lawful basis for KYC data processing and cross-border transfer (translation, SMTP, geo-IP)?
3. **Revenue model.** No fee logic exists. How does the operator earn money?
4. **Legal entity and license** for the code (no LICENSE file). Relationship to the inherited Aldernorth / TitanX / Lymora codebases and whether any third party holds rights.
5. **Is the GitHub repository public?** `.gitignore` says so. If yes, this document and `scripts/deploy.sh` disclose infrastructure details and unfixed vulnerabilities.
6. **Production PHP and MySQL/MariaDB versions**, `session.gc_maxlifetime`, `max_execution_time`, whether a proxy/CDN fronts the site (affects rate limiting and the cron loopback check).
7. **Backups:** does cPanel take them, how often, has a restore been tested?
8. **Monitoring:** is anyone alerted when the cron exits 1, when IPNs 403, or when SMTP fails?
9. **Who operates payouts**, and where are outgoing bank/crypto references recorded?
10. **Admin roster:** how many admins exist, with which roles, and who holds `ADMIN_INVITE_CODE`?
11. **Translation driver in production** (`I18N_DRIVER`): which provider is actually receiving page content?
12. **Are the sibling sites on the same cPanel account** (named in `deploy.sh`) the same product under other brands, and do they share members or funds?
13. **Data retention policy** for KYC documents, login logs, and contact messages.
14. **Is the legacy `settings` table still present in production?** (18.11)
15. **NOWPayments account:** is it the Maveren account, or the "old TitanX merchant account" referenced in `.env.local`?

---

## 20. Recommendations

Ordered by priority. Each references its finding.

### P0 - fix before handling more member funds
1. **Make withdrawals atomic** (C-1): move the balance read inside the transaction with `SELECT ... FOR UPDATE`, or use `UPDATE wallets SET balance = balance - ? ... WHERE user_id = ? AND balance >= ?` and require `rowCount() === 1`. Consider `CHECK (balance >= 0)` (MySQL 8.0.16+).
2. **Delete the direct-call block** in `create_crypto_payment.php` (C-2), and deny `api/payments/create_crypto_payment.php` and `api/cron/` over HTTP in `.htaccess` (M-8).
3. **Make the IPN handler idempotent** (C-3): `UPDATE transactions SET status='completed' ... WHERE id=? AND status='pending'`, check `rowCount()`, credit only if 1; also assert `type='deposit' AND method='secure_exchange'` (M-17); credit the stored amount rather than the payload amount unless intentionally different.
4. **Stop `deploy.sh` from re-applying `deposit_addresses_live.sql`** on every run, or remove its retirement `UPDATE` (H-6). Remove the file from the server after first import (18.10).
5. **Answer Section 19 items 1-2** before further feature work. Several technical priorities depend on them.

### P1 - integrity and accountability
6. **Implement an admin audit log** (H-1): create `admin_logs` via migration, call it from every admin write (approvals, balance edits, bonuses, rate/term changes, address changes, user edits/deletes, settings, broadcasts).
7. **Replace `update_balance`** with signed adjustment entries that insert a `transactions` row (`type='adjustment'`, actor, reason) (H-2).
8. **Replace hard user deletion** with a `disabled`/anonymize flow that keeps the ledger; delete KYC files only under a documented retention policy (H-3).
9. **Make `executeQuery()` rethrow** in the admin files (as `deposit_addresses.php` already does), or remove it (H-4).
10. **Fix the KYC role gate** to `MVC_ROLE_ALL` or `MVC_ROLE_OPERATOR`, and delete or correct `config/roles.php` (H-8, 18.5).
11. **Reject state-changing actions over GET** in `invest.php`, `announcements.php`, `settings.php`, using the `wallet.php:60-83` pattern (H-7).
12. **Escape `tx.*` fields** in `assets/js/admin/transactions.js` (H-11).
13. **Cron hardening** (M-1): per-investment `SELECT ... FOR UPDATE` or CAS on `next_payout_date`; credit only when the investment update matched; add a lock file so two runs cannot overlap. Resolve M-2 by paying due periods inside `unlock_investment` or blocking unlock until the cron has run.

### P2 - security hygiene
14. **Admin provisioning** (H-9): default new admins to `support`; build admin management UI; add TOTP MFA for admins; call `mvcSessionElevate()` in `admin_register.php` (M-5).
15. **Separate database user** for this site with privileges on its database only (H-5). Rotate the shared password afterwards.
16. **Exclude `.env.dbpass`, `.claude/`, `_planning/`, `shots/`, `scripts/`, `dbschema/*.sql` from rsync** (M-11), and add `.claude` to the `.htaccess` deny list.
17. **Translation privacy** (H-10): disable `mvcI18nStart()` on member/admin pages, or mark personal data with `translate="no"`; document the provider in a privacy notice.
18. **Profile email change**: require current password and re-verification of the new address (M-6).
19. **Revoke other sessions** on password change/reset (store a per-user session version and check it).
20. **Move contact attachments** outside the web root and serve via an admin-only streamer like `kyc_file.php` (M-10).
21. **Fix `.htaccess` env detection** using `SetEnvIf Host ^localhost MVC_ENV=dev` or remove the override in `config/env.php:47` (M-9); verify the HTTPS/www redirect actually fires.
22. **Record the per-account buckets** for `invest` and `reset` (M-4).

### P3 - operability
23. **Backups**: a scheduled `mysqldump` plus an off-host copy, and a dump step in `deploy.sh` before `migrate.php` (M-12). Test a restore.
24. **Alerting**: email or webhook on cron exit != 0, on IPN signature failures, on SMTP failures above a threshold.
25. **Indexes**: `transactions(type, status, created_at)`, `transactions(created_at)`, UNIQUE `wallets(user_id)` (after de-duplicating) (5.12, M-3).
26. **Move email off the request path**: a `mail_queue` table drained by cron, starting with admin broadcast (Section 12).
27. **Show payout destination** in the admin pending-withdrawals view, and record outgoing payout references on completion (M-13, 16.7).
28. **Admin contact inbox** reading `contact_messages`.

### P4 - maintainability
29. **Tests**: at minimum, integration tests for deposit (both routes), IPN signature, withdraw, invest, cron catch-up, and the admin role matrix. Wire `php -l` and `scripts/csp_hashes.php --check` into a pre-deploy step or CI.
30. **Consolidate helpers**: one `jsonResponse`, one `executeQuery` (throwing), one reference generator, one cadence module shared by `invest.php`, cron and `admin/plans.php` (6.4).
31. **Track `composer.lock`** and install `vendor/` on the server from it (8.14).
32. **Remove dead code** listed in 17.6 and update stale comments listed in Section 18.
33. **Integer cents** (or strict `bcmath`) for money arithmetic, and reject amounts with more than 2 decimal places (M-19).

---

### Readiness check for a new engineer

Question: "Would a new engineer joining tomorrow be productive by end of week?"
- **Yes, for code navigation:** the routing map, endpoint table, schema and workflows above cover every tracked file, and the in-code comments are thorough.
- **Gaps:** there is no local setup guide (which web server, PHP extensions required: `pdo_mysql`, `curl`, `fileinfo`, `gd`, `mbstring`, `intl` not required, `dom`/`libxml` for i18n), no staging environment, no tests to confirm a change is safe, and the business questions in Section 19 are unanswered. A `README.md` with local setup steps and the Section 19 answers would close most of this.
