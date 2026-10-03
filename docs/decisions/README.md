# Architecture decision records

Decisions that the plan text alone could not carry — because the plan was
written before the code existed, and reality argued back. Each record states
what was decided, what it replaces, and what it costs.

[docs/gotham-investments-plan.md](../gotham-investments-plan.md) remains the
spec. Where an ADR and the plan disagree, **the ADR wins**: the plan's R5 block
says so explicitly.

## Index

| # | Title | Status | Supersedes |
|---|---|---|---|
| [0001](ADR-0001-laravel-13-inertia-3.md) | Laravel 13 + Inertia 3 | Accepted | Plan Section 3 version pins (Laravel 12, Inertia 2) |
| [0002](ADR-0002-phpstan-level-progression.md) | PHPStan level 7 in Phase 0, level 8 from Phase 1 | Accepted | Plan Section 19 "PHPStan level 8" as the starting bar |
| [0003](ADR-0003-artisan-serve-no-docker-compose.md) | `artisan serve` for local dev, no Docker Compose | Accepted | Plan Section 24 Phase 0 Docker Compose deliverable |
| [0004](ADR-0004-typography-space-grotesk-inter-jetbrains.md) | Space Grotesk (display) + Inter (body) + JetBrains Mono (numeric) | Accepted — token naming superseded by [0006](ADR-0006-font-token-naming-alignment.md) | Plan Section 15.2 "Inter Display" specification |
| [0005](ADR-0005-dark-only-theme.md) | Dark-only theme; appearance switcher removed | Accepted | The starter kit's built-in light/dark toggle |
| [0006](ADR-0006-font-token-naming-alignment.md) | Font token naming aligns with Tailwind 4 conventions | Accepted | [ADR-0004](ADR-0004-typography-space-grotesk-inter-jetbrains.md)'s `--font-body` naming (partial) |
| [0007](ADR-0007-unified-users-table-with-roles.md) | Unified users table with role-based admin identity | Accepted | Plan Section 6's separate `admins` table |

0001-0005 are dated 2026-09-23 and record decisions taken during Phase 0.
0006 is dated 2026-09-30 and corrects a naming detail in 0004; the font
assignments 0004 made are untouched and still in force. 0007 is dated
2026-10-03 and is the first decision taken during Phase 1.

## How these work

**A new decision gets a new ADR. An existing ADR is never edited.**

If a decision changes, write the next record, mark the old one `Superseded by
ADR-NNNN`, and say in the new record what changed and why. The value of this
directory is that it shows the reasoning as it stood at the time, including the
reasoning that turned out to be wrong — editing a record in place destroys
exactly that.

The same rule points the other way too: when the plan and the code drift apart,
the fix is a new ADR plus an amendment to the plan text that cites it, not a
quiet edit to either.

A record is worth writing when the decision is expensive to reverse, when it
contradicts something already written down, or when the next person would
otherwise ask "why on earth is it like this?".

## Format

Keep it short. Title, then:

- **Status** — Accepted, Superseded by ADR-NNNN, or Rejected
- **Date**
- **Supersedes** — what it replaces, if anything
- **Context** — what was true that forced a choice
- **Decision** — what we do, in the imperative
- **Consequences** — what this costs and what it now commits us to, including
  the work it creates
