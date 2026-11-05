# ADR-003 — PostgreSQL as the primary database

- **Status:** Accepted
- **Date:** 2026-11-05
- **Related:** DF-002

## Context
Deployment state requires strong consistency, partial unique indexes, `SKIP LOCKED` leasing, JSONB metadata, and
`inet` types.

## Decision
PostgreSQL 17 is the source of truth for all lifecycle state.

## Alternatives
| Option | Why rejected |
|---|---|
| MySQL | Weaker partial index/`SKIP LOCKED` ergonomics for this design |
| SQLite | Not suitable for concurrent workers |
| Redis as primary | Lossy, no relational integrity (explicitly forbidden) |

## Consequences
Rich constraints (one active release, one live lease) enforced in the database, not only in code.

## Security implications
Separate roles (app/migrate/readonly), TLS in production, no superuser for the app.
