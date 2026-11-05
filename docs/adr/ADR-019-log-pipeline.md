# ADR-019 — Log chunks flow through the control plane

- **Status:** Accepted
- **Date:** 2026-11-05
- **Related:** DF-020, DF-027

## Context
Workers could write directly to object storage, but then they need storage credentials and the control plane cannot
redact or index reliably.

## Decision
Workers POST bounded log chunks to the lease-authenticated API. The control plane redacts, writes to object storage,
and inserts the chunk index. The UI reads with a cursor and short-lived signed URLs; SSE is a later addition.

## Alternatives
| Option | Why rejected |
|---|---|
| Workers write to S3 directly | Extra credentials, no central redaction/index |
| Stream logs over WebSocket only | Not durable; fails on disconnect |
| Store in Redis streams | Lossy, unbounded, violates ADR-010 |

## Consequences
Log ingestion shares the control-plane request path; chunk size and rate limits protect it.

## Security implications
One redaction point, one authorization point, no storage credentials outside the control plane.
