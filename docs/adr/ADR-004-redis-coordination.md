# ADR-004 — Redis for queues, cache, locks, and replay cache

- **Status:** Accepted
- **Date:** 2026-11-05
- **Related:** DF-002, DF-017

## Context
We need low-latency queueing, rate limits, distributed locks, and a nonce cache. Deployment state must survive Redis
loss.

## Decision
Redis 7 provides queues, cache, locks, rate limiting, and worker nonce storage. PostgreSQL remains authoritative for all
deployment/job/release state.

## Alternatives
| Option | Why rejected |
|---|---|
| Database queue | Simpler ops, but polling latency and lock contention for internal jobs |
| SQS/managed queue | Extra cloud coupling; local dev friction |
| Redis as source of truth | Violates recoverability |

## Consequences
A Redis flush loses no accepted deployment; it only delays dispatch until the outbox relay re-publishes.

## Security implications
Password-protected, no public port, separate logical DBs, nonce keys expire automatically.
