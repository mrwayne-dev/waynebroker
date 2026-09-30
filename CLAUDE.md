# Working on Waynebroker

Read these two before writing code. They are the spec and the defect catalogue.

1. [docs/gotham-investments-plan.md](docs/gotham-investments-plan.md) — the
   build plan. Stack (Section 3), bounded contexts (5), data model (6),
   security overhaul (13), design system (15), phases (24). Revisions R1-R4
   sit at the top; later revisions override earlier ones.
2. [docs/architecture.md](docs/architecture.md) — the audit of Maveren
   Capital, the procedural PHP application this project is replacing. Its
   Section 17 findings are the reason most of the plan reads the way it does.

## What this is

Gotham Investments (URL brand: Waynebroker) is a Laravel + React + Inertia
rebuild of Maveren: USD wallet, crypto deposits, fixed-term investment plans
paying on a schedule, admin approval workflows, KYC, and a trading terminal
over Gotham Charts — instruments this application generates itself and which
track no external market. Portfolio project, production quality.

An in-place strangler: Laravel sits at the repo root, the old code sits in
`_legacy/`, and folders leave `_legacy/` as their replacements land.

## Rules

1. **The plan is the plan.** Substituting Livewire for React, PostgreSQL for
   MySQL, Redis for the database drivers, or a real queue worker for the
   cron-drained database queue is drift. The plan explains why each choice
   exists — shared hosting drives most of them. Propose, do not swap.
2. **Every Maveren finding is a failing test before it is a fix.** C-1, C-2,
   C-3, H-1..H-11 and the M-series in `docs/architecture.md` Section 17 are a
   checklist to retire, not history to read. The concurrent-withdrawal test
   lands with the wallet, failing, and the ledger service makes it pass. The
   same for IPN idempotency, the admin audit trail, GET-accepted state
   changes, and the rest.
3. **Money is `BIGINT` minor units.** Never a float, never a decimal bound to
   a PHP float. Cast to integer cents at the boundary and keep it integer
   through the whole path. Formatting happens at display, once.
4. **Wallet mutations go through one ledger service.** No controller, job or
   command touches `wallets.balance_cents`. The row lock, the
   `CHECK (balance_cents >= 0)` constraint, the hash chain and the audit trail
   live in that one place.
5. **State-changing endpoints never accept GET.** Enforced by a test that
   walks the route table, not by good intentions.
6. **Never widen a role gate.** If the plan says `finance_admin`, do not write
   `['finance_admin', 'super_admin']` because it feels safer. Ask.
7. **Announce every new dependency** with one line of justification before
   installing it.
8. **Stop and ask** on product decisions the plan does not answer, and when
   the plan and the audit contradict each other. Propose; do not invent.
9. **Comments explain why, not what.** The audit singled out Maveren's dense
   in-code reasoning as the one thing worth keeping. Keep it.
10. **`_legacy/` is read-only.** Reference it freely, never edit it, and only
    delete from it when its replacement has landed.

## Environment

- PHP 8.3, Composer 2, Node 22, MySQL 8 on 127.0.0.1:3306.
- Local databases: `waynebroker_local` and `waynebroker_testing`, owned by the
  `waynebroker`@`127.0.0.1` user, which holds privileges on those two schemas
  and nothing else. Credentials in `.env` (untracked).
- Serving: `php artisan serve --port=8000`. No Docker Compose — dropped from
  the Phase 0 gate.
- Frontend tooling is `vite-plus` (`vp`), not plain Vite: `npm run check` is
  format + lint + types, `npm test` runs the Vitest it bundles. Do not install
  a separate `vitest` — the bundled 4.1.11 is pinned and a second copy is an
  unresolvable peer conflict.
- Stories run in Ladle, not Storybook (Storybook 10 refuses `vite-plus` 0.3.0).

## Checks before you call something done

```bash
composer ci:check      # Pint, PHPStan, Pest, format, lint, types
npm test               # Vitest
npx playwright test    # E2E (run `npm run build` first)
```

PHPStan is at level 7 for Phase 0 and rises to 8 at the start of Phase 1.

## Open decisions

Section 23 of the plan tracks these. Answered so far: the member-facing name
for the agent is "Trading Agent"; typography is Inter with JetBrains Mono for
numerals; the display name is Gotham Investments while the URL brand is
Waynebroker.
