# ADR-020 — Worker authentication by per-worker HMAC

- **Status:** Accepted
- **Date:** 2026-11-05
- **Related:** DF-016, DF-022, blueprint §4.8

## Context
Blueprint requires strong worker auth and replay protection, allowing mTLS or short-lived credentials.

## Decision
Each worker gets a unique secret at registration. Requests carry timestamp, nonce, and an HMAC-SHA256 signature over
method, path, timestamp, nonce, and body hash. The server enforces a 5-minute window, a nonce cache, revocation, and
lease ownership. TLS is mandatory; mTLS is a documented upgrade path, not v1.

## Alternatives
| Option | Why rejected |
|---|---|
| Static bearer token | No replay protection, hard to scope per job |
| mTLS only | Certificate distribution/rotation is heavy for v1 |
| Signed JWTs only | Adds a token service for little gain |
| Redis queue access | Weak per-job authorization |

## Consequences
Worker clocks must be synchronized; registration is an operator action.

## Security implications
Captured requests are unusable after the nonce/skew window; compromise is limited to one worker's assignments.
