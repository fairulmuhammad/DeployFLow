# ADR-014 — Rollback creates a new deployment

- **Status:** Accepted
- **Date:** 2026-11-05
- **Related:** DF-028, blueprint §5.4

## Context
Blueprint §2.4 models `RollingBack/RolledBack` on the original deployment, while §4.7 implies a rollback action on a
deployment. Reusing a terminal record destroys audit history and makes "latest" queries ambiguous.

## Decision
Rollback creates a new `deployments` row with `trigger_type=rollback` and `rollback_of_deployment_id` set to the
deployment whose release is restored. It pulls the stored digest and never rebuilds. The original deployment is never
mutated.

## Alternatives
| Option | Why rejected |
|---|---|
| Mutate the original record | Terminal state becomes mutable; history lost |
| Separate `rollbacks` table | Duplicates the lifecycle, steps, and logs model |
| Re-deploy the commit | Rebuild risk; not a true rollback |

## Consequences
Every action is a deployment; history and permissions stay uniform.

## Security implications
Rollback can only target a verified release in the same environment; the digest is immutable.
