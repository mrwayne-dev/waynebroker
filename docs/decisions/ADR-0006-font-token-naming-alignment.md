# ADR-0006: Font token naming aligns with Tailwind 4 conventions

- Status: Accepted
- Date: 2026-09-30
- Supersedes: ADR-0004's --font-body naming (only)

## Context

ADR-0004 named the body-copy token `--font-body`. The Tailwind 4
convention exposes body sans-serif under `--font-sans`, which is
what the `font-sans` utility, the starter kit's Blade template,
and every shadcn/ui primitive resolve to. Introducing a parallel
`--font-body` token would require either overriding every
downstream reference or keeping two names for the same value.

## Decision

Body-copy typography lives at `--font-sans`. Display lives at
`--font-display`. Numeric lives at `--font-mono`. Every ADR-0004
body/display/numeric mapping still holds; only the token key
changes.

## Consequences

- No code change from what Phase 0 already shipped.
- ADR-0004's font-family assignments (Space Grotesk, Inter,
  JetBrains Mono) remain accepted and in force.
- Future ADRs may still supersede other parts of ADR-0004 if
  the assignments themselves change; this record supersedes
  only the token key naming.
