# ADR-021 — Single Docker host for v1 execution

- **Status:** Accepted
- **Date:** 2026-11-05
- **Related:** DF-023, DF-025, DF-034

## Context
The blueprint targets one developer and a demonstrable portfolio release. Multi-host scheduling, placement, and
cross-host networking would dominate the schedule.

## Decision
v1 runs control plane, workers, proxy, and application containers on one Docker host. Workers already authenticate
individually, so adding remote hosts later is a payload/target-selection change, not a redesign.

## Alternatives
| Option | Why rejected |
|---|---|
| Kubernetes | Out of scope, contradicts non-goals |
| Nomad/Swarm | Operational overhead before the core loop works |
| Multi-host from day one | Schedule risk with no user-visible benefit in v1 |

## Consequences
Host is a single point of failure; documented as a v1 limitation. Scaling is vertical.

## Security implications
One tenant blast radius on the host; isolation relies on container hardening and the trusted-repository policy.
