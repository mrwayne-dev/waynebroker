# Gotham Investments — Rebuild Plan

| Field | Value |
|---|---|
| Predecessor | Maveren Capital (procedural PHP + jQuery, single web root) |
| New product | Gotham Investments — trading terminal + fixed-plan investments + Batman-inspired institutional identity |
| Author | Planning pass, pre-code |
| Status | Draft for review before Claude Code kickoff |
| Revision | R2 — updated for shared-hosting deployment and portfolio/mock scope |

---

## R2 — What changed since R1 and why

Two constraints came in after R1 was written; they cascade through several sections.

**C1. Shared-hosting deployment (cPanel/LiteSpeed, no root, no long-running processes).**
Drops out of the R1 plan: Docker in production, Laravel Reverb WebSocket server, Redis, PostgreSQL, Nginx tuning, Horizon queue workers, blue/green containers.
Replaces with: MySQL 8, database session driver, file cache, database queue drained by a minutely `queue:work --stop-when-empty` cron, HTTP polling (or Pusher if we want real-time later), rsync/git-hook deploys of a locally pre-built asset bundle.
Sections rewritten: 3 (stack), 4 (architecture), 6 (data-model DB engine notes), 16 (realtime), 17 (payments/reconciliation), 19 (CI/CD).

**C2. Portfolio/mock project with Gotham-owned fictional markets ("Gotham Charts").**
The trading terminal shows Gotham-branded instruments (e.g. `GTM-A1`, `GTM-A2`, or themed names like "Wayne Utility Index") rather than real FX pairs. Buy/sell outcomes are plan-tier-shaped. The framing makes it clearly a Gotham product with its own rules, closer to an in-app market game than a fake broker. Combined with portfolio/demonstration scope, the R1 Section 22 legal/custody/yield-source risks are downgraded from launch blockers to portfolio-hygiene notes (privacy policy, terms, plain disclaimers).
Sections rewritten: 7 (price feed and instruments), 8 (agent framing), 22 (risks trimmed), 23 (open questions trimmed).

**What did NOT change:** bounded contexts (5), data model shape (6 except DB engine), investment product model (9), user dashboard shape (10), admin dashboard shape (11), auth and MFA (12), the Maveren finding retirement matrix (13), anti-cloning layer (14), Batman design system (15), notifications (18), MCP kickoff (20), migration approach (21), delivery phases (24, tightened), kickoff checklist (25).

Every rewritten section is marked "R2:" at its opening line so a diff read is easy.

---

## R3 — Answers from the second round

- **Host:** Maveren's shared host (`hostingserver1` per Maveren audit `deploy.sh:5`), same Spaceship cPanel account. Gotham gets its own MySQL database and its own MySQL user (partial mitigation of Maveren H-5 cotenancy risk). Gotham lives at `waynebroker.mgbah.dev`; Maveren keeps `maverencapital.com` untouched.
- **Repo approach:** Fresh Laravel repo, seeded from Maveren as reference/spec (fork-and-evolve). Sections 21 and 24 updated to reflect this.
- **Instrument names:** proceed with `GTM-A*` / `GTM-B*` / `WYN-*` codes (Section 7.3 stands).
- **Plan tier params:** carry Maveren's rates (Section 9 table stands).
- **Crypto deposit addresses:** fresh addresses on the Gotham NOWPayments account. No copy from Maveren's live SQL.
- **NOWPayments credentials:** supplied at Phase 2 kickoff.
- **Domain:** `waynebroker.mgbah.dev`. Display name kept as "Gotham Investments" per the plan unless we resolve otherwise (see chat).

Sections rewritten: A5 (assumptions), 4 (host cotenancy note), 21 (fork-and-evolve migration), 24 (Phase 0 revised), 25 (checklist), 23 (answered questions removed).

---

## R4 — In-place strangler correction

R3 called this fork-and-evolve. It is not. Corrections:

- **Repo:** same Git repo as Maveren. Start in the existing Maveren codebase, install Laravel + Inertia + React into it, move current PHP into `_legacy/` for reference, port folder by folder into the Laravel structure, delete `_legacy/` when done.
- **Hosting:** Waynebroker and Maveren live on **different hosting servers**. The cotenancy risk from Maveren audit H-5 does not apply to Waynebroker. Section 4's cotenancy paragraph is retracted.
- **Local first:** Phase 0 focuses on local development only. Deployment is deferred until we audit the target host separately.
- **Data:** no importer needed. Waynebroker is a fresh install with no Maveren data. The one-shot importer sketched in R3 stays sketched — we don't build it until you say so.

Sections rewritten: A5 (host correction), 4 (cotenancy note retracted), 21 (in-place strangler + fresh install), 24 (Phase 0 revised for local-only), 25 (checklist).

---

## 0. How to read this document

This is a scoping and architecture plan, not a task list yet. Every section closes with a "Decisions needed" block. Read top-to-bottom once, mark disagreements in the margin, then we condense the accepted plan into the Claude Code kickoff prompt.

The Maveren audit is treated as ground truth for what exists today. Every critical/high finding in that audit (C-1, C-2, C-3, H-1 through H-11, and the M-series) is explicitly retired by name in Section 13. Nothing is inherited untouched.

---

## Table of contents

1. Scope interpretation and assumptions
2. What carries over and what does not
3. Target stack
4. High-level architecture
5. Bounded contexts
6. Data model rebuild
7. Trading terminal
8. Trading agent and plays engine
9. Investment product model
10. User dashboard (Exness-inspired, Batman-themed)
11. Admin dashboard
12. Authentication and authorization
13. Security overhaul (retiring Maveren audit findings)
14. Anti-cloning layer
15. Batman design system
16. Realtime infrastructure
17. Payments and custody
18. Notifications
19. Observability, testing, CI/CD
20. MCP discovery and self-aware kickoff
21. Migration strategy from Maveren
22. Risks, legal, compliance
23. Open questions
24. Delivery phases
25. Ready-for-Claude-Code checklist

---

## 1. Scope interpretation and assumptions

Restating the ask in one paragraph so a wrong assumption gets caught here rather than in code: Gotham Investments is a rebuild of Maveren Capital as a Laravel 12 + React 19 + Inertia 2 web application, with a Batman-inspired dark institutional visual identity, an Exness-style live trading terminal that displays multiple markets in a real-time candlestick chart, and a trading agent that unlocks per subscription plan. Members can place buy or sell orders that appear to route to those markets. Outcomes are shaped by a server-side "plays" engine keyed to the member's plan tier. The existing fixed-return plans remain, now positioned as the primary product with trading as a plan perk. The admin panel is modernised. Security, performance, and clone-resistance are hard requirements.

Explicit assumptions being made here — flag any I got wrong:

- **A1.** The terminal shows Gotham-owned instruments (Gotham Charts, e.g. `GTM-A1`). Prices are generated by Gotham. Outcomes are shaped by the plays engine per plan tier. This is the product, not a workaround.
- **A2.** Trading is optional. The primary money return is the fixed-return plans, structured like Maveren's.
- **A3.** "Same concept as what we have" means Maveren's fixed-return plans, wallet, deposit/withdraw flow, KYC and admin approval workflow all survive, just re-implemented cleanly.
- **A4.** "Batman-themed" means the aesthetic register of Wayne Enterprises the corporation, not the cape-and-cowl fandom register. No Batman trademarks, no DC iconography, no cartoon bats.
- **A5.** Production hosting is a **separate shared cPanel/LiteSpeed host** from Maveren's (Maveren is on `hostingserver1`; Waynebroker will be on a different server). New subdomain `waynebroker.mgbah.dev`. No cotenancy with the Maveren sibling sites. Local development is the current focus; deployment target is audited separately when we're ready to push. The shared-hosting constraints on stack choice still apply: no root, no long-running processes, MySQL only, cron is the only scheduler. These drive Sections 3, 4, 16, 17, 19.
- **A6.** This is a portfolio/demonstration project. It is built to production quality but the risk framing in Section 22 is portfolio-hygiene, not launch-blocking legal.
- **A7.** English-only. The 134-language i18n from Maveren is dropped (privacy leak per Maveren H-10).
- **A8.** USD-denominated wallet remains the internal unit of account.

