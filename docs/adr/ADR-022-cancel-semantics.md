# ADR-022 — Cancellation only before activation

- **Status:** Accepted
- **Date:** 2026-11-05
- **Related:** DF-021, DF-028

## Context
The blueprint allows cancel up to `Building` and shows `Succeeded → RollingBack`, leaving post-switch behavior undefined.

## Decision
Cancel is accepted in `queued`, `cloning`, `building`, `pushing`, and `deploying`, and during `health_checking` only
before the traffic switch. After activation, cancel returns `409 CANNOT_CANCEL_AFTER_ACTIVATION`; the only recovery is a
rollback deployment. Cancellation sets `cancellation_requested_at`, is signalled to the worker via heartbeat, and is
idempotent.

## Alternatives
| Option | Why rejected |
|---|---|
| Cancel at any time | Would tear down a live release with no replacement |
| No cancellation | Wastes build resources and confuses users |
| Cancel = delete record | Destroys audit history |

## Consequences
A clear commit point: traffic switch. Users see the same rule in the UI.

## Security implications
No path allows tearing down a healthy release without an explicit, audited rollback.
