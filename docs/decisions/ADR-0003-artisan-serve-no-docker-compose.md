# ADR-0003: `artisan serve` for local dev, no Docker Compose

- Status: Accepted
- Date: 2026-09-23
- Supersedes: Plan Section 24 Phase 0 Docker Compose deliverable

## Context

R2 established shared hosting as the production target. The plan
still called for Docker Compose in local dev for parity with a
Compose-based staging environment we never built. The shared-hosting
production target runs php-fpm behind LiteSpeed with no containers,
so Compose gives us local parity with an environment that does not
exist.

## Decision

Local development runs Laravel's built-in server: `php artisan serve
--port=8000` against a host-installed MySQL 8 and a
`waynebroker_local` database. Mail routes to the log driver in Phase
0; mailpit gets set up at Phase 2 when the first user-facing email is
sent.

## Consequences

- Phase 0 gate is `http://localhost:8000`, not
  `http://localhost`.
- CI uses SQLite in-memory (Phase 0) and MySQL as a service container
  (Phase 1 onward). Neither is Docker Compose.
- A future "worked on my machine" bug that only Compose would have
  caught is the trigger to revisit this decision.
