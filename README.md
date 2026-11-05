# DeployFlow

A self-hosted deployment control plane: connect a GitHub repository, build an immutable container image in an isolated
worker, deploy it blue-green, verify health, and roll back to a previously verified release.

> **Status:** specification complete, implementation not started (M0). Only `blueprint.md`, `docs/`, and `AGENTS.md`
> exist today. The quickstart below is the target contract for M0.

---

## Why

Deploying an app means coordinating source control, CI, image builds, a registry, a runtime host, a proxy, secrets,
logs, and health checks. DeployFlow owns that lifecycle and — critically — never replaces a healthy release with a
broken one.

**It is not:** Kubernetes, a public multi-tenant PaaS, a browser shell, or a billing product. See
[`docs/PRD.md`](docs/PRD.md) §Non-Goals.

---

## How it works

```text
GitHub ──webhook──▶ Control plane (Laravel + Inertia/React)
                        │  state machine, policies, outbox
                        ├──▶ PostgreSQL (truth)  Redis (queue/locks)
                        └──▶ Build worker ──rootless BuildKit──▶ Registry (digest)
                                   │
                              Deployment agent ──▶ Docker ──▶ blue/green slots
                                   └── health gate ──▶ Traefik route switch
```

Three principles drive the design:

1. **The control plane never runs repository code and never mounts `docker.sock`.**
2. **Deployments reference an immutable commit and image digest, never a mutable tag.**
3. **Traffic moves only after a health check; the previous release stays until the new one is verified.**

Full detail: [`docs/ARCHITECTURE.md`](docs/ARCHITECTURE.md), [`docs/STATE-MACHINES.md`](docs/STATE-MACHINES.md),
[`docs/WORKER-CONTRACTS.md`](docs/WORKER-CONTRACTS.md), [`docs/SECURITY.md`](docs/SECURITY.md).

---

## Stack

Laravel 13 (PHP 8.4) · Inertia + React 19 + TypeScript · Tailwind 4 · PostgreSQL 17 · Redis 7 · BuildKit (rootless) ·
Traefik 3 · S3-compatible log storage · Pest 4 · Larastan 8 · Trivy.

---

## Quickstart (available after M0)

```bash
git clone https://github.com/fairulmuhammad/DeployFlow.git
cd DeployFlow
cp .env.example .env
make setup     # composer install, npm ci, key, migrate, storage link
make dev       # postgres, redis, minio, registry, traefik, vite, queue, horizon
make check     # lint + static analysis + tests
```

Open `http://localhost`. First-run: register, verify email (mail is logged locally), create an organization, connect the
GitHub App, then create an application.

Prerequisites: PHP 8.3+ with `pgsql`/`redis` extensions, Composer 2, Node 22, Docker 27+ with BuildKit, `make`.

---

## Documentation map

| Document | Read it when |
|---|---|
| [`AGENTS.md`](AGENTS.md) | Starting any work — invariants, commands, Definition of Done |
| [`docs/PRD.md`](docs/PRD.md) | Product scope, roles, acceptance demo |
| [`docs/ARCHITECTURE.md`](docs/ARCHITECTURE.md) | Component boundaries and flows |
| [`docs/DATA-MODEL.md`](docs/DATA-MODEL.md) | Schema, constraints, indexes |
| [`docs/STATE-MACHINES.md`](docs/STATE-MACHINES.md) | Lifecycle rules |
| [`docs/API.md`](docs/API.md) | Routes, conventions, errors |
| [`docs/WORKER-CONTRACTS.md`](docs/WORKER-CONTRACTS.md) | Worker API and job payloads |
| [`docs/SECURITY.md`](docs/SECURITY.md) | Threat model and invariants |
| [`docs/TESTING.md`](docs/TESTING.md) | Test matrix and failure injection |
| [`docs/ROADMAP.md`](docs/ROADMAP.md) / [`docs/TASKS.md`](docs/TASKS.md) | Planning and task IDs |
| [`docs/OPERATIONS.md`](docs/OPERATIONS.md) | Runbooks |
| [`docs/DEPLOYMENT.md`](docs/DEPLOYMENT.md) | Releasing DeployFlow itself |
| [`docs/adr/`](docs/adr/) | Why each decision was made |
| [`blueprint.md`](blueprint.md) | Original specification (historical) |

---

## Known v1 limitations

- Single Docker host; no multi-region or autoscaling.
- Dockerfile builds only (no buildpacks, no framework auto-detection).
- Trusted repositories only; not a public multi-tenant builder.
- In-app notifications only; no email/Slack alerting.
- Health checks are HTTP path/port probes, not arbitrary URLs.

---

## License

MIT (proposed).
