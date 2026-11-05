# Testing Strategy

A deployment platform that only works when everything goes right is not reliable. Failure paths are first-class test
targets, and a feature is not done until its failure modes are covered.

---

## 1. Layout

```text
tests/
├── Unit/           pure logic: state machines, value objects, validation, redaction
├── Feature/        HTTP + DB + policies + actions (Pest, RefreshDatabase)
├── Integration/    adapters with fakes or isolated real services
├── Security/       authorization, isolation, secrets, SSRF, webhook, replay
├── FailureInjection/  controlled failures across the lifecycle
└── Performance/    k6 scripts (not run on every PR)
```

Test naming: `it('rejects a health-check failure before activating the release')`. Every test name states the expected
behavior, not the method name.

---

## 2. Test pyramid

| Layer | Share | Speed | What |
|---|---|---|---|
| Unit | 45% | ms | state machines, enums, DTO validation, image reference parsing, redaction, health result interpretation |
| Feature | 35% | 100s ms | endpoints, policies, tenant scoping, idempotency, outbox |
| Integration | 15% | seconds | GitHub adapter, registry adapter, S3 log store, worker API, Docker runtime |
| Failure injection / performance | 5% | minutes | end-to-end failure and load scenarios |

CI runs unit + feature + security on every push. Integration with real Docker/registry runs on a dedicated job with a
self-hosted or privileged runner (opt-in label), and nightly.

---

## 3. Unit tests (required)

- `DeploymentStateMachineTest`: every allowed transition succeeds; every disallowed transition throws; terminal states
  immutable; every failure requires a code.
- `WorkerJobStateMachineTest`: claim, lease expiry with attempts < max and ≥ max, cancellation, stale token.
- `ReleaseActivationTest`: one active release per environment; previous becomes inactive atomically.
- `RolePermissionTest`: role × action truth table from `PRD.md` §3.
- `ImageReferenceTest`: digest parsing, tag rejection, registry allowlist, malformed inputs.
- `IdempotencyKeyTest`: key scope, body-hash mismatch.
- `HealthCheckResultTest`: status codes, timeouts, retries, success set.
- `RedactionTest`: exact values, token prefixes, private keys, base64 blobs.
- `EnvironmentVariableKeyTest`: reserved keys, invalid characters, case sensitivity.
- `CommitShaTest` / `BranchTest`: format, injection attempts, length limits.

Target: state machine, policy, and idempotency classes have exhaustive branch coverage.

---

## 4. Feature tests (required per endpoint)

For every route:

| Case | Expected |
|---|---|
| Happy path with correct role | 200/201/202, correct props/JSON |
| Wrong role | 403 |
| Unauthenticated | 401 or redirect |
| Unverified email | 403 |
| Resource in another organization | 404 |
| Validation failure | 422 with field details |
| Rate limit | 429 after N attempts |
| Soft-deleted/archived resource | 409 or 404 per contract |

Flow coverage:

- registration → verification → organization creation → invitation → acceptance
- GitHub callback with valid/invalid state; installation sync
- application + environment + variable CRUD (value never returned)
- deployment trigger: 202, outbox row, no synchronous build
- deployment history pagination and tenant scoping
- cancel and rollback rules incl. post-activation rejection
- log access: signed URL issuance, tenant denial, missing logs
- audit records for every sensitive action

---

## 5. Integration tests

Bind fakes by default; real adapters are tested in isolation.

| Adapter | Fake | Real (nightly) |
|---|---|---|
| `GitHubAppClient` | recorded fixtures; installation token flow | GitHub App in a test account |
| `RegistryGateway` | in-memory push/pull + digest | `registry:2` container |
| `LogStore` | in-memory | MinIO container |
| `ContainerRuntime` | scripted state machine | Docker on a dedicated runner |
| Worker API | Laravel HTTP client against the app | build worker + agent processes |
| Redis queue | `QUEUE_CONNECTION=redis` in CI | same |

Rules: no test hits the public internet. Fixtures are checked in and contain no real tokens.

---

## 6. Security tests

| ID | Scenario | Expected |
|---|---|---|
| SEC-01 | Access another org's application by ID | 404 |
| SEC-02 | Access another org's deployment/logs | 404 |
| SEC-03 | Trigger deployment as viewer | 403 |
| SEC-04 | Rollback without permission | 403 |
| SEC-05 | Invalid webhook signature | 401, no row created |
| SEC-06 | Duplicate webhook delivery | one row, one deployment max |
| SEC-07 | Webhook for another installation/repo | ignored, no deployment |
| SEC-08 | Duplicate deployment request (same key) | one deployment, 202 replay |
| SEC-09 | Same key, different body | 409 |
| SEC-10 | Expired worker lease | rejected, job recoverable |
| SEC-11 | Replayed worker request | 401 (nonce) |
| SEC-12 | Worker acts on a job it does not hold | 409/403 |
| SEC-13 | Secret requested via any endpoint | value absent |
| SEC-14 | Secret appears in build args | rejected at configuration |
| SEC-15 | Secret in build log | redacted before storage |
| SEC-16 | Health-check SSRF path/host injection | rejected by validation |
| SEC-17 | Image from a non-allowlisted registry | 422 |
| SEC-18 | Tag used where digest required | 422 |
| SEC-19 | Member removal revokes access immediately | 403/404 on next request |
| SEC-20 | CSRF on browser mutation | 419 |

