# ADR-0004: Space Grotesk (display) + Inter (body) + JetBrains Mono (numeric)

- Status: Accepted
- Date: 2026-09-23
- Supersedes: Plan Section 15.2 "Inter Display" specification

## Context

The plan's Section 15.2 named "Inter Display" as the display face.
That family does not exist on Google Fonts. The plan's intent was a
distinctive display treatment that carried the plan's stated
"institutional but not stiff" tone. Neue Haas and Söhne satisfied the
intent at $500+ licensing cost; Space Grotesk satisfies the intent
for free.

## Decision

- Display / hero: Space Grotesk, weights 500 and 700.
- Body / forms / tables: Inter, weights 400, 500, 600, 700.
- Numeric / monospace: JetBrains Mono, weights 400 and 500.

All three self-hosted at build via the vite-plus Google fonts
provider. No runtime request to Google Fonts.

## Consequences

- `vite.config.ts` fetches Space Grotesk alongside Inter and
  JetBrains Mono.
- `resources/css/gotham.css` exposes `--font-display: "Space Grotesk"`
  alongside the existing `--font-body` (Inter) and `--font-mono`
  (JetBrains Mono) tokens.
- The hero and other display-scale headings use the display token.
- Every other typographic surface uses body or mono.
