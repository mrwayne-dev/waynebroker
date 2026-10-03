# ADR-0007: Unified users table with role-based admin identity

- Status: Accepted
- Date: 2026-10-03
- Supersedes: Plan Section 6's separate `admins` table

## Context

Plan Section 6 specified a separate `admins` table with its own
MFA, audit log, and credential model. The Phase 1 Checkpoint 2
build placed admin identity on the single `users` table via
Spatie permissions and `HasRoles`. Checkpoint 3 (MFA enforcement,
session revocation) builds on `users.two_factor_confirmed_at`
and `users.session_version`.

A separate admins table would duplicate Fortify's MFA, session,
password reset and email verification scaffolding, or require
fragile cross-table joins to reuse them. It also means a user
who legitimately has both a member role (invests) and an admin
role (ops) would need two accounts — not how the product should
work.

## Decision

One `users` table. Role assignments through
`spatie/laravel-permission`. Admin audit events reference
`users.id` as the actor. Role constants live in
`App\Domains\Identity\Roles`.

## Consequences

- MFA is enforced per-role via middleware reading
  `users.two_factor_confirmed_at`.
- Session revocation (ADR-0007-adjacent, in Checkpoint 3) uses a
  `session_version` column on `users`.
- A single person can hold `member` plus one admin role
  simultaneously.
- Role revocation is a Spatie `removeRole()` call, not a row
  move.
- If a future scenario needs admins fully separated from members
  (regulatory, auditor isolation), it gets a new ADR and the
  cost gets paid then.