---

## 7. Failure-injection matrix

Each case gets a stable ID (referenced from PRD §7 and `docs/TASKS.md`). Every test asserts the final deployment state,
the release state, and that no orphan container/job is left behind.

| ID | Injected failure | Expected result |
|---|---|---|
| FT-01 | GitHub API timeout during clone | `GIT_CLONE_FAILED`, retry <= 2, then failed |
| FT-02 | Invalid installation token | `GIT_AUTH_FAILED`, no retry, audit entry |
| FT-03 | Dockerfile missing | `DOCKERFILE_MISSING`, `build` step failed |
| FT-04 | Build command exits non-zero | `BUILD_FAILED`, logs preserved, previous release intact |
| FT-05 | Build timeout | process killed, `BUILD_TIMEOUT`, workspace removed |
| FT-06 | Registry push failure | bounded retries, `PUSH_FAILED`/`REGISTRY_UNAVAILABLE` |
| FT-07 | Digest mismatch | `DIGEST_MISMATCH`, deployment failed, no deploy job |
| FT-08 | Container start failure | `CONTAINER_START_FAILED`, green removed, blue active |
| FT-09 | Health check failure | `HEALTH_CHECK_FAILED`, **no traffic switch**, previous release active |
| FT-10 | Traffic switch failure | `TRAFFIC_SWITCH_FAILED`, previous route restored |
| FT-11 | Worker crash mid-build | lease expires, requeue if attempts remain, else `WORKER_LOST` |
| FT-12 | Agent disconnect after switch | completion retried, release reconciled to green |
| FT-13 | Duplicate queue delivery | handler idempotent, one transition |
| FT-14 | Cancel during build | steps stopped, `cancelled`, workspace cleaned |
| FT-15 | Cancel after activation | 409, release unchanged |
| FT-16 | Rollback target invalid | 422, active release unchanged |
| FT-17 | Rollback health failure | `ROLLBACK_FAILED`, current release still serving |
| FT-18 | Database unavailable during callback | worker retries, eventual idempotent success |
| FT-19 | Outbox relay crash after dispatch | consumer dedupes, one side effect |
| FT-20 | Two concurrent deployments, same environment | one accepted, one `409` |

Concurrency tests use real transactions (`RefreshDatabase` with parallel connections where possible) or explicit
`DB::transaction` + row locks; never `sleep()` to "probably" avoid a race.

---

## 8. Contract tests

- JSON Schemas in `workers/contracts/` are validated against control-plane output and worker parsing in CI.
- Adding a required field fails both sides until updated.
- Error codes in worker payloads are validated against `docs/ERROR-CODES.md` (enum generated from a single PHP source).
- Step ordering is asserted in a table-driven test.

---

## 9. Performance tests (k6)

| ID | Scenario | Target |
|---|---|---|
| PT-01 | Dashboard read latency (`/dashboard`, app list) | p95 < 300 ms at 50 VUs |
| PT-02 | Deployment creation | p95 < 300 ms, 10 req/s, returns 202 |
| PT-03 | Webhook ingestion | p95 < 200 ms, 100/s, no processing inline |
| PT-04 | Log retrieval | p95 < 500 ms for 1 MiB across chunks |
| PT-05 | Queue delay (outbox → job claimable) | p95 < 5 s at steady state |
| PT-06 | Concurrent deployments | 10 environments, no cross-talk, correct states |

Build time is measured separately and reported, never asserted. Smoke thresholds are documented in
`tests/Performance/README.md` when created.

---

## 10. CI gates

```text
composer validate --strict
pint --test
phpstan analyse (level 8, no baseline)
npm run lint && npm run typecheck
php artisan test --parallel
npm run build
docker compose config
trivy fs / image scan (policy: no critical)
composer audit / npm audit
```

A red gate is fixed, never bypassed (AGENTS.md §3). Flaky tests are quarantined with an issue and a deadline, not
skipped silently.

---

## 11. Manual QA per milestone

Automation does not cover visual/UX or real GitHub behavior. Each milestone brief in `docs/agents/` lists its manual
checks. The release acceptance script in `PRD.md` §10 is executed end-to-end before tagging a release, with screenshots
and deployment IDs recorded in the release notes.

---

## 12. Fixtures and factories

- Factories for every model: `OrganizationFactory`, `UserFactory`, `ApplicationFactory`, `EnvironmentFactory`,
  `DeploymentFactory` (per status), `ReleaseFactory`, `WorkerFactory`, `WorkerJobFactory`.
- `DeploymentFactory::forStatus()` enforces legal step rows.
- Time control: `travelTo()` / `freezeTime()` for leases and retention; never assert on real elapsed time.
- Queue: `Queue::fake()` for dispatch assertions, `Bus::fake()` for job assertions; at least one integration test uses
  the real Redis queue per adapter.
- Every fixture is tenant-scoped: tests that create two organizations are the default for resource tests.
