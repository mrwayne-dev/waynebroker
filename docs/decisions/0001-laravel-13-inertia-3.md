# ADR-0001: Laravel 13 + Inertia 3

- Status: Accepted
- Date: 2026-09-23
- Supersedes: Plan Section 3 version pins (Laravel 12, Inertia 2)

## Context

The Laravel installer used to scaffold Waynebroker in Phase 0 was on
version 5.31.1, which produces a Laravel 13 + Inertia 3 + React 19
starter kit. The plan Section 3 was written when Laravel 12 + Inertia
2 was current. Both frameworks are current stable branches and the
starter kit's tooling (vite-plus, Chisel, Pao, Wayfinder) is built
against the 13/3 line.

## Decision

Proceed on Laravel 13 + Inertia 3. Do not downgrade. Plan Section 3
is amended by R5.

## Consequences

- Every future scaffold command and package we add pins against 13/3.
- The Inertia 3 API surface (props hooks, server components boundary)
  is what the domain contexts will build against in Phase 1+.
- Any plan snippet referencing "Laravel 12" behaviour is stale and
  gets caught in R5-style reconciliation.
