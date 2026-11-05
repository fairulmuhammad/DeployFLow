# Roadmap

Execution order and exit criteria. Task-level detail lives in `docs/TASKS.md`.
Estimates assume one developer with full context; the blueprint's original part-time estimate was 13–16 weeks.

Status: M0 is implemented and green locally; later milestones are **not started**. Update this table as work lands.

| M | Milestone | Est. | Status | Outcome |
|---|---|---:|---|---|
| M0 | Foundation | 1 w | In progress (CI on first PR) | Clean boot + green CI |
| M1 | Identity & tenancy | 1–2 w | Not started | Isolated organizations with tested roles |
| M2 | GitHub integration | 1–2 w | Not started | Repository connected; webhooks safe |
| M3 | Applications | 1 w | Not started | Configurable apps without deploys |
| M4 | Build pipeline | 2–3 w | Not started | Isolated build → verified digest |
| M5 | Deployment engine | 2–3 w | Not started | Blue-green with a health gate |
| M6 | Logs & rollback | 1–2 w | Not started | Investigate failures, restore releases |
| M7 | Security & reliability | 2 w | Not started | Failure/security suites green |
| M8 | Release | 1 w | Not started | Reproducible demo + docs |

---

## M0 — Foundation

**Objective:** a repository that anyone can clone and run, with the guardrails that keep later work honest.

Deliverables: Laravel 13 skeleton, compose dependencies, docs/ADRs, CI, Make targets, `.env.example`, health routes,
base frontend (Inertia + React + TS + Tailwind), code style and static analysis configured at target levels.

**Exit criteria**
- `make setup && make check` passes on a clean clone.
- `docker compose config` valid; no service mounts the Docker socket except the deployment agent.
- CI runs composer validate, Pint, PHPStan 8, Pest, frontend build.
- ADRs 001–022 committed; `docs/` contains no TBD that blocks M1.

**Risks:** tooling drift (pin versions), CI runtime (cache Composer/npm).

---

## M1 — Identity & Multi-Tenancy

**Objective:** prove isolation before any tenant data exists in volume.

Deliverables: auth, organizations, invitations, membership, RBAC policies, `TenantContext`, audit logging, tenant
isolation tests.

**Exit criteria**
- SEC-01…03, SEC-19 green.
- Every policy method has allow + deny coverage.
- Removing a member revokes access on the next request.

**Risks:** global-scope leaks in system contexts; invitations edge cases (email change, expiry).

---

## M2 — GitHub Integration

**Objective:** receive trusted events and know which repositories an organization may deploy.

Deliverables: GitHub App, installation callback, repository sync, webhook signature + dedupe, installation lifecycle
handling.

**Exit criteria**
- A real `push` event is accepted once and rejected if forged/replayed.
- Revoking the app blocks new deployments with `REPOSITORY_UNAVAILABLE`.
- No token or private key reaches the browser.

**Risks:** installation-token permissions; GHCR push permission ambiguity (verify before M4).

---

## M3 — Application Management

**Objective:** all configuration needed for a deploy, with no execution yet.

Deliverables: application CRUD, environments, build configuration validation, encrypted variables, dashboard pages.

**Exit criteria**
- Users can configure an app end-to-end; the deployment button exists but enqueues nothing until M4.
- Secret values are unreadable everywhere.
- PRD §9 steps 1–4 work in the UI.

**Risks:** UI scope creep; build config validation diverging from the worker contract.

---

## M4 — Build Pipeline

**Objective:** turn a commit into a verified, immutable digest.

Deliverables: worker contract, registration, leasing, checkout, rootless BuildKit build, registry push, digest capture,
log capture, timeouts and retries.

**Exit criteria**
- An authorized repository builds and pushes; digest is recorded and matches the registry.
- FT-01…07 pass.
- Workspaces are removed in success, failure, timeout, and cancel paths.
- No runtime secret is present in the build environment.

**Risks:** highest-risk milestone; isolation quality, BuildKit rootless setup, registry auth.

---

## M5 — Deployment Engine

**Objective:** take a digest to an active release without risking the current one.

Deliverables: state machine, agent auth, container deployment, health gate, blue-green switch, retention.

**Exit criteria**
- PRD §9 steps 5–8 work end-to-end.
- I-9 and I-10 are proven by tests (FT-09, FT-10).
- No tag-based deploy path exists; no arbitrary command endpoint exists.

**Risks:** Traefik switch atomicity and verification; container networking; env injection safety.

---

## M6 — Logs & Rollback

**Objective:** make failure explainable and recovery one click.

Deliverables: history, timeline, log viewer, rollback, in-app notifications.

**Exit criteria**
- A failed deployment is diagnosable from logs alone.
- Rollback restores the previous digest without a rebuild and passes the health gate.
- FT-16/17 pass; audit records exist for both.

**Risks:** log volume/retention; SSE complexity (defer if needed).

---

## M7 — Security & Reliability

**Objective:** prove the platform fails safely.

Deliverables: idempotency suite, failure-injection suite, observability, security review, runbooks.

**Exit criteria**
- SEC-01…20 and FT-01…20 green.
- Alerts fire in a staged failure drill.
- Build isolation limitations documented and accepted.

**Risks:** time sink; prioritize the matrix over polish.

---

## M8 — Release & Documentation

**Objective:** someone else can reproduce it and see the demo.

Deliverables: control-plane image pipeline, worker images, staging/prod deploy, smoke tests, monitoring, demo repo,
release notes, screenshots, known limitations.

**Exit criteria**
- PRD §10 acceptance script passes on a clean installation.
- Queue workers restart gracefully during deploy; migrations are expand/contract.
- A second developer completes the quickstart without help.

---

## Sequencing rules

1. Never start M4 before M1 isolation tests are green.
2. Never start M5 before a verified digest exists end-to-end.
3. Never start M8 before the failure suite runs.
4. A milestone is not complete because tasks are merged; its exit criteria must be demonstrated.
5. Scope additions require an ADR and a `PRD.md` update first.