If any of A1 through A8 is wrong, say which — several later sections change.

---

## 2. What carries over and what does not

**Carries over (rebuilt, not copied):**

- The domain model: users, wallets, transactions (as an append-only ledger this time), investments, plans, KYC submissions, deposit addresses, announcements, contact messages, admin roles.
- The NOWPayments integration for crypto deposit invoices and IPN webhooks.
- The manual-transfer-to-published-address deposit flow with admin approval.
- Withdrawal request flow with admin approval and KYC gate.
- Cron-driven ROI payouts and maturity releases.
- Email-based OTP verification and password reset.
- Admin approval workflows for deposits, withdrawals, KYC.

**Explicitly dropped:**

- All procedural PHP (`api/**/*.php` fat controllers).
- jQuery, Bootstrap, hand-written `mvc-*.css`.
- MyMemory / DeepL / Google translation of member pages (privacy leak).
- Smartsupp live chat until reintroduced deliberately.
- Every `executeQuery()` helper that swallows exceptions.
- The `settings` table wobble between legacy and current schemas.
- Direct file-based sessions on a shared cPanel disk.
- Deploy-time re-application of a live deposit-address SQL file.

**New in Gotham that was not in Maveren:**

- Trading terminal (candlestick chart + order book + trade tape).
- Trading agent, plays engine, per-plan agent configuration.
- Simulated buy/sell orders with position tracking and P/L.
- MFA for admins (mandatory) and members (optional).
- Immutable append-only ledger with cryptographic hash chaining per user.
- Admin audit log.
- Real observability stack.

---

## 3. Target stack

**R2:** Rewritten for shared-hosting constraints (no root, no background processes, MySQL only, single PHP runtime per request, cron as the only scheduler).

**Backend**
- **Laravel 12** (PHP 8.3+, whatever the shared host provides — most cPanel hosts now offer 8.3). Framework replaces the 232-file procedural sprawl.
- **MySQL 8.0.16+** or **MariaDB 10.6+**. Real `CHECK` constraints exist here since MySQL 8.0.16, so the non-negative-balance invariant (Maveren C-1) is enforceable at the storage layer. All money is `BIGINT` minor units — this sidesteps DECIMAL rounding regardless of engine.
- **File cache driver** for cache (`storage/framework/cache`). Sufficient for the volume; adds no infra dependency.
- **Database session driver**. Rows in a `sessions` table, cleaned by Laravel's built-in GC. Path-based file sessions on shared disk are the Maveren surface we avoid.
- **Database queue driver**, drained by a cron job every minute: `* * * * * php artisan queue:work --stop-when-empty --max-time=55 --tries=3`. This is the shared-hosting substitute for Horizon. Failed jobs table is real; a job monitor page in the admin panel shows what's stuck.
- **Symfony Mailer** (Laravel default), SMTP via cPanel or the same SpaceMail account Maveren uses. All email dispatched as queued jobs.
- **Laravel Sanctum** for any small first-party API tokens (e.g. admin health endpoints).
- **Spatie packages**: `laravel-permission` (roles), `laravel-activitylog` (audit log), `laravel-medialibrary` (KYC uploads, stored under `storage/app/private/kyc` and served through a signed-URL controller — no S3 needed).
- **`spatie/laravel-backup`** scheduled via Laravel's task scheduler (also driven by the minutely cron), writing weekly dumps to a Google Drive or Dropbox target via `league/flysystem` adapters. cPanel backups are the belt; this is the braces.

**Frontend**
- **React 19 + TypeScript** via **Inertia 2** (Laravel-driven routing, no separate API surface for the app).
- **Tailwind CSS 4** with a custom Gotham design system layer.
- **shadcn/ui** as the component base, restyled with Gotham tokens.
- **TradingView Lightweight Charts v5** for the terminal (45KB gzipped, MIT with attribution requirement).
- **Recharts** for admin analytics; **TanStack Table** for admin grids.
- **Framer Motion** for restrained motion.
- **Zustand** for local terminal state (selected symbol, timeframe).
- **Vite** for the frontend build; production build includes JavaScript obfuscation and content-hashed asset filenames.
- **All assets pre-built locally and rsynced.** The shared host is not expected to run Node.

**Infra and tooling**
- **Local dev**: Docker Compose (php-fpm, mysql, mailpit). Dev is containerised even though prod is not.
- **CI**: GitHub Actions runs tests and builds the production asset bundle. On green, a deploy job rsyncs to the shared host over SSH (or triggers a cPanel git deploy hook).
- **Cloudflare** in front of the app — DNS-level, works fine with shared hosting — for WAF, Turnstile, bot mitigation and edge caching of public marketing pages.
- **Sentry** for backend + frontend errors (external SaaS, no infra needed on host).
- **Uptime**: Better Stack or UptimeRobot pinging the app and a `/healthz` endpoint.

**Realtime**
- **HTTP polling by default.** The trading terminal polls `/api/ticks?symbol=X&since=Y` every 2 seconds; the browser knows only its last received tick timestamp. This is boring and it works on shared hosting. A subtle "delayed by 2s" indicator is honest and matches how many discount retail brokers actually look.
- If we later want push updates: **Pusher** (external, paid) or **Ably** slot in with one config change. Reverb is off the table on shared hosting.

**Rejected alternatives and why**
- Livewire: React is a hard requirement.
- Next.js standalone: doubles routing and auth surface.
- PostgreSQL: not offered on typical shared cPanel; MySQL is the reality.
- Redis, Reverb, Horizon: require a persistent process the host will not run.
- Docker in prod: no root, no daemon.

**Decisions needed on Section 3:**
- Which shared host — Spaceship (the Maveren host), Namecheap, HostGator, other? Affects whether SSH is available (needed for deploys) and PHP version.
- Sentry free tier is fine for this scale — confirm.

---

## 4. High-level architecture

**R2:** Simplified for shared hosting. Everything the app needs runs in the same PHP process or on the shared cron.

```
┌──────────────────────────────────────────────────────────────────┐
│  Cloudflare (WAF, Turnstile, edge cache for marketing routes)    │
└────────────┬─────────────────────────────────────────────────────┘
             │
             ▼
   ┌───────────────────────────────────────────────────────────────┐
   │  cPanel / LiteSpeed on shared host                            │
   │  ├── .htaccess (locked-down routing, deny .env / storage)     │
   │  ├── public/ = docroot (Laravel public/)                      │
   │  └── PHP 8.3                                                  │
   └────┬──────────────────────────────────────────────────────────┘
        │
        ▼
  ┌────────────────────────────────────────────────────┐
  │  Laravel 12 (request lifecycle)                    │
  │  ├── Inertia SSR (optional; can start SPA-only)    │
  │  ├── Domain services (Wallet, Investment,          │
  │  │    Trading, Agent, Compliance, Notification)    │
  │  ├── Dispatches queued jobs (DB-backed)            │
  │  └── Reads/writes MySQL, file cache                │
  └────┬────────────────────────┬──────────────────────┘
       │                        │
       ▼                        ▼
  ┌──────────────┐        ┌──────────────────────────┐
  │ MySQL 8      │        │ storage/                  │
  │ (single DB)  │        │  ├── framework/cache      │
  │              │        │  ├── framework/sessions   │
  │              │        │  ├── app/private/kyc      │
  │              │        │  └── logs                 │
  └──────────────┘        └──────────────────────────┘

  ┌────────────────────────────────────────────────────┐
  │  cron (every minute, one entry point):             │
  │   php artisan schedule:run                         │
  │  Laravel scheduler drives:                         │
  │   ├── queue:work --stop-when-empty --max-time=55   │
  │   ├── payouts:process-due                          │
  │   ├── agent:run-tick                               │
  │   ├── ticks:generate (Gotham Charts price walk)    │
  │   ├── reconcile:wallets                            │
  │   └── backup:run (weekly)                          │
  └────────────────────────────────────────────────────┘
```

