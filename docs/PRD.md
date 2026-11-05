# Product Requirements Document (PRD)

**Product:** DeployFlow
**Type:** Developer platform / platform engineering
**Owner:** fairulmuhammad
**Status:** Draft v1 (implementation contract)
**Source:** `blueprint.md` §§1, 15, 16, reconciled by `docs/adr/`

---

## 1. Vision

DeployFlow lets a developer connect a GitHub repository, configure an application, build a container image in an
isolated worker, deploy it blue-green, verify health, and roll back to a previously verified release — from one web
interface, without hiding what is happening underneath.

The product's value is **trustworthy release management**, not the number of integrations.

---

## 2. Problem

Deploying an application requires coordinating source control, CI, image builds, a registry, a runtime host, a reverse
proxy, secrets, logs, and health checks. Each tool is individually understandable; the coordination is not. DeployFlow
is the control plane that owns the lifecycle and preserves the previous working release when something fails.

---

## 3. Users and permissions

| Role | Application | Environment | Deployment | Secrets | Members |
|---|---|---|---|---|---|
| Owner | Full | Full | Full | Full | Full (incl. transfer/delete org) |
| Admin | Full | Full | Full | Full | Invite/remove non-owner |
| Developer | Manage | Manage non-secret config | Trigger, cancel, rollback | Write-only, values never readable | None |
| Viewer | Read | Read | Read history/logs | Names only | None |

A user may belong to multiple organizations with different roles. Authorization is always evaluated against the
**active organization**; see `docs/SECURITY.md` §Tenant isolation.

---

## 4. Goals (v1)

1. GitHub App connection with repository selection.
2. Applications bound to a repository, with multiple environments.
3. Manual and webhook-triggered deployments pinned to a commit SHA.
4. Isolated, reproducible Dockerfile builds with immutable digests.
5. Blue-green deployment with a hard health gate.
6. Deployment history, step timeline, and log access.
7. Encrypted runtime environment variables.
8. Rollback to a previous verified image without rebuilding.
9. Audit trail for sensitive actions.
10. Failure detection with an actionable failure code.

---

## 5. Non-goals (v1)

- Kubernetes scheduling, CRDs, or cluster management.
- Infrastructure provisioning (servers, DNS, TLS issuance beyond Traefik defaults).
- Arbitrary language/framework detection or buildpacks.
- Browser shell, arbitrary command execution, or remote terminal.
- Public multi-tenant hosting of arbitrary third-party repositories. v1 supports repositories the operator owns or
  explicitly authorizes.
- Billing, subscriptions, usage metering.
- Multi-region, multi-cluster, or autoscaling.
- Public REST API for third-party clients (Inertia + internal worker API only; ADR-012).
- Custom domains and certificate management (deferred, FR-021).

Rationale: see blueprint §1.5 and §6.5. Scope discipline is a security control.

---

## 6. Functional requirements

| ID | Requirement | Priority | Milestone |
|---|---|---|---|
| FR-001 | Registration, login, logout, password reset, email verification | P0 | M1 |
| FR-002 | Organizations, invitations, membership management | P0 | M1 |
| FR-003 | Role-based access control enforced by policies | P0 | M1 |
| FR-004 | GitHub App installation and callback | P0 | M2 |
| FR-005 | Repository listing and selection from an installation | P0 | M2 |
| FR-006 | Application creation and configuration | P0 | M3 |
| FR-007 | Environment management with branch + auto-deploy flag | P0 | M3 |
| FR-008 | Manual deployment trigger with idempotency key | P0 | M4/M5 |
| FR-009 | Push webhook triggers deployment for matching branch | P0 | M2/M4 |
| FR-010 | Asynchronous build execution in an isolated worker | P0 | M4 |
| FR-011 | Image build, registry push, digest capture | P0 | M4 |
| FR-012 | Deployment execution by the agent | P0 | M5 |
| FR-013 | Build/deployment log capture and viewing | P0 | M4/M6 |
| FR-014 | Encrypted runtime environment variables | P0 | M3 |
| FR-015 | Health checks with traffic gating | P0 | M5 |
| FR-016 | Rollback to a previous verified release | P0 | M6 |
| FR-017 | Deployment history and step timeline | P0 | M6 |
| FR-018 | Audit logging for sensitive actions | P0 | M1+ |
| FR-019 | Deployment failure notifications (in-app) | P1 | M6 |
| FR-020 | Resource metrics per application | P1 | post-v1 |
| FR-021 | Custom domains and TLS | P2 | post-v1 |
| FR-022 | Multiple regions | P2 | post-v1 |

