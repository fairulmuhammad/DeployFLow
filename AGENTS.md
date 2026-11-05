# AGENTS.md — DeployFlow

Operational contract for AI agents and humans working in this repository.

**Read order:** this file → `docs/GLOSSARY.md` → `docs/ARCHITECTURE.md` → the doc for the module you are touching → relevant ADR.
If two documents disagree, the more specific one wins (`docs/adr/` > module doc > `ARCHITECTURE.md` > `blueprint.md`), and you must open an issue to reconcile them.

`blueprint.md` is the original product specification. It is **not** the implementation contract where it conflicts with
`docs/`. Conflicts are resolved in `docs/adr/`.

---

## 1. Mission

DeployFlow is a self-hosted deployment control plane. It connects GitHub repositories, builds immutable container
images in isolated workers, deploys them blue-green, verifies health, and keeps the previous healthy release available
for rollback.

**v1 is not:** Kubernetes, a PaaS for arbitrary third-party code, a shell-in-the-browser, a billing product, or a
multi-region platform. See `docs/PRD.md` §Non-Goals.

---

## 2. Invariants (never violate)

| # | Invariant |
|---|---|
| I-1 | The control-plane container never runs repository code, never mounts `docker.sock`, and never holds a Docker socket. |
| I-2 | Every tenant-owned read and write is scoped to an organization. Never rely on UUID unguessability. |
| I-3 | Every mutation passes a Laravel policy. Controllers do not contain business logic. |
| I-4 | Secret values are never returned by any endpoint, never logged, never placed in build args, never baked into images. |
| I-5 | Deployments reference an immutable commit SHA and an image **digest**, never a mutable tag alone. |
| I-6 | Deployment status only changes through `DeploymentStateMachine`; terminal states are immutable. |
| I-7 | Every async dispatch is written to `outbox_messages` in the same transaction as the state change. |
| I-8 | Deployment creation is idempotent per `(environment_id, Idempotency-Key)`. |
| I-9 | Traffic is never switched before the new release passes health checks. |
| I-10 | The previous healthy release is never stopped before the new release is verified and activated. |
| I-11 | Webhook events are deduplicated by `X-GitHub-Delivery` and signature-verified against the raw body. |
| I-12 | Workers may only touch jobs they hold a live lease for. No lease, no action. |
| I-13 | Migrations are backward compatible with the previous release (expand → migrate → contract). |
| I-14 | Any change to a contract (API, job payload, state transition, schema) updates the matching doc in the same PR. |

Violating an invariant is a review-blocking defect, even if tests pass.

---

## 3. Stop and ask before

- Changing an accepted ADR or adding an ADR that contradicts `docs/ARCHITECTURE.md`.
- Adding a production dependency (Composer or npm) not listed in `docs/ARCHITECTURE.md` §Stack.
- Altering an existing table's column type, renaming a column, or dropping anything.
- Touching `docs/adr/`, `docs/SECURITY.md`, or the invariants above.
- Weakening a test, skipping a security test, or adding `@phpstan-ignore` / `// @ts-ignore` without a linked issue.
- Introducing a second dispatch mechanism, cache layer, queue driver, or HTTP client abstraction.

---

## 4. Repository map

```text
AGENTS.md                  this file
blueprint.md               original spec (historical reference)
docs/                      the implementation contract
app/
  Domain/                  entities, value objects, policies, state machines
  Application/             actions, services, DTOs, events
  Infrastructure/          GitHub, Registry, Storage, Execution adapters
  Http/                    controllers, middleware, requests, resources
  Jobs/                    control-plane queued jobs (not execution jobs)
workers/
  build-worker/            isolated builder (own process, own credentials)
  deployment-agent/        container runtime adapter (own credentials)
resources/js/              Inertia + React + TS
routes/                    web.php, internal.php, console.php
tests/                     Unit, Feature, Integration, Security
infrastructure/            docker, compose, proxy, monitoring, scripts
.github/workflows/         CI, security, image build, release
```

Ownership rule: `workers/` must not import control-plane internals. They speak HTTP contracts only
(`docs/WORKER-CONTRACTS.md`).

---

## 5. Stack (pinned)

| Layer | Version | Notes |
|---|---|---|
| PHP | 8.4 (8.3–8.5 supported) | Docker image pins 8.4; local WSL is 8.3.6 |
| Laravel | `^13.0` (13.40) | Released 2026-03-17 |
| Inertia | `inertiajs/inertia-laravel ^3.7` | Server-driven pages |
| Frontend | React 19.3 + TypeScript 5.9 + Vite 8 | `@inertiajs/react ^3.11` |
| Styling | Tailwind CSS 4 | utility-first, no component framework |
| DB | PostgreSQL 17 | `timestamptz`, UUIDv7 |
| Cache/Queue | Redis 7 | queues, locks, rate limits, nonce cache |
| Queue ops | Laravel Horizon | control-plane queues only |
| Builds | rootless BuildKit | never inside the app container |
| Routing | Traefik 3 | file-provider dynamic config for blue-green switching |
| Registry | `registry:2` (dev) / GHCR (prod) | behind `RegistryGateway` |
| Object storage | MinIO (dev) / S3-compatible (prod) | logs |
| Tests | Pest 4, Larastan 3, Pint | CI gates |
| Scans | Trivy, Composer audit, `npm audit` | CI gates |

Adding or bumping a major version requires an ADR note.

---

## 6. Commands

These are the contract. They are created during M0 (DF-001…DF-004); if a command below is missing, your task is to add
it, not to invent a new one.

