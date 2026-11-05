# ADR-016 — Idempotency scoped per environment

- **Status:** Accepted
- **Date:** 2026-11-05
- **Related:** DF-030

## Context
A globally unique `idempotency_key` lets one tenant probe or collide with another tenant's keys.

## Decision
Uniqueness is `(environment_id, idempotency_key)` where the key is not null. The stored request hash must match on
replay; otherwise return `409 IDEMPOTENCY_KEY_REUSED`. Creation and outbox write share a transaction.

## Alternatives
| Option | Why rejected |
|---|---|
| Global unique key | Cross-tenant oracle and collisions |
| No key | Duplicate deployments from retries and double-clicks |
| Key stored in Redis only | Loss on flush; violates I-7 |

## Consequences
Clients must resend the same body with the same key to get replay semantics.

## Security implications
Keys cannot be used to enumerate another organization's requests.
