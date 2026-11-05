# ADR-015 — Denormalized organization_id with composite foreign keys

- **Status:** Accepted
- **Date:** 2026-11-05
- **Related:** DF-008, DF-015

## Context
Blueprint §3.1 requires organization ownership on every tenant entity, but child tables (environments, deployments,
releases, variables) only reference their parent. Application-level scoping alone is one bug away from a leak.

## Decision
Every tenant table stores `organization_id`. Children use composite foreign keys
`(organization_id, parent_id) → parent(organization_id, id)`, with a unique `(organization_id, id)` on each parent.

## Alternatives
| Option | Why rejected |
|---|---|
| Join-only scoping | Slow and easy to forget in new queries |
| Single global scope on the root | Does not protect direct child queries |
| Postgres RLS | Strong, but adds session-variable coupling and ops complexity for v1 |

## Consequences
Slightly wider tables and migrations; isolation is enforced by the database as well as the code.

## Security implications
A cross-tenant parent reference becomes impossible, not merely discouraged.
