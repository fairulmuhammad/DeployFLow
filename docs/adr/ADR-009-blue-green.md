# ADR-009 — Blue-green release activation

- **Status:** Accepted
- **Date:** 2026-11-05
- **Related:** DF-025

## Context
A failed deployment must not replace a healthy release. Two slots per environment give a verified fallback.

## Decision
Run blue and green slots per environment. Start the idle slot, health-check it, atomically switch the Traefik route, then
retain the previous slot for a bounded window before removal.

## Alternatives
| Option | Why rejected |
|---|---|
| Rolling update | Harder to guarantee instant fallback with a single replica |
| Recreate in place | Downtime and no safe rollback |
| Canary | Requires traffic weighting and more observability than v1 |

## Consequences
Doubles peak container footprint per environment; requires a proxy with dynamic config.

## Security implications
The previous release stays available for rollback; the switch is the single commit point and is verified after the fact.
