# ADR-010 — Deployment logs in object storage

- **Status:** Accepted
- **Date:** 2026-11-05
- **Related:** DF-020, DF-027

## Context
Build logs are large, append-only, and rarely queried by the database.

## Decision
Workers upload log chunks; the control plane redacts and writes them to S3-compatible storage. PostgreSQL stores only a
chunk index (`deployment_logs`). The UI reads via cursor pagination and short-lived signed URLs.

## Alternatives
| Option | Why rejected |
|---|---|
| Log text in PostgreSQL | Bloat, vacuum pressure, poor fit |
| Only the worker's local disk | Not durable, not reachable, lost on failure |
| External log SaaS | Extra dependency and secret surface for v1 |

## Consequences
Log retrieval requires storage availability; retention is a lifecycle job.

## Security implications
Private bucket, signed URLs, double redaction, no secret values at rest.