```bash
make setup          # host workflow: composer install, npm ci, .env, key, build, storage link, migrate
make up             # container workflow: build + start the full stack, then run migrations
make infra          # start dependencies only (postgres, redis, minio, registry, traefik)
make down           # stop the local stack
make logs           # follow stack logs
make compose-config # validate docker-compose.yml
make dev            # make up + print the app URL
make dev-host       # app server + horizon + vite on the host (needs PHP extensions)
make migrate        # apply migrations
make test           # pest
make lint           # pint --test + tsc --noEmit
make analyse        # phpstan (level 8)
make typecheck      # tsc --noEmit
make check          # lint + analyse + test  (the gate CI runs)
make fresh          # migrate:fresh --seed (local only)
make worker-build   # run build worker against local control plane (M4)
make deploy-agent   # run deployment agent against local docker (M5)
```

`make worker-build` and `make deploy-agent` arrive with the workers (M4/M5).

Never commit `.env`, credentials, dumps, or generated build output.

---

## 7. Code conventions

**PHP**
- `declare(strict_types=1);` in every file.
- Domain models are Eloquent models in `app/Domain/<Module>/Models`; they may contain relations, casts, scopes, and
  small invariant guards — no HTTP, no facades that touch the network.
- Business operations are single-purpose Actions in `app/Application/Actions` with an `execute(DTO): Result`.
- Controllers validate via Form Requests, authorize via policies, call one Action, return Inertia/JSON.
- No `DB::raw` outside migrations, reporting queries, and `SELECT ... FOR UPDATE SKIP LOCKED` in the job repository.
- Enums are PHP backed enums in `app/Domain/<Module>/Enums`; DB stores their `value`.
- Every external system is an interface in `app/Infrastructure/<Area>` bound in a service provider, so tests can fake it.

**TypeScript/React**
- Strict mode. No `any` in committed code.
- Pages in `resources/js/Pages/<Area>/...`, shared components in `resources/js/Components`.
- Server data arrives as Inertia props; do not fetch the same data again client-side.
- All copy goes through the components in `docs/UI-SPEC.md`; no ad-hoc button/table styles.

**Naming**
- DB: snake_case plural tables, `*_id` FKs, `*_at` timestamps.
- Routes: kebab-case; route names `area.resource.action`.
- Jobs: `VerbNounJob`; events past tense; failures use codes from `docs/ERROR-CODES.md`.

---

## 8. Testing gates

A PR is not mergeable unless `make check` passes and:

| Change type | Required tests |
|---|---|
| State transition | unit test for every allowed and rejected transition |
| Policy / role | feature test per role × action, plus cross-tenant denial |
| New endpoint | feature test: 200, 403 (wrong role), 404 (other tenant), 422 (validation) |
| Webhook change | signature valid/invalid, duplicate delivery, wrong installation |
| Worker contract change | contract test against the schema in `docs/WORKER-CONTRACTS.md` |
| Migration | `migrate` + `migrate:rollback` on empty DB; no data loss on existing rows |
| Secret handling | assert value absent from response body, logs, and exception traces |
| Deployment flow | failure-injection case from `docs/TESTING.md` §Failure matrix |

Coverage: domain state machines, policies, and idempotency paths must be **exhaustively** covered. Overall line
coverage is reported, not gated.

---

## 9. Git workflow

- Branch: `feat/DF-0NN-short-slug`, `fix/DF-0NN-short-slug`, `chore/short-slug`.
- Commit: Conventional Commits, scoped by area, referencing the task:
  `feat(deployments): enforce health gate before activation (DF-025)`.
- One logical change per PR; migrations and the code that uses them ship together.
- PR body must state: task ID, invariant(s) touched, tests added, docs updated, rollback plan (for infra changes).
- Never force-push `main`. Never commit secrets. Never disable a CI gate to get green.

---

## 10. Definition of Done

From blueprint §14, plus local additions:

- [ ] Acceptance criteria of the DF task met.
- [ ] Validation, authorization, and tenant isolation implemented and tested.
- [ ] Failure paths handled with a code from `docs/ERROR-CODES.md`.
- [ ] Idempotency, timeout, cancellation, and recovery considered (deployment-related work).
- [ ] No secret in logs, responses, exceptions, or build args.
- [ ] `make check` green; docs updated (`docs/` and this file where relevant).
- [ ] Migration reversible and backward compatible.
- [ ] Observability: structured log fields and at least one metric/event for the new path.
- [ ] PR reviewed before merge.

---

## 11. Current state

- M0 nearly complete. Done: Laravel 13.40 skeleton, Inertia 3 + React 19 + TypeScript 5.9 + Vite 8 + Tailwind 4,
  Pest 4 / Larastan 3 / Pint, Horizon 5, `/up` and `/healthz` (database + Redis, no secrets), `config/deployflow.php`,
  `.env.example`, the M0 `Makefile`, the compose stack (postgres/redis/minio/registry/traefik + app/queue/horizon/
  scheduler/migrate), and `.github/workflows/ci.yml` + `security.yml`. Remaining: confirm CI green on the first pushed
  PR and pin actions by SHA before public release (DF-033).
- Verification: `make check` green; `docker compose config` valid; `/healthz` returns 503 with
  `{"status":"degraded","checks":{"database":false,"redis":false}}` on this WSL host because `pdo_pgsql`/`redis` are
  absent, and 200 inside the compose stack.
- Local toolchain caveat: this WSL install is PHP 8.3.6 without `pdo_pgsql`, `pdo_sqlite`, or `redis`, and Docker WSL
  integration is disabled — host-run DB work needs the extensions, and `docker compose up` needs Docker Desktop with
  WSL integration enabled. The Windows Docker CLI can still validate the compose file.
- Do not scaffold ad-hoc; follow `docs/TASKS.md` and `docs/agents/M0.md`.
- When M1 starts, move this section forward and keep the command table accurate.
