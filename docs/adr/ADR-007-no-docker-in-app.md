# ADR-007 — No Docker runtime access in the web application

- **Status:** Accepted
- **Date:** 2026-11-05
- **Related:** DF-002, DF-023

## Context
Mounting `docker.sock` grants effective root on the host and full control of every container.

## Decision
Only the deployment agent may access the container runtime, through a narrowly scoped adapter limited to resources
labelled `deployflow.managed=true` for assigned environments.

## Alternatives
| Option | Why rejected |
|---|---|
| Socket mount in app | Unacceptable blast radius |
| TCP Docker API without TLS | Unauthenticated root-equivalent API |
| Kubernetes API | Out of scope for v1 (ADR-021) |

## Consequences
One extra service to operate; a clear audit boundary.

## Security implications
Container escape from the app no longer implies host/container control.