Key differences from Maveren:
- KYC uploads leave the web root but stay on the same disk under `storage/app/private/kyc`, served through a signed-URL streamer controller.
- Wallet-touching operations either run in a DB transaction inside the request, or dispatch a queued job with an idempotency key; nothing important is left "eventually consistent" via file writes.
- Sessions live in the `sessions` DB table, not on the filesystem.
- One cron entry point drives everything; Laravel's scheduler decides what runs when. No orphaned cron lines to hand-manage.

**Hosting note (R4).** Waynebroker deploys to a shared cPanel account on a **different physical host** from Maveren, so the Maveren audit H-5 cotenancy risk does not carry over. The R3 mitigations (dedicated DB and DB user) still apply as standard hygiene.

---

## 5. Bounded contexts

Modular monolith with these bounded contexts. Each context owns its models, services, actions, events, listeners and policies. Cross-context calls go through documented service interfaces.

| Context | Responsibility | Notable models |
|---|---|---|
| **Identity** | Registration, login, MFA, sessions, admin provisioning, roles, permissions | `User`, `Admin`, `Role`, `MfaFactor`, `Session`, `LoginAttempt` |
| **Wallet** | Balances, ledger entries, deposit/withdrawal orchestration | `Wallet`, `LedgerEntry`, `Transaction`, `DepositAddress` |
| **Payments** | NOWPayments client, IPN handling, provider abstraction | `PaymentProvider`, `PaymentInvoice`, `IpnEvent` |
| **Investment** | Plans, positions, ROI schedule, maturity | `Plan`, `Investment`, `PayoutSchedule` |
| **Trading** | Markets, symbols, order execution surface, positions, order tape, price feed adapters | `Market`, `Symbol`, `Order`, `Position`, `PriceTick` |
| **Agent** | Plays, per-plan agent configs, play scheduling, outcome resolution | `AgentConfig`, `Play`, `PlayExecution`, `AgentSession` |
| **Compliance** | KYC submissions, document storage, admin audit log, sanctions check hooks | `KycSubmission`, `KycDocument`, `AdminAuditEvent` |
| **Notification** | Emails, in-app alerts, future push | `Notification`, `EmailTemplate` |
| **Content** | Announcements, marketing pages, contact form | `Announcement`, `ContactMessage` |
| **Ops** | Backups, health checks, feature flags | `FeatureFlag`, `HealthCheckResult` |

Each context lives under `app/Domains/<Context>` with its own `Models`, `Services`, `Actions`, `Events`, `Listeners`, `Policies`, `Http/Controllers`, `Http/Requests`. Shared kernel goes under `app/Support`.

---

## 6. Data model rebuild

Core principles that were violated in Maveren and are enforced here:

1. **Money is stored as integer minor units.** `bigint` cents (or satoshis for crypto). No floats anywhere in the code path. Money conversion happens only at display.
2. **The wallet balance is derived from the ledger.** `wallets.balance` is a materialized cache updated inside the same transaction as the ledger insert. A background invariant check reconciles `wallet.balance == SUM(entries) - SUM(reservations)` on a schedule and pages ops on drift.
3. **Ledger is append-only.** `ledger_entries` has no `UPDATE` grant; corrections are recorded as new offsetting entries with an audit reason. Every entry carries a `previous_hash` and a `hash` of its own row, chained per wallet, so tampering is detectable.
4. **Concurrent writes go through row locks or optimistic CAS.** No read-modify-write across transaction boundaries anywhere.
5. **KYC PII columns are encrypted at rest** (`encrypted:` cast on `id_number`, `dob`, `full_name`).
6. **No hard deletes on financial data.** Users can be `disabled` and `anonymised`; their ledger stays.

### Table sketch (abbreviated — full DDL comes in the coding phase)

`users` — `id`, `email` (citext unique), `email_verified_at`, `phone`, `status` enum, `disabled_at`, `anonymised_at`, timestamps.

`user_profiles` — split from `users` so PII sits in one table with row-level access logging.

`admins` — separate table; own MFA factors; own audit log.

`roles`, `permissions`, `role_permissions`, `user_roles`, `admin_roles` — spatie/laravel-permission with strict role naming: `super_admin`, `finance_admin`, `support_admin`, `content_admin`, `member`.

`wallets` — `id`, `user_id` UNIQUE, `balance_cents` bigint, `reserved_cents` bigint, `hash_head` (chain head), timestamps. Trigger prevents `balance_cents < 0`.

`ledger_entries` — `id`, `wallet_id`, `direction` enum(credit,debit), `amount_cents`, `kind` (deposit, withdrawal, roi_payout, principal_release, adjustment, agent_pnl, bonus, correction), `reference_type`, `reference_id`, `metadata` jsonb, `previous_hash`, `hash`, `created_at`. No `updated_at`. Only `INSERT` grant to the app role.

`transactions` — the user-facing view; wraps ledger entries plus provider-facing state (pending, completed, failed).

`deposit_invoices` — one row per NOWPayments invoice, unique `provider_payment_id`, status machine.

`ipn_events` — every incoming IPN, uniqueness by `provider_payment_id + signature`, idempotency key for the handler.

`plans`, `investments`, `payout_schedules`, `payouts` — first-class payout schedule table so cron reads a due-payouts query rather than recomputing cadence three ways.

`markets`, `symbols`, `price_ticks` — `price_ticks` uses TimescaleDB hypertable if we add the extension, otherwise a monthly partitioned table. Ticks retained 30 days, aggregated into `price_candles` (1m/5m/15m/1h/1d) with longer retention.

`orders`, `positions` — simulated. `orders` records intent, `positions` is the live book.

`plays`, `agent_configs`, `play_executions`, `agent_sessions` — see Section 8.

`kyc_submissions`, `kyc_documents` — documents stored in object storage; only `object_key` in DB.

`admin_audit_events` — every admin write, actor, before/after, IP, session id.

`sessions` — Redis-backed via Laravel session driver; DB fallback table for auditing "sessions active" per user.

`login_attempts`, `mfa_factors`, `mfa_backup_codes`, `password_reset_tokens`, `email_verification_tokens` — hashed tokens, not plaintext OTPs.

`feature_flags` — DB-backed feature flags.

### Migration and seeding

- Migrations are numbered per bounded context (e.g. `2026_10_01_000000_wallet_create_wallets_table.php`).
- A single baseline migration is forbidden; every table is a real migration.
- Test factories for every model.
- Seed a demo member, demo admin, three plans (mirrors Maveren), three markets (BTC/USD, EUR/USD, XAU/USD), and a small library of plays for each plan tier.

**R2 note on Section 6:**
- DB engine is MySQL 8, not PostgreSQL. `CHECK` constraints work since 8.0.16, `JSON` columns cover our metadata needs (no `JSONB` indexing but we do not need it at this volume), and `NUMERIC` semantics are moot because money is `BIGINT` minor units.
- Tick data lives in a normal MySQL table partitioned monthly by `PARTITION BY RANGE (YEAR(created_at)*100 + MONTH(created_at))`. No TimescaleDB.
- The append-only ledger uses MySQL row-level constraints: a trigger prevents `UPDATE` and `DELETE` on `ledger_entries` outside a superuser role.

**Decisions needed on Section 6:**
- Crypto amounts in the ledger: satoshis, or normalise everything to USD-cents at credit time? Recommend USD-cents.
- MySQL vs MariaDB — whichever the host offers. Recommend MySQL 8 if the choice exists.

---

## 7. Trading terminal

The core new feature. Modelled after Exness webtrader, tuned to fit Gotham identity.

### 7.1 Layout

