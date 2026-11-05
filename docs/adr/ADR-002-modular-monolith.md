# ADR-002 — Modular monolith, not microservices

- **Status:** Accepted
- **Date:** 2026-11-05
- **Related:** DF-003

## Context
Domains (identity, organizations, integrations, applications, deployments, builds) are distinct but share transactions
and invariants. The team is one developer.

## Decision
Keep business logic in one deployable Laravel application organized by domain modules, with enforced dependency rules
(`docs/ARCHITECTURE.md` §3). Execution is separated (ADR-006), business logic is not.

## Alternatives
| Option | Why rejected |
|---|---|
| Microservices per domain | Distributed transactions, duplicated auth, operational overhead with no scale need |
| Single unstructured Laravel app | Faster at first, but boundaries erode and tests become feature-coupled |

## Consequences
One deploy, one database, transactional consistency, simpler debugging. Extraction requires an ADR and a real
operational driver.

## Security implications
Smaller network surface; fewer service-to-service credentials to protect.
