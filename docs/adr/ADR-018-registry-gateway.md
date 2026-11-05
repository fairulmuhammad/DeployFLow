# ADR-018 — Registry behind a gateway, local registry first

- **Status:** Accepted
- **Date:** 2026-11-05
- **Related:** DF-019

## Context
The blueprint targets GHCR, but local development and CI should not require GitHub credentials or network access.

## Decision
Define `RegistryGateway` with two implementations: `LocalRegistry` (`registry:2`) for development/CI and `GhcrRegistry`
for production. Deployment payloads carry an allowlisted registry host and are rejected otherwise.

## Alternatives
| Option | Why rejected |
|---|---|
| GHCR only | Credential coupling in dev/CI, flaky tests |
| Hard-coded registry | Blocks self-hosting and migration |
| No allowlist | Arbitrary image deployment risk |

## Consequences
Two adapters to test; digest semantics must match exactly.

## Security implications
Image sources are constrained at configuration and execution time.
