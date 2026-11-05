# ADR-006 — Isolated build workers

- **Status:** Accepted
- **Date:** 2026-11-05
- **Related:** DF-006, DF-018

## Context
Building a repository executes its code. The control plane handles sessions and tenant data.

## Decision
Builds run in a dedicated worker using rootless BuildKit, with resource limits, restricted egress, an ephemeral
workspace, and no runtime secrets. The control plane never mounts a Docker socket.

## Alternatives
| Option | Why rejected |
|---|---|
| Build inside the app container | Would merge the highest-risk workload with tenant data |
| Host Docker socket in the app | Full host compromise on container escape |
| Managed CI as builder | Loss of synchronous status/leasing control; external dependency |

## Consequences
The build is the long pole in delivery and needs its own deployment. v1 accepts only operator-authorized repositories.

## Security implications
This is the primary isolation boundary. Privileged containers are forbidden as a shortcut.