```
┌─────────────────────────────────────────────────────────────────┐
│ Top bar: symbol picker | timeframe | balance | agent status     │
├──────────┬───────────────────────────────────────┬──────────────┤
│          │                                       │              │
│ Market   │                                       │  Order       │
│ watch    │      Candlestick chart                │  ticket      │
│ (list)   │      (lightweight-charts)             │  Buy/Sell    │
│          │                                       │  Lot size    │
│          │                                       │  SL/TP       │
│          │                                       │              │
├──────────┼───────────────────────────────────────┴──────────────┤
│          │  Tabs: Open positions | Pending | History | Alerts  │
│          │  Data grid (TanStack Table)                          │
└──────────┴──────────────────────────────────────────────────────┘
```

Responsive rules: on tablet, market watch collapses to a symbol dropdown; order ticket becomes a bottom sheet on mobile; chart takes full width.

### 7.2 Chart

- Library: TradingView Lightweight Charts v5, React binding via the official examples.
- Series: candlesticks primary, volume histogram in a lower pane, moving averages as overlays.
- Timeframes: 1m, 5m, 15m, 1h, 4h, 1d.
- Interaction: crosshair, zoom, pan, "go to last" button, position markers rendered as chart overlays where the user's simulated entries live.
- Theme: fully replaced palette using our tokens; no default TradingView blue.
- Attribution: the "chart by TradingView" attribution appears in the chart's footer per license.

### 7.3 Instruments and price feed

**R2:** Instruments are Gotham-owned. No AUD/CAD, no BTC/USDT. The terminal lists Gotham Charts symbols with codes like `GTM-A1`, `GTM-A2`, `GTM-B1`, `WYN-U` (Wayne Utility Index), etc. This is not a broker; it is a Gotham product. Ten instruments at launch, grouped as:

- **Gotham Alpha (GTM-A*)** — synthetic pairs, higher volatility, "trend-heavy" character.
- **Gotham Beta (GTM-B*)** — lower volatility, "range-bound" character.
- **Wayne Indices (WYN-*)** — themed indices (Utility, Industrial, Media), slow trending.

Each instrument has: name, code, category, tick size, min lot, max lot, margin bps, session hours (24/5 for most; 24/7 for Alpha), base volatility.

The price feed is **fully server-generated**:

- A cron-driven `ticks:generate` command runs every minute.
- For each active instrument, it advances a deterministic seeded random walk (Ornstein-Uhlenbeck for the Beta group, geometric Brownian for Alpha, drift-dominant for indices) and writes new ticks to `price_ticks` and rolls candles into `price_candles` at 1m/5m/15m/1h/1d.
- Seeds are stored per instrument so any environment can reproduce the walk for debugging and admin backtesting.

Delivery to the browser is HTTP polling:

- `GET /api/ticks?symbol=GTM-A1&since={lastTs}` returns any new ticks since `lastTs`.
- Terminal polls every 2s while visible; pauses when the tab is hidden (Page Visibility API).
- Historical candles served by `GET /api/candles?symbol=GTM-A1&tf=1h&from=...&to=...`.
- A subtle "live · 2s" indicator in the chart footer.

This is more honest than a fake WebSocket and cheaper to run.

### 7.4 Order lifecycle (simulated)

1. Client submits an order: `{symbol_id, side, volume, order_type, sl?, tp?}`.
2. `PlaceOrderAction` validates plan permissions, wallet margin, and market open state.
3. Order is written to `orders`, position opened, wallet's `reserved_cents` incremented by margin.
4. Agent engine is asked to attach a play to this order if one is scheduled (Section 8).
5. Position updates on every tick; unrealised P/L broadcast.
6. On close (manual, SL hit, TP hit, or agent-scripted): position closed, realised P/L posted to the ledger as `kind=agent_pnl`.

### 7.5 Client stack

- Zustand store for terminal state.
- Web Worker for the price stream to keep the main thread free.
- Chart series updates use `series.update()` for live ticks (not full re-set).
- Draw tools (trendline, horizontal, Fibonacci) using Lightweight Charts primitives.

**Decisions needed on Section 7:**
- Ten instrument codes and display names at launch. Recommend I draft a table with GTM-A1..A4, GTM-B1..B3, WYN-U, WYN-I, WYN-M and you rename to taste.
- Positions persist across sessions (recommend yes).
- Lot size and margin per instrument — I can propose defaults; you review.

---

## 8. Trading agent and plays engine

This section makes the intentional behaviour explicit so we build it right and defend it in Section 22.

### 8.1 Concept

**R2:** Because instruments are Gotham-owned (Gotham Charts, Section 7.3), outcome shaping happens inside Gotham's own market and is part of the product, not a fake overlay on someone else's data.

Each plan tier includes an "agent" that periodically executes trades on the member's behalf across Gotham Charts. The agent's trades appear in the terminal like any other, but the outcome distribution is set by the plan tier. Higher tiers show:

- Higher win rate
- Larger average profit per trade
- More trades per week
- Narrative variety (breakout, mean-reversion, index rotation)

Members may also place their own manual trades. Manual trade outcomes are decided by the same plays engine, drawn from a "manual" play pool per tier that pays out at a lower rate than the agent — so the agent stays the reason to be in a plan, but manual trading is not pointless.

Trading is optional; the primary money return remains the fixed-return plans (Section 9). The terminal's role is engagement and demonstration.

### 8.2 Domain objects

- **`AgentConfig`** — per plan tier: `trades_per_week`, `avg_profit_bps`, `win_rate`, `max_open_positions`, `allowed_symbols`, `narrative_pool_id`.
- **`Play`** — a template: `symbol`, `direction`, `entry_offset_bps`, `target_bps`, `stop_bps`, `hold_seconds`, `narrative_key`, `outcome` (win, loss, breakeven), `weight`.
- **`AgentSession`** — a member's live agent state: `user_id`, `plan_id`, `next_execution_at`, `positions_open`, `week_stats`.
- **`PlayExecution`** — records each play run: which play, entry price, exit price, resolved outcome, ledger entry id.

### 8.3 Scheduling

- A recurring queued job `RunAgentTick` runs every N minutes.
- For each active `AgentSession` past its `next_execution_at`:
  - Pull the plan's `AgentConfig`.
  - Draw a `Play` weighted by the config's outcome distribution.
  - Enqueue `ExecutePlayJob` that opens the simulated order, waits `hold_seconds`, closes it at the target/stop.
  - Set `next_execution_at` from a jittered Poisson interval matching `trades_per_week`.
- All P/L posts to the ledger as normal wallet entries.

### 8.4 Guardrails

- Weekly caps per plan tier so the outcome distribution stays defensible: max positive weekly return X%, max negative Y%.
- Circuit breaker: if a member's session sees more than N losses in a row (or a week has gone worse than the tier's floor), the agent pauses and a support ticket is auto-opened.
- All plays are seeded and reproducible for admin review.

### 8.5 Admin surface

- Play editor: create/edit plays, upload play packs per tier.
- Live view of any member's `AgentSession` and next scheduled play.
- Pause/resume agent per user.
- Backtest simulator that runs a plan tier's config against a month of synthetic data.

**Decisions needed on Section 8:**
- The name shown to members for this feature. Options: "Wayne Agent", "Guardian Agent", "Autopilot", "Agent-assisted trading". Recommend the plain "Trading Agent".
- Should members be allowed to disable the agent? Recommend yes, with a persistent banner explaining they'll miss out on plan benefits.
- Legal disclaimer copy — see Section 22.

---

## 9. Investment product model

Fixed-plan investments stay as the flagship product. Trading is positioned as a plan perk. Everything the Maveren cron and invest flow did is rebuilt on top of the new ledger.

- **Plan** — `name`, `tier`, `roi_bps_per_period`, `period_days`, `duration_periods`, `min_principal_cents`, `max_principal_cents`, `agent_config_id`, `status`.
- **Investment** — `user_id`, `plan_id`, `principal_cents`, `started_at`, `matures_at`, `payout_schedule_id`, `status` (active, paused, completed, cancelled).
- **PayoutSchedule** and **Payout** — precomputed at start; cron reads `payouts` where `due_at <= now() AND status='pending'` and processes in transactional batches with row locks. Idempotent by unique `(investment_id, sequence)`.
- **Principal release** happens as a `payout` with `kind=principal_release` on the last schedule row.