A requirement is only "done" when its acceptance criteria in `docs/TASKS.md` and the Definition of Done in
`AGENTS.md` §10 are satisfied.

---

## 7. Non-functional requirements

| Category | Requirement | Verification |
|---|---|---|
| Reliability | A failed deployment never replaces the last healthy release | failure-injection test FT-09 |
| Consistency | Deployment operations are idempotent | `IdempotencyTest`, concurrency test |
| Security | Tenant isolation, secret protection, isolated execution | `tests/Security/*`, review |
| Performance | HTTP requests never wait for builds; deployment POST returns 202 in < 300 ms p95 locally | k6 PT-02 |
| Scalability | Execution workers process jobs independently of web requests | lease-based API |
| Observability | Structured logs, queue/worker/deployment metrics, audit trail | `docs/OPERATIONS.md` |
| Maintainability | Modular monolith, policies, automated tests, PHPStan level 8 | `make check` |
| Recoverability | Every deployment traceable; rollback documented and tested | `docs/OPERATIONS.md` §Rollback |
| Auditability | Sensitive actions attributable to actor + org | audit log tests |
| Compatibility | Migrations are expand/contract; no downtime deploy | migration review |

---

## 8. Success criteria

Engineering targets, not current claims:

- ≥ 95% successful deployments in controlled test scenarios (20-run suite, staging).
- Every accepted deployment has a unique traceable ID and a terminal state.
- Duplicate webhook deliveries produce exactly one logical event and at most one deployment.
- A failed health check prevents traffic switch and release activation.
- The previously active release remains pullable/deployable after a failure.
- Secrets never appear in API responses, logs, build args, images, or audit metadata.
- Every failure has a code and an actionable message.
- Cross-organization access is denied for every resource type, proven by tests.
- Rollback restores the previous digest in under 2 minutes on the reference host.

---

## 9. v1 vertical slice (must work end-to-end before breadth)

From blueprint §15:

1. Register, verify email, log in.
2. Create an organization, invite a second user.
3. Connect a GitHub App installation and select one repository.
4. Create an application and a staging environment with one branch.
5. Trigger a deployment manually (idempotent).
6. Build the image in an isolated worker and push it to the registry.
7. Agent pulls by digest, starts a container, runs the health check.
8. Traffic switches only on success.
9. Dashboard shows deployment status, steps, and logs.
10. Push a broken commit: deployment fails at health check, previous release stays active.
11. Operator reads logs and rolls back to the previous digest.

Anything not required for this slice is deferred, even if listed as P1.

---

## 10. Acceptance demonstration

The public/portfolio scenario in blueprint §16 is the release acceptance script:

| # | Action | Expected evidence |
|---|---|---|
| 1 | Connect repository | Installation + repository rows; audit entry |
| 2 | Create application | Application page with staging env |
| 3 | Trigger deploy | 202, `queued`, job leased by build worker |
| 4 | Build | Steps `clone → build → push` succeed; digest stored |
| 5 | Deploy | Agent pulls by digest, container starts |
| 6 | Health check | Passes; release `active`; traffic switched |
| 7 | Dashboard | Status succeeded, timeline complete, logs viewable |
| 8 | Push broken release | Health check fails; deployment `failed` with `HEALTH_CHECK_FAILED` |
| 9 | Verify safety | Previous release still active and serving |
| 10 | Rollback | New deployment `trigger_type=rollback`, old digest reactivated, audited |

The demo is not optional: it is the definition of "works".

---

## 11. Risks

| Risk | Impact | Mitigation |
|---|---|---|
| Build isolation weaker than assumed | Critical | Rootless BuildKit on a dedicated host/VM; no docker.sock in app; documented limitations |
| Docker/BuildKit learning curve | Schedule | M4 split into checkout, build runner, workspace lifecycle |
| Traffic switch not atomic | Downtime | Traefik dynamic config replaced atomically only after the health gate; agent verifies the route before deactivating the old slot |
| Secret leakage through logs | Critical | Redaction at worker + control plane; tests assert absence |
| Scope creep into PaaS | Never ships | Non-goals list; vertical slice gate; ADR required to expand |
| Agent architecture divergence | Rework | `docs/` is the contract; invariants; contract tests |

---

## 12. Open questions

None blocking. Resolved decisions are recorded in `docs/adr/`. New questions must be added here with an owner and a
decision deadline before implementation of the affected area begins.
