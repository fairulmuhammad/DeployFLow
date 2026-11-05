# Deploying DeployFlow

This is how the platform itself is built, released, and rolled back. It is deliberately stricter than how user
applications are deployed, because a mistake here affects every tenant.

---

## 1. Images and artifacts

| Image | Built from | Runs as | Notes |
|---|---|---|---|
| `deployflow/control-plane` | `Dockerfile` (PHP-FPM + nginx or Octane) | app + queue + horizon | no Docker socket, no build tools |
| `deployflow/build-worker` | `workers/build-worker/Dockerfile` | build worker | rootless BuildKit |
| `deployflow/deployment-agent` | `workers/deployment-agent/Dockerfile` | deploy agent | narrow Docker access |
| `deployflow/migrate` | same as control-plane, entrypoint runs migrations | one-shot | separate DB role |

Every image:
- is tagged with the full commit SHA and (for releases) a semver tag;
- embeds an SBOM (`/sbom.spdx.json`);
- is scanned by Trivy; critical findings block promotion;
- runs as non-root, read-only root filesystem where possible;
- has no secrets baked in.

The tag is not the deployable reference for user applications — that rule applies to DeployFlow's own release too:
promotion records the digest.

---

## 2. Pipeline

```text
PR → CI (lint, analyse, unit, feature, security, frontend, compose config)
   → image build (SHA tag) + SBOM + Trivy
   → merge to main
   → push SHA image to registry
   → deploy staging (compose or orchestrator)
   → run migrations (expand step)
   → smoke tests (health, login, org create, deployment dry-run)
   → manual approval
   → deploy production (blue-green or rolling)
   → run migrations (contract step, next release)
   → verify health + queue + workers
   → tag release, publish notes
```

Workflow files (blueprint §8.2): `ci.yml`, `security.yml`, `build-control-plane.yml`, `release.yml`,
`dependency-review.yml`. Pin third-party actions by full SHA.

---

## 3. Migration policy (expand → contract)

Migrations must be safe while the previous version is still running (I-13).

| Change | Pattern |
|---|---|
| Add column | nullable or with default; backfill in batches; add NOT NULL in a later release |
| Rename column | add new → dual-write → backfill → switch reads → drop old in a later release |
| Drop column | stop using it in release N; drop in release N+1 |
| Add index | `CREATE INDEX CONCURRENTLY` outside a transaction |
| Add FK | add `NOT VALID`, validate in a separate migration |
| Enum change | add value; never remove/rename in place |
| Data migration | separate command, idempotent, batched, resumable |

Rules:
- Never edit an applied migration.
- Every migration has a working `down()` or an explicit irreversible note in the PR.
- `migrate --force` runs as the `migrate` role, separate from the app role.
- Long backfills run as queue jobs, not in the deploy path.
- A migration PR includes the rollback plan in the description.

---

## 4. Staging

Purpose: prove the release and the worker contracts before production.

- Separate database/Redis/bucket/registry namespace.
- One build worker and one deployment agent, pointed at a dedicated Docker host.
- Demo application repository (`deployflow-demo`) with a known-good and a known-broken commit.
- Smoke script (must pass):
  1. `/up` 200, `/healthz` 200.
  2. Login + organization + application fixture exists.
  3. Trigger deployment of the good commit → succeeds, release active.
  4. Trigger the broken commit → fails with `HEALTH_CHECK_FAILED`, previous release still active.
  5. Rollback → succeeds, digest matches the earlier release.
  6. Duplicate webhook delivery → no second deployment.

---

## 5. Production

### Order of operations

1. Pre-flight: backup verification, disk space, registry reachable, worker heartbeats fresh.
2. Put the app in maintenance mode only for the seconds required by the migration step; prefer zero-downtime otherwise.
3. Run **expand** migrations.
4. Deploy new app containers alongside the old ones (or blue-green at the proxy).
5. Wait for health + a real request through the proxy.
6. Restart queue workers gracefully: `php artisan queue:restart` (Horizon picks it up); do not kill in-flight jobs.
7. Verify Horizon is processing; verify the outbox relay is draining.
8. Shift traffic; keep the old app slot for 15 minutes.
9. Run **contract** migrations in a later release.
10. Record release metadata (image digest, migration set, operator).

### Zero-downtime constraints

- No destructive migration in the same release as the code that needs it.
- Queue jobs must tolerate both old and new payload shapes during rollout (additive contract changes only).
- Sessions in Redis survive app restarts; changing `APP_KEY` invalidates them intentionally.

### Rollback of the control plane

- Re-deploy the previous image **digest** (not tag).
- Roll back only additive migrations; never run `migrate:rollback` in production for destructive changes.
- If a bad migration is already applied, ship a forward fix — do not reverse in place.
- Verify: health, login, deployment list, worker heartbeats, queue processing.
- Record the incident and add a regression test.

---

## 6. Compose topology (v1 reference host)

```yaml
services:
  traefik:        # :80/:443, file provider, watches /traefik/dynamic
  app:            # control-plane, no docker socket
  queue:          # same image, queue:work
  horizon:        # same image, horizon
  scheduler:      # schedule:work
  postgres:       # volume, healthcheck, app/migrate/readonly roles
  redis:          # password, no published port
  minio:          # logs bucket (dev; use managed S3 in prod)
  registry:       # registry:2 (dev; GHCR in prod)
  build-worker:   # rootless BuildKit, egress-limited
  deploy-agent:   # docker access limited to deployflow-* labels
```

Hard rules:
- Only `deploy-agent` may mount the Docker socket, and it must be documented as the highest-risk service.
- `app`, `queue`, `horizon`, `scheduler` share the same image and config.
- No service uses `network_mode: host`.
- Volumes are named; no bind mounts of the host root.
- Resource limits on every service.

---

## 7. Secrets in deployment

- Production secrets come from the orchestrator's secret store or mounted files, never from the image or the repo.
- `GITHUB_APP_PRIVATE_KEY_PATH` points to a read-only mounted file.
- Rotate in this order: provider → environment → deploy → revoke old.
- After rotating `APP_KEY`, run the documented re-encryption command and invalidate sessions.

---

## 8. Smoke tests and acceptance

Automated (post-deploy script):
- `/up`, `/healthz`
- login page renders
- public webhook endpoint rejects an unsigned request with 401
- worker heartbeat age < 60 s
- queue depth < threshold after 2 minutes

Manual (release acceptance, PRD §10): full deploy → break → rollback scenario with screenshots and deployment IDs.

---

## 9. Rollback decision table

| Symptom | Action |
|---|---|
| New app version unhealthy | redeploy previous digest, keep DB |
| Bad additive migration | ship forward fix; do not roll back |
| Worker contract mismatch | redeploy previous worker + app together |
| Registry unavailable | keep running containers; retry pushes; no new deploys |
| Mass deployment failures with one code | stop new deployments, inspect worker/registry, then rollback app if needed |

---

## 10. Post-release checklist

- [ ] Release digest and tag recorded.
- [ ] Migrations listed (expand/contract).
- [ ] Smoke + acceptance evidence attached.
- [ ] Alerts silent for 30 minutes.
- [ ] No failed jobs accumulating.
- [ ] Worker heartbeats fresh.
- [ ] Backup completed after release.
- [ ] Docs updated (this file, `OPERATIONS.md` if runbooks changed).