The Maveren edge case where a member `unlock_investment` before the cron skipped the final ROI (audit M-2) is closed because the "final ROI" is a real row in `payouts` with its own `due_at`, and unlocking processes it if due.

The `investment_bonus`, `investment_term`, `investment_close` admin actions are reintroduced with mandatory `AdminAuditEvent` records and a reason field.

**Plan tiers proposed:**

| Tier | ROI/period | Period | Duration | Min | Agent trades/wk | Agent win rate |
|---|---|---|---|---|---|---|
| Starter | 1.10% | week | 13 | $100 | 3 | 62% |
| Growth | 1.65% | week | 26 | $500 | 6 | 68% |
| Institutional | 6.00% | month | 12 | $2,000 | 12 | 74% |

These mirror Maveren's rates. Confirm or override.

---

## 10. User dashboard (Exness-inspired, Batman-themed)

Structure by tab, kept close to Exness webtrader for user familiarity, layered on the Batman token system in Section 15.

- **Overview** — balance card, current investments summary, agent status, recent activity, market ticker strip.
- **Trade** — the terminal (Section 7).
- **Invest** — plan cards, active positions, matured positions, ROI schedule visualisation.
- **Wallet** — deposit (crypto invoice, published address), withdraw (crypto, bank), transaction history filterable by kind and date, CSV export of the whole result set (not just the current page — Maveren bug).
- **Verification** — KYC status, document upload with camera capture option, re-submit path.
- **Profile** — details, MFA setup, session list with per-session revoke, notification preferences.
- **Announcements** — read-only member-facing announcements.
- **Support** — contact form; an in-app admin inbox is built so admins actually see submissions (Maveren M-13 fix).

Cross-cutting UX rules:
- Every destructive action confirms with a themed modal, never `window.confirm`.
- All money displayed with locale-aware formatting through a single `<Money>` component, never string-concatenated.
- Loading is via skeletons for surfaces, spinners only for buttons.
- Empty states offer the next action, never just a "no data" line.
- Errors show what went wrong and what to try, in the interface's voice.

---

## 11. Admin dashboard

Modernised with the same Gotham tokens but tuned for density.

- **Home** — live metrics: active users, open positions, agent P/L today, pending deposits, pending withdrawals, KYC queue, IPN failures last hour. All WebSocket-updated.
- **Users** — searchable, filterable, role editing, disable/anonymise (no hard delete), impersonate with audit trail, per-user session list.
- **Wallets** — read balance, view ledger, post an audited adjustment entry (never absolute overwrite — Maveren H-2 fix).
- **Transactions** — full ledger with faceted filters, export the full filtered set to CSV.
- **Deposits** — pending, completed, failed. Approve/decline with mandatory reason field. Sees payout destination.
- **Withdrawals** — same, plus a field to record the outgoing bank/crypto reference on completion.
- **Investments** — search, edit, bonus, term-change, close, all audited.
- **Plans** — CRUD with versioning; existing investments do not shift when a plan changes.
- **Agent** — plays library, per-tier config, per-user session inspector, backtest.
- **KYC** — queue with visible thumbnails via signed URLs, per-submission audit trail, mandatory reason on reject. Correct role gate (super/finance/support/content mapping — Maveren H-8 fix).
- **Deposit addresses** — CRUD, no deploy-time overwrites.
- **Announcements** — CRUD, categories, publish state.
- **Contact inbox** — every contact form submission (Maveren M-13 fix).
- **Broadcasts** — email broadcast via queue, per-batch progress meter, cancellation.
- **Audit log** — searchable admin action feed.
- **Settings** — every setting through a form validated on both ends; one source of truth.

Admin auth: mandatory TOTP MFA; no self-service registration; provisioning by super admin only; new admins default to `support_admin` (Maveren H-9 fix). Session elevation required for sensitive operations.

---

## 12. Authentication and authorization

- **Session store**: Redis. `SESSION_LIFETIME` honoured. Rolling refresh, absolute cap 30 days.
- **Cookies**: `HttpOnly`, `Secure`, `SameSite=Lax` for member (needed for OAuth returns later), `SameSite=Strict` for admin.
- **Password policy**: minimum 10, checked against a bloom filter of the top 100k breached passwords bundled with the app; Argon2id.
- **Session regeneration** on every privilege change, including admin registration (Maveren M-5 fix) and MFA success.
- **Session revocation** on password change or reset (Maveren fix): a per-user `session_version` claim in the session; server checks on every request.
- **MFA**: TOTP for members (optional but rewarded with reduced friction on high-value actions) and admins (mandatory). Backup codes single-use.
- **Email verification**: token, not OTP; single-use; 24h expiry.
- **Password reset**: token in a link, single-use, 30min expiry, hashed at rest. Reset invalidates all sessions.
- **Rate limits**: per-IP, per-user, per-email, per-fingerprint. All buckets recorded, not just IP (Maveren M-4 fix).
- **CSRF**: Laravel default plus a hard rule that state-changing actions are never accepted over GET (Maveren H-7 fix). Endpoint tests assert method restrictions.
- **Impersonation**: admin can impersonate a user with a banner visible in the member UI, ends on logout, every impersonated action stamped with the acting admin in the audit log.
- **Role/permission model**: spatie/laravel-permission, roles listed in Section 5, permissions granular. Policies live in each domain module.

---

## 13. Security overhaul (retiring Maveren audit findings)

Every ID from the Maveren audit maps to a Gotham design decision.

| Maveren finding | Gotham resolution |
|---|---|
| C-1 (withdrawal race) | Ledger-based debit inside a transaction with `SELECT FOR UPDATE`, plus a `CHECK (balance_cents >= 0)` constraint and idempotency key on the withdrawal request |
| C-2 (unauthenticated invoice creation) | The NOWPayments client lives in a service class; there is no HTTP endpoint that calls it directly. Invoice creation is only reachable from an authenticated action; no debug path |
| C-3 (IPN idempotency) | `ipn_events` uniqueness on provider payment id + signature; handler CAS on `deposit_invoices.status='pending'`; ledger credit only fires when the update matched one row |
| H-1 (no admin audit) | `AdminAuditEvent` recorded automatically via a middleware on every admin write; UI in the admin panel |
| H-2 (absolute balance override) | Removed. Adjustments only via a signed ledger entry with a reason |
| H-3 (hard user deletion) | Disable + anonymise flow; ledger retained; KYC files retained under retention policy or purged with an audited job |
| H-4 (executeQuery swallowing) | No helper hides exceptions; all queries go through Eloquent or DB facade with framework exception handling |
| H-5 (shared DB user across sites) | Dedicated DB, dedicated DB user, dedicated Redis, no co-tenancy |
| H-6 (deploy re-applies deposit addresses) | Deposit addresses live only in the DB and are edited through the admin UI. No deploy-time SQL for them |
| H-7 (GET-accepted state changes) | Every state-changing action requires POST/PATCH/DELETE. Enforced by tests |
| H-8 (KYC role mismatch) | Role names come from constants; a single test asserts every role gate references a real role |
| H-9 (invite-code admin = super_admin) | No invite code. Admins are provisioned by other super_admins from the admin UI; new admins default to `support_admin` |
| H-10 (translation privacy leak) | i18n removed at launch |
| H-11 (stored HTML injection into admin) | Every admin data grid renders through React with no `dangerouslySetInnerHTML`; server data is JSON, not HTML |
| M-1 (cron overlap and unlocked credits) | Payouts are queued jobs with a unique idempotency key per `(investment_id, sequence)`, row lock the investment before crediting |
| M-3 (wallets not unique) | `UNIQUE(user_id)` on `wallets` and one canonical wallet creation path |
| M-6 (email change requires nothing) | Email change requires current password and a fresh verification of the new address before it becomes primary |
| M-7 (email inside DB transaction) | Emails are queued jobs, dispatched after commit |
| M-11 (`.env.dbpass` shipped) | Docker image and deploy pipeline exclude secrets by construction |
| M-12 (no backup before migration) | `spatie/laravel-backup` scheduled every 6h off-site, and a snapshot before every deploy |
| M-13 (payout destination hidden in admin) | Withdrawals show destination in the admin queue |
| M-17 (webhook not asserting type) | IPN handler asserts the referenced deposit invoice is in the correct state and kind |
| M-19 (float money) | Integer minor units throughout |

