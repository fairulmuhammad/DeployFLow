# ADR-011 — Transactional outbox for dispatch

- **Status:** Accepted
- **Date:** 2026-11-05
- **Related:** DF-017, DF-030

## Context
Writing a deployment row and publishing a queue job are two systems. A crash between them loses work; publishing before
commit dispatches work for a rollback.

## Decision
State change and `outbox_messages` row commit in one transaction. A relay publishes pending messages and marks them
dispatched. Consumers are idempotent on the outbox id.

## Alternatives
| Option | Why rejected |
|---|---|
| Dispatch inside the transaction | Publishes before commit; consumer may read missing state |
| Dispatch after commit without outbox | Crash window loses the job |
| DB queue only | Still needs a dispatch step for external workers |

## Consequences
At-least-once delivery; idempotency is mandatory (I-7/I-8).

## Security implications
No work is created for rolled-back transactions; replay cannot duplicate side effects.
