# ADR-0002: PHPStan level 7 in Phase 0, level 8 from Phase 1

- Status: Accepted
- Date: 2026-09-23
- Supersedes: Plan Section 19 "PHPStan level 8" as the starting bar

## Context

The scaffold ships with PHPStan level 7 configured and green. The
plan called for level 8. Dropping to 6 and raising later would have
been a step backward from the starter kit's baseline; raising to 8
immediately would have generated dozens of violations in scaffold
code before Waynebroker had written a single domain line.

## Decision

Phase 0 runs at level 7. The first commit on the Phase 1 branch
raises the bar to level 8 and fixes any violations that surface.
Level 8 is the standing bar from that point onward.

## Consequences

- Phase 0 CI passes at level 7.
- Phase 1's opening commit is a bar-raising step, not a domain
  feature. This is a deliberate signal that Phase 1 code is written
  to a higher standard than the scaffold.
- Any new file added in Phase 0 that would fail at level 8 will get
  cleaned up as part of that first Phase 1 commit.
