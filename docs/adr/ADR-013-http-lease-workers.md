# ADR-013 — Execution workers use an HTTP lease API

- **Status:** Accepted
- **Date:** 2026-11-05
- **Related:** DF-016, DF-017

## Context
Blueprint §2.3 dispatches builds via Laravel Queues on Redis; §4.8 defines a worker claim/heartbeat API. Running both
would create two sources of truth for the same job.

## Decision
Execution jobs (build, deploy, rollback) live in `worker_jobs` and are leased over the authenticated HTTP API. Redis
queues are used only for control-plane internal jobs: webhook processing, outbox relay, notifications, reconciliation,
retention.

## Alternatives
| Option | Why rejected |
|---|---|
| Redis queue for external workers | Exposes Redis to workers, weak per-job auth, no lease semantics |
| Both mechanisms | Ambiguous ownership, double execution risk |
| Database polling only | Same as chosen; the lease API is the transport |

## Consequences
Workers need outbound HTTPS only, not Redis access. Lease expiry is the single liveness authority.

## Security implications
Per-worker credentials, replay protection, and job ownership checks are enforceable at one boundary.
