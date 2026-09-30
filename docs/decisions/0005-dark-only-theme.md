# ADR-0005: Dark-only theme; appearance switcher removed

- Status: Accepted
- Date: 2026-09-23
- Supersedes: The starter kit's built-in light/dark toggle

## Context

The plan specifies a dark-only palette. Financial terminals are dark;
Waynebroker's identity leans on that. The starter kit shipped a
light/dark/system appearance toggle in Settings that, given a
dark-only palette, would either do nothing or reintroduce a light
palette the design system does not support.

## Decision

- `.dark` is hard-coded on the root `<html>`.
- The `useAppearance` hook is retained (removing it would break
  starter kit imports across many files).
- The appearance switcher UI is deleted from Settings and any nav
  that surfaced it.

## Consequences

- No user can switch to a light theme. This is intentional.
- A future decision to add light mode requires a new ADR and a full
  palette pass for the light surfaces.
- The Settings page has one fewer tab.
