# Gotham Investments (Waynebroker)

A Laravel 12-line + React + Inertia rebuild of the Maveren Capital platform.
Members hold a USD wallet, fund it by crypto deposit, place capital into
fixed-term investment plans that pay on a schedule, and trade Gotham Charts —
proprietary instruments whose prices this application generates itself and which
do not track any external market. An admin console handles deposits,
withdrawals, identity verification, plans and the instrument catalogue. The
product is built to production quality as a portfolio piece; the display brand
is Gotham Investments and the URL brand is Waynebroker.

## Status

**Phase 0 — in progress.** Scaffold, design tokens and the test harness only.
No business logic has been ported yet: no wallet, no ledger, no plans, no
terminal. The phase plan is in
[docs/gotham-investments-plan.md](docs/gotham-investments-plan.md) Section 24.

## Local development

Requires PHP 8.3+, Composer 2, Node 22, and a local MySQL 8.

```bash
git clone git@github.com:mrwayne-dev/waynebroker.git
cd waynebroker

composer install
npm install

cp .env.example .env
php artisan key:generate
```

Create the database and a user scoped to it — the application user is never
a global-privilege account:

```sql
CREATE DATABASE `waynebroker_local` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'waynebroker'@'127.0.0.1' IDENTIFIED BY 'your-password-here';
GRANT ALL PRIVILEGES ON `waynebroker_local`.* TO 'waynebroker'@'127.0.0.1';
```

Point `.env` at it (`DB_CONNECTION=mysql`, `DB_DATABASE=waynebroker_local`,
`DB_USERNAME=waynebroker`, `DB_PASSWORD=...`), then:

```bash
php artisan migrate
npm run build          # or `npm run dev` for the Vite dev server
php artisan serve --port=8000
```

The app is then at http://localhost:8000. There is no Docker Compose file:
Phase 0 dropped it in favour of `artisan serve` against host MySQL.

### Checks

```bash
composer ci:check      # format, lint, types, Pint, PHPStan, Pest
npm test               # Vitest component tests (tests/js)
npx playwright test    # end-to-end smoke (tests/e2e), needs `npm run build` first
npm run ladle          # component stories at http://localhost:61000
```

PHPStan runs at level 7 through Phase 0 and rises to level 8 when Phase 1 opens.

## Layout

| Path                           | What it is                                                                            |
| ------------------------------ | ------------------------------------------------------------------------------------- |
| `app/`, `routes/`, `database/` | The Laravel application                                                               |
| `resources/css/gotham.css`     | The design token layer — palette, type scale, motion, and the shadcn semantic mapping |
| `resources/js/components/ui/`  | shadcn/ui primitives, restyled through the tokens                                     |
| `resources/js/stories/`        | Ladle component stories                                                               |
| `tests/Feature`, `tests/Unit`  | Pest (PHP)                                                                            |
| `tests/js`, `tests/e2e`        | Vitest and Playwright                                                                 |
| `docs/`                        | The plan and the predecessor audit                                                    |
| `_legacy/`                     | Maveren, read-only                                                                    |

### `_legacy/`

`_legacy/` holds the Maveren Capital codebase this project is strangling. It is
reference material, not running code — nothing in it is web-reachable and
nothing imports from it. Folders are deleted from it as their replacements land
(plan Section 21). Treat it as read-only.

## Documents

- [docs/gotham-investments-plan.md](docs/gotham-investments-plan.md) — the
  build plan: stack, bounded contexts, data model, phases. Revisions R1-R4.
- [docs/architecture.md](docs/architecture.md) — the audit of Maveren. Its
  Section 17 findings (C-1, C-2, C-3, H-1..H-11, M-series) are the defect
  catalogue this rebuild exists to retire; each becomes a failing test before
  it becomes a fix.

## Hosting constraints

Production is a shared cPanel/LiteSpeed account: no root, no long-running
processes, MySQL only, and cron as the only scheduler. That is why the stack
uses the database session, cache and queue drivers, drains the queue with a
minutely `queue:work --stop-when-empty`, and delivers live prices by HTTP
polling rather than WebSockets. Redis, Reverb, Horizon and Docker-in-production
are all off the table for that reason.

On co-tenancy: the Maveren audit's finding H-5 — one MySQL user with full
privileges shared across six sites on one account — does not carry over.
Waynebroker deploys to a different host with its own database and its own
database user (plan R4, Section 4). The hygiene rule still stands: this
application's database user holds privileges on its own schema and nothing
else. The deployment target itself has not been audited yet, and the CI deploy
job is a stub until it is.