Additional hardening not tied to a specific Maveren finding:
- CSP with `script-src 'self'` and per-build asset hashes; no `'unsafe-inline'`.
- SRI on any external asset.
- HSTS, `Referrer-Policy: same-origin`, `Permissions-Policy` blocking everything not needed.
- Signed URLs for all uploaded media served from the object store.
- Every write endpoint has integration tests that assert method restrictions, auth requirement, role requirement, and rate limit.

---

## 14. Anti-cloning layer

No single measure stops a determined cloner. Layered defence raises the cost past the point where casual clones happen.

**Render layer**
- Inertia SSR on member and admin surfaces. View-Source shows a shell and a data blob, not the compiled UI.
- No public sitemap for member/admin surfaces. `X-Robots-Tag: noindex, nofollow` on those paths.

**Build layer**
- Vite production build with `javascript-obfuscator` at moderate settings: identifier renaming, string encryption, dead code injection. Not maximum settings (they wreck performance).
- Per-release rotated asset filenames; old bundles 404 after a grace period.
- CSS class names hashed in production so scraped HTML does not match a public class inventory.
- Fonts served from origin with CORS locked to the app's domain.

**Runtime layer**
- Client fingerprint (canvas, WebGL, timezone, screen) hashed and sent as a header on every request; anomalous fingerprints get a Turnstile challenge from Cloudflare.
- WebSocket connections require a short-lived signed token issued on page load; token bound to session and fingerprint.
- API endpoints reject requests that do not present the fingerprint and CSRF header pair.
- Static image assets (chart glyphs, brand marks) served with a signed URL query param that expires.
- Right-click and text selection disabled on member data surfaces (not on marketing pages, they're SEO territory).
- Devtools open detection: a soft signal, logged server-side; repeated detection on a session flags it for review, does not block.

**Watermarking**
- Every rendered page contains a hidden per-session watermark encoded as invisible unicode inside data attributes and in the DOM structure order. A cloned copy phones home its watermark; we can identify the origin session and act.
- Uploaded documents that are downloaded from the admin panel are watermarked with the admin's id and download timestamp.

**Bot mitigation**
- Cloudflare Turnstile on register, login, password reset, contact.
- Bot Fight Mode on.
- Aggressive rate limits on the price feed endpoints. WebSocket ticks are the free path; HTTP polling of `/api/ticks` is throttled.

**Legal/organisational**
- `LICENSE` file (proprietary), `TERMS` in the footer, DMCA process documented.
- Every clone we identify gets a takedown to the hosting provider.

**What we deliberately do not do**
- Encrypted response bodies decoded client-side. That is theatre; the JS to decode is right there.
- Disabling F12 and printing. That is theatre and hostile UX.

**Decisions needed on Section 14:**
- Cloudflare Pro tier ($20/mo) unlocks Bot Fight Mode Advanced. Approve.
- Devtools detection soft-block or observability only? Recommend observability.

---

## 15. Batman design system

The important call: do not build the acid-yellow-on-pure-black cluster that reads like a generic dark SaaS. Wayne Enterprises the financial holding company is the reference, not the cape.

### 15.1 Palette

Tokens named by role, not colour. Hex tuned for OLED and desktop LCD.

| Token | Value | Use |
|---|---|---|
| `--surface-void` | `#0B1220` | App background. Midnight blue-black, subtle temperature |
| `--surface-carbon` | `#131A26` | Cards, chart pane |
| `--surface-elevated` | `#1B2331` | Modals, tooltips, dropdowns |
| `--surface-line` | `#25304256` | Hairlines and grid rules; alpha-tuned |
| `--ink-primary` | `#E9E3D2` | Warm ivory, not sterile white |
| `--ink-secondary` | `#A6A99B` | Secondary text |
| `--ink-muted` | `#6B6F62` | Captions, disabled |
| `--accent-brass` | `#C89E4C` | Primary CTAs, key numbers, brand marks |
| `--accent-brass-hot` | `#E4B970` | Hover, focus ring |
| `--signal-cyan` | `#4E9CB7` | Information, informational badges |
| `--signal-loss` | `#B84B5C` | Real losses, destructive actions |
| `--signal-gain` | `#4A9F7E` | Realised gains, confirmations |

`--accent-brass` is the money colour. It is warm and confident. It is not the meme "bat-yellow". Reserve `--signal-gain` for actual money-in-the-account events; do not use green for "success" toasts generically.

Dark by default and only. No light mode in v1. Financial terminals are dark; forcing a light mode adds work for no reward.

### 15.2 Typography

Two families:
- **Display / body**: `Neue Haas Grotesk Display` (or the open-source `Söhne`-like `Sohne Buch` alternative — depends on licensing budget; free fallback: `Inter Display`).
- **Numeric / monospace**: `JetBrains Mono` for all numbers, table cells, order tickets, and code. Tabular figures on.

No secondary decorative face. No serif display. That said, one reserved treatment: hero pages may use a heavier weight of the display face at very large size, with tight tracking, as the single distinctive gesture. No "one-word-in-italic" or per-letter accent tricks.

Type scale: 12, 14, 16, 18, 21, 28, 36, 48, 64. Line-heights follow: 16, 20, 24, 28, 30, 36, 44, 56, 72. Numbers use `font-variant-numeric: tabular-nums`.

### 15.3 Layout and structure

- Hairline dividers, `--surface-line`, are the primary structural device.
- Corner radius: 6px on cards, 4px on inputs, 2px on data cells, 999px on chips. No mixed radii per surface.
- Density is calm. Sixteen-column grid on desktop, no cramped card fields. Data grids are dense; content pages are airy.
- No glassmorphism, no floating cards, no drop shadows on primary chrome. A single elevation shadow reserved for modals: `0 24px 48px -12px rgba(0,0,0,0.6)`.

### 15.4 Motion

- One entrance sequence per page: the hero panel fades and rises 8px on load. Nothing else animates on scroll by default.
- Interaction motion — buttons, dropdowns, ticket confirmations, price flashes — is welcome, kept under 180ms with `cubic-bezier(0.2, 0.8, 0.2, 1)`.
- Price ticks pulse a 1px inner ring in gain/loss colour for 120ms. This is the terminal's signature moment; nothing else uses it.
- `prefers-reduced-motion` disables entrance sequences and price pulses; static states shown instead.

### 15.5 Iconography

- Phosphor icons in `Regular` weight at 20px default. No mixed icon families.
- Brand mark: a wordmark set in the display face, letter-spaced. A single glyph mark reserved for favicon, avatar chip, and the terminal's chart watermark. The glyph is an original geometric mark; no bat silhouette.

### 15.6 Imagery

- Photography: dusk/night skyline, brass and glass interiors, no cape imagery. Duotoned to `--surface-void` and `--accent-brass`.
- Illustrations for empty states: geometric line art in `--ink-muted`. Not cartoon.

### 15.7 Written voice

- Short. Institutional but not stiff. Present tense. Active voice.
- No hype language. No exclamation points outside toast confirmations, and even then rarely.
- Copy sample: "Position opened. $250 at 1.0842." Not "Success! Your position has been opened."

### 15.8 Component library

We start from shadcn/ui and immediately override the token layer to Gotham. Every component in Storybook (or Ladle) with a "Gotham" variant and its own visual test.

Components explicitly designed for the terminal:
- `PriceCell` (tabular, flashing on tick)
- `Money` (integer minor units to formatted string)
- `SymbolChip` (icon + code + direction badge)
- `OrderTicket` (buy/sell, lot, SL/TP, submit)
- `PositionRow` (symbol, entry, current, P/L, close)
- `AgentStatus` (state, next play in, week summary)
- `LedgerRow` (kind, description, amount, running balance)

---

## 16. Realtime infrastructure

**R2:** HTTP polling all the way down. Shared hosting rules out a WebSocket server.

- **Price ticks**: `GET /api/ticks?symbol=X&since={cursor}` every 2s while the terminal is visible; paused when the browser tab is hidden.
- **Positions and P/L**: `GET /api/positions/live` every 3s while positions are open; unrealised P/L is derived client-side from the last tick, no server round-trip needed per re-render.
- **Agent status**: `GET /api/agent/status` every 15s.
- **User events** (order filled, KYC decision, deposit credited): a lightweight `GET /api/events?since={cursor}` every 10s. Server returns any new event ids for the session and clears them. Events also arrive as toast notifications when the response contains them.
- **Admin dashboard live counters**: `GET /api/admin/pulse` every 5s.
- **HTTP cache headers**: all live endpoints set `Cache-Control: no-store`; Cloudflare passes them through.
- **ETag / If-None-Match** on tick and event endpoints so unchanged responses return `304` cheaply.

Cost of this design: ~30 requests per minute per active member per open tab. Cloudflare absorbs most of that at the edge for public-cacheable subsets (like historical candles).

**Path to real-time push later**: swap the polling endpoints for a Pusher (external SaaS) channel. Client stays on `laravel-echo`; the switch is `.env`-flag deep. No architectural change needed.

---

## 17. Payments and reconciliation

**R2:** Portfolio scope. The hard custody question is moot; the code still models the flow end-to-end so the app demonstrates real financial-app plumbing.

- **Deposits**: NOWPayments crypto invoices (kept from Maveren, hardened per Section 13) and manual transfers to published addresses. Both flows work end-to-end so a demo can show a real deposit landing in the wallet. If we want a zero-money demo path, an admin can also credit a wallet via an audited adjustment entry (Section 13, H-2 fix).
- **Withdrawals**: request → admin approval → recorded outbound reference. External payout stays off-platform.
- **Custody note**: for the portfolio narrative, the README states plainly that this is a demonstration and any deposits are treated as demo balances unless the operator explicitly holds them. Nothing about the code prevents real operation; it just isn't the point of the project.
- **Reconciliation**: the invariant job runs — `wallets.balance == SUM(ledger_entries) - reserved` — because it is the single best-looking piece of correctness plumbing in a financial-app portfolio. Drift pages the admin dashboard, not a real on-call rotation.

**Decisions needed on Section 17:**
- Keep NOWPayments live in this build, or stub the payments provider entirely (an interface with a `FakeProvider` that mints invoices for demo)? Recommend keeping the real integration but shipping the fake provider too, gated by `.env`. Best of both worlds for a portfolio piece.

---

## 18. Notifications

- **Email** via queued jobs; templates in Blade + MJML for consistent rendering across clients.
- **In-app** via a `notifications` table and a Reverb channel; bell icon in the app chrome with unread count.
- **Push** deferred to v1.1.
- **Templates**: welcome, verify, reset, deposit-initiated, deposit-completed, withdrawal-requested, withdrawal-completed, withdrawal-cancelled, investment-started, roi-credited, principal-released, agent-position-opened (opt-in), agent-position-closed (opt-in), kyc-approved, kyc-rejected, security-alert (new device, password-change), broadcast.
- Every template escapes user data by default; the two intentionally-raw slots (`details_html`, `message_body`) are wrapped in a value object that carries pre-escaped HTML and refuses raw strings.

---

## 19. Observability, testing, CI/CD

**Testing**
- **Pest** for PHP: unit tests for services, feature tests for HTTP flows, browser tests via Pest 3 browser (or Playwright called from Pest).
- **Property tests** on ledger invariants: `SUM(entries) == balance` for any sequence of operations.
- **Race condition tests**: parallel withdrawal requests must debit exactly once; parallel IPN deliveries must credit exactly once.
- **Vitest + React Testing Library** for React components.
- **Playwright** for E2E: register → verify → deposit → invest → agent-trade → withdraw.

**Quality gates in CI**
- PHPStan level 8 (Larastan).
- Laravel Pint.
- Rector for automated modernisation on PRs.
- ESLint, Prettier, TypeScript strict.
- Playwright suite on every PR to `main`.
- Cypress or Playwright visual regression on Storybook.

**Observability**
- **Sentry** for backend + frontend errors.
- **Laravel Telescope** in staging only.
- **Horizon** dashboard behind admin auth for queue health.
- Structured JSON logs shipped to Better Stack or self-hosted Loki.
- Business metrics: active positions, agent P/L today, deposit success rate, IPN success rate, exposed as a `/metrics` endpoint scraped by a lightweight Prometheus.
- Uptime: external monitor pings the app and Reverb, pages on 3-strike failures.
- Reconciliation alerts (Section 17) go to a dedicated Slack/webhook.

**CI/CD (R2: shared-hosting flavour)**
- GitHub Actions.
- On PR: run PHP tests, JS tests, static analysis, lint.
- On merge to `main`: build the frontend (`npm ci && npm run build`), package the release (excludes `node_modules`, `.git`, `.env`, `tests/`), rsync over SSH to the shared host, then run remote artisan commands via SSH:
  - `php artisan down`
  - `php artisan migrate --force`
  - `php artisan config:cache && php artisan route:cache && php artisan view:cache`
  - `php artisan up`
- The Maveren deploy hazards (H-6 deposit-address SQL re-application, M-11 secret leakage) are avoided because the release package is built from a clean checkout with strict include/exclude rules — no local `.env`, no live-data SQL, no `.claude/`.
- Backups: `spatie/laravel-backup` scheduled weekly plus a manual snapshot triggered before every deploy through a `pre-deploy` artisan command.
- No blue/green — shared hosting does not host it. Instead, sub-second downtime is accepted; `php artisan down` shows a themed maintenance page.

---

## 20. MCP discovery and self-aware kickoff

Claude Code should not assume which MCP servers exist on the Linux machine. The kickoff prompt in Section 25 tells Claude to:

1. Run `claude mcp list` (or read the configured MCP config file) to enumerate available servers.
2. Report the discovered set back to us before starting work.
3. Prefer, in this order for design/frontend work:
   - A **shadcn/ui MCP** (component source, blocks, registry lookup).
   - A **Figma MCP** if a design file exists or is being drafted.
   - A **Chrome DevTools MCP** or **Playwright MCP** for visual verification during development.
   - A **Filesystem MCP** for repo edits.
   - A **PostgreSQL MCP** for local schema introspection during development.
   - A **Sentry MCP** for error triage once the app is deployed.
4. If a required MCP is missing, install it and record the install steps in the repo's `docs/mcp-setup.md`.
5. Never silently invent capabilities not backed by a discovered MCP.

The kickoff also instructs Claude Code to never blindly trust the Maveren PHP behaviour; it should read the Gotham plan and the audit before proposing code changes.

---

## 21. Migration strategy from Maveren

**R4:** In-place strangler on the existing Maveren repo. Not fork-and-evolve.

- **Repo:** the same Git repo Maveren was built in. We work inside it. Branch name suggestion: `gotham` for the migration branch, merge to `main` once the strangler completes.
- **Layout during migration:**
  - Existing Maveren files (`api/`, `pages/`, `assets/`, `config/`, `dbschema/`, `scripts/`, `index.php`, root `.htaccess`) move into `_legacy/` in one dedicated commit early in Phase 0. This preserves file history via `git mv` and keeps everything findable during the port.
  - Laravel 12 scaffolds at the repo root next to `_legacy/`. Laravel's `public/` becomes the docroot.
  - `.htaccess` is rewritten to route out of `public/`; nothing in `_legacy/` is web-reachable.
- **Porting rhythm:** we port folder by folder as the phases in Section 24 dictate. Each ported folder gets a commit like `port(wallet): replace api/backend/wallet.php with app/Domains/Wallet`, and `_legacy/api/backend/wallet.php` is deleted in the same commit. When `_legacy/` is empty, we delete the folder.
- **Data:** Waynebroker is a fresh install with no Maveren data. No importer is built. If you later want to pull selected data across, we spec it separately.
- **Deployment:** deferred. Local development only until we audit the deployment target and decide how to push.
- **NOWPayments:** fresh account for Waynebroker. Credentials supplied when Phase 2 opens.
- **Maveren the running site:** untouched. Different repo checkout, different server, different DB, different domain. Whatever happens to Waynebroker's repo does not affect Maveren's production.

**On why in-place rather than fork:** the audit is our spec. Doing the strangler in the same repo makes every port commit a diff against the exact file we're replacing, so the audit's findings are impossible to miss.

---

## 22. Risks, legal, compliance

**R2:** Substantially trimmed. Gotham is a portfolio/demonstration project, trading uses Gotham-owned instruments (Gotham Charts), and the yield model mirrors Maveren's published fixed-return plans. The launch-blocking legal review that R1 recommended is no longer applicable. What remains is portfolio-quality hygiene that a reasonable reviewer of your work would expect to see.

**R1. Framing in the app.** Because instruments are Gotham-owned and the app is not making claims about outside markets, the "deceptive broker" concern from R1 does not apply. Two small things worth doing anyway, because they read well in a portfolio project:
- A one-line disclaimer in the terminal footer: "Gotham Charts are proprietary instruments. Prices are generated by Gotham and do not track external markets."
- A README section that explains the plays engine as the intentional product mechanic it is. A future employer or client who reads the repo should see this deliberately, not discover it by reading `AgentConfig`.

**R2. Portfolio hygiene documents.** Ship them because they raise the quality of the project, not because they are required for a demo.
- Privacy policy (short, honest, states what data the demo collects and does not sell).
- Terms of service (short, states it is a demo, no financial advice).
- Cookie notice — Cloudflare will nudge us on this.

**R3. KYC data.** Because the app collects ID uploads (even in demo), the storage rules from Section 13 still apply: encrypted PII columns, signed-URL delivery, and a documented retention (e.g. 30 days for demo submissions). This is table stakes and doubles as a portfolio-worthy talking point.

**R4. What is deliberately NOT built.** For a demo, we skip:
- Live sanctions/PEP screening — noted as a "if this became real" hook in the code.
- A qualified-custodian integration — same.
- Formal AML monitoring — same.

The code leaves clean interfaces where these would slot in. That is the right level of ambition for a portfolio project.

**R5. Cloudflare, Sentry, NOWPayments are still cross-border processors.** For a demo this is fine to disclose in the privacy policy and move on.

Everything else in the original R1 Section 22 (yield source, custody, launch legal review) is out of scope for a demo. If any part of Gotham later becomes a real product, revisit this section against the R1 version, which is preserved in git history.

---

## 23. Open questions

**R2:** Trimmed. Hosting, custody, legal path resolved by "shared hosting + portfolio scope + Gotham Charts".
**R3:** Six questions answered; two carried forward; one new one added.

Carried forward from R2:
- **Trading agent name shown to members** — I recommend the plain "Trading Agent".
- **Design system typography** — free fallback (Inter Display + JetBrains Mono) or paid pair? Recommend free fallback for a portfolio piece.

New in R3:
- **Brand vs URL name.** Display name is "Gotham Investments" per the plan; URL is `waynebroker.mgbah.dev`. Confirm the display name stays "Gotham Investments", or if we should rename to "Wayne Broker" to match the URL, or use a display name of "Wayne Broker" as an alternate mark for the portfolio.

Everything else in R2's Section 23 is answered by the R3 note at the top.

---

## 24. Delivery phases

**R2:** Tightened. No Reverb/Redis/PostgreSQL to set up, no legal review to wait on. Estimate down to ~9 weeks.

**Phase 0 — Local setup and repo re-layout (1 week)**
- On a `gotham` branch off `main`, one dedicated commit moves every existing Maveren file into `_legacy/` via `git mv` so history is preserved.
- Fresh Laravel 12 install scaffolds at the repo root beside `_legacy/`. React 19 + Inertia 2 starter kit applied. Tailwind 4 + shadcn/ui wired to the Gotham token layer.
- `.env.example` populated with the keys the plan calls for (no secrets).
- Docker Compose for local dev: php-fpm, mysql 8, mailpit. Runs from a `docker-compose.yml` at the repo root.
- Local `.env` set up; `php artisan migrate` works against the Docker MySQL; local dev server serves a themed "hello Gotham" landing page.
- Vitest + Pest + Playwright installed and green on the trivial starter tests.
- GitHub Actions CI: build + test on push. **Deploy job stubbed** — we'll wire the deploy pipeline when the hosting audit for the target server is done.
- Storybook (or Ladle) up with the first three shadcn primitives restyled to Gotham tokens: Button, Input, Card.
- Storybook / Playwright visual-regression baseline snapshot committed.
- End-of-phase gate: `docker compose up`, browse to `http://localhost`, see the themed hero, and `pest` + `npm test` both green.

**Phase 1 — Identity + Wallet + Ledger (2 weeks)**
Auth, MFA, admin provisioning, wallet, hash-chained append-only ledger with invariant checks, admin audit log. Full test coverage on money paths — including race-condition tests.

**Phase 2 — Deposits + Withdrawals + KYC (1.5 weeks)**
NOWPayments integration (hardened) plus a `FakeProvider` gated by `.env`, manual deposit flow, withdrawal request + admin approval, KYC upload to `storage/app/private/kyc` with signed-URL streamer. Emails queued.

**Phase 3 — Plans + Investments (1 week)**
Plans CRUD, investment lifecycle, PayoutSchedule + Payout model, cron-driven payouts as queued jobs with idempotency, admin edit/bonus/close with audit.

**Phase 4 — Gotham Charts Terminal (1.5 weeks)**
Instruments, cron-driven price generator, ticks/candles tables, HTTP polling endpoints, Lightweight Charts terminal with candles + volume, order ticket, positions, P/L. No agent yet.

**Phase 5 — Trading Agent (1 week)**
Agent config, plays library, scheduler, execute jobs, admin play editor, per-user session inspector.

**Phase 6 — Polish, hardening, portfolio prep (1 week)**
Anti-cloning layer, Cloudflare, Vite obfuscator, watermark, README that positions the project well, demo dataset seeder, launch.

Phases are gated: no phase starts before the previous phase's test suite is green.

---

## 25. Ready-for-Claude-Code checklist

Before we hand this to Claude Code, we need answers to the remaining open questions in Section 23 (agent name shown to members, typography choice, brand vs URL name). None of them are Phase 0 blockers; all can be answered inside Phase 0.

When we kick off Claude Code, the opening prompt will:

- Point Claude at this plan and the Maveren audit.
- Tell it to run `claude mcp list` and report the available MCPs before writing code.
- Instruct it to scaffold Phase 0 exactly to Section 3, no drift on stack choices without discussing.
- Instruct it to write a `docs/architecture.md` for Gotham that mirrors the Maveren audit format so we always have current truth.
- Instruct it to open PRs per bounded context, never one giant "everything at once" commit.
- Tell it that every Maveren finding (C-1..H-11, M-series) is a test case, not a suggestion; the test must exist before the fix ships.
- Tell it to stop and ask when it hits any Section 22 risk boundary; those decisions are not Claude Code's to make.

That prompt is the last artefact we produce before the first line of Laravel is written.

---

_End of plan. Mark up disagreements section by section and we'll converge before kickoff._
