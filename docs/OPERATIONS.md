# Operations

How to run DeployFlow when something is wrong. Every runbook is written to be followed under pressure: numbered steps,
copy-pasteable commands, explicit stop conditions.

---

## 1. Dashboards and signals

| Signal | Source | Healthy |
|---|---|---|
| HTTP error rate (5xx) | reverse proxy / app metrics | < 1% over 5 min |
| Request latency p95 | app metrics | < 500 ms |
| Queue depth | Horizon / Redis | < 100, draining |
| Queue oldest job age | outbox/metrics | < 60 s |
| Failed jobs (15 min) | `failed_jobs` | 0 |
| Active deployments | metrics | matches expected |
| Deployment success rate (24 h) | metrics | > 95% |
| Deployment duration p95 | metrics | within configured timeout |
| Worker heartbeat age | `workers.last_heartbeat_at` | < 60 s |
| Registry failures (15 min) | metrics | 0 |
| Outbox pending age | `outbox_messages` | < 60 s |
| Database connections | Postgres | < 80% of max |
| Disk usage | host | < 80% |

---

## 2. Alerts

| Condition | Severity | Route |
|---|---|---|
| Control-plane API unavailable (2 min) | Critical | page |
| Database unavailable (1 min) | Critical | page |
| Secret exposure suspected | Critical | page + security |
| Worker heartbeat missing (5 min) | Warning | chat |
| Queue backlog growing (10 min) | Warning | chat |
| Build worker unavailable (5 min) | Warning | chat |
| Deployment health-check failure spike | High | chat + owner |
| Repeated registry failures (3 in 10 min) | High | chat |
| Outbox pending age > 5 min | High | chat |
| Disk > 85% | High | chat |
| Failed jobs > 0 for 15 min | Warning | chat |

Alert rules live in `infrastructure/monitoring/` (created in DF-032). Every alert links to the matching runbook section.

---

## 3. Runbooks

### RB-01 — Build worker offline

1. Confirm: `select name, status, last_heartbeat_at from workers where type='build';`
2. Check the container: `docker compose ps build-worker` and `docker compose logs --tail=200 build-worker`.
3. Check host resources: disk (`df -h`), memory (`free -m`), inode exhaustion.
4. If the process is alive but heartbeats stopped: check network/DNS to the control plane, clock skew (`timedatectl`).
5. Restart the worker: `docker compose restart build-worker`.
6. Verify a heartbeat arrives and the worker is `online`.
7. Queued builds resume via lease expiry; no manual job edits needed.
8. If credentials are revoked/expired: re-register (operator) and update the secret store.

Stop condition: do not mark jobs failed manually; let the reaper requeue them.

### RB-02 — Deployment agent offline

1. Confirm worker status and recent leases.
2. Check `docker compose logs --tail=200 deploy-agent`.
3. Verify Docker socket access and `docker ps` works from the agent container.
4. Check for stuck containers: `docker ps -a --filter label=deployflow.managed=true`.
5. Restart the agent; confirm heartbeat.
6. Leases expire and requeue (max attempts respected). Deployments stuck without a job are reconciled by the reaper.
7. If an environment is in a bad state, the safe action is **rollback** to the active release, never a manual container
   start without recording a deployment.

### RB-03 — Queue backlog

1. `php artisan horizon:status`; check workers count.
2. Inspect: `php artisan horizon:list` / Redis queue lengths.
3. Look for a poison job: recent `failed_jobs` entries, repeated retries of the same job.
4. Scale queue/horizon processes if it is volume, not a stuck job.
5. If one job type is stuck, pause that queue and investigate; do not delete jobs blindly.
6. Confirm outbox relay is running (`outbox_messages` pending age).
7. After recovery, verify backlog drains and deployment states reconcile.

### RB-04 — Registry unavailable

1. Check registry health and credentials (`docker login`, `curl /v2/`).
2. Deployments in `pushing`/`pull` will retry with bounded backoff, then fail with `REGISTRY_UNAVAILABLE`.
3. Existing running containers are unaffected.
4. Do not disable retries or switch registries ad hoc; that requires an ADR.
5. If credentials are the cause: rotate, redeploy workers, retry affected deployments from the UI.

### RB-05 — Database connection failure

1. Check Postgres health, max connections, disk, and locks.
2. Confirm the app is not leaking connections (pool settings, long transactions).
3. Terminate only idle-in-transaction sessions older than the threshold.
4. If failover occurs, restart app/queue/horizon after confirming the primary is writable.
5. Verify: migrations table, recent deployment writes, outbox relay.
6. Deployments fail with `INTERNAL_ERROR`; users retry after recovery.

### RB-06 — Failed release (user application)

1. Open the deployment: read `failure_code`, step, and logs.
2. Confirm the previous release is still `active` (`releases`).
3. Fix forward (new commit) or use **Rollback** to the previous verified digest.
4. If the failure is platform-side (worker/registry), fix the platform first; retrying the same commit is safe.
5. Record the code in the incident if it is new or unexpectedly frequent.

### RB-07 — Failed rollback

1. The current active release must remain serving; verify with a request.
2. Read `ROLLBACK_FAILED` context: image pull, container start, health, or switch.
3. If the target digest is gone from the registry, choose another verified release.
4. If the agent is unhealthy, follow RB-02.
5. Never delete the active release row to "reset" state.

### RB-08 — Corrupted build workspace

1. Identify the worker and job; stop the worker (drain).
2. Remove stale workspaces under the worker's build root (path is logged, never user-controlled).
3. Check disk/inodes; corrupted caches are discarded, not repaired.
4. Restart the worker; retry the deployment.
5. If it recurs, disable the build cache and investigate the BuildKit version.

### RB-09 — Compromised credentials

1. Identify which credential: user session, worker secret, GitHub App key, webhook secret, registry, AWS, `APP_KEY`.
2. Revoke at the provider/source first.
3. Deploy rotated values; restart affected services.
4. For `APP_KEY`: rotate with key history, re-encrypt, invalidate sessions.
5. Audit access: `audit_logs`, worker `last_used_at`, registry pull logs, GitHub audit log.
6. Verify no unauthorized deployments or configuration changes.
7. Write an incident note, add a regression test/control.

### RB-10 — Backup and restore

Backup:
1. Postgres: daily full + WAL archiving; encrypted; retention 14 days.
2. Object storage: versioning on the log bucket; lifecycle per retention.
3. Secrets: backed up in the secret manager only, never in the database dump.
4. Registry images: retained by release policy; digests referenced by active releases must never be pruned.

Restore drill (quarterly):
1. Provision an isolated environment.
2. Restore the latest Postgres dump; verify `deployments`, `releases`, `worker_jobs` counts.
3. Restore object storage (or verify versioning access).
4. Start the app with restored secrets; confirm login, deployment history, and log access.
5. Record elapsed time and gaps. Target RTO 4 h, RPO 24 h for v1.

---

## 4. Routine operations

| Cadence | Task |
|---|---|
| Daily | Check failed jobs, queue age, worker heartbeats, disk |
| Weekly | Review audit log anomalies, dependency alerts, certificate expiry |
| Monthly | Rotate logs per retention, review access, restore-test log sampling |
| Quarterly | Full restore drill, credential rotation review, runbook review |
| Per release | `DEPLOYMENT.md` §10 checklist |

---

## 5. Debugging playbook

1. Start with the deployment ID; collect `status`, `failure_code`, steps, and last 200 log lines.
2. Check `worker_jobs` for attempts, lease expiry, and worker id.
3. Check `deployment_events` for the exact transition order.
4. Correlate with application logs by `request_id` / `deployment_id`.
5. Confirm the active release and its digest before changing anything.
6. Reproduce in staging with the same commit; never debug by mutating production state directly.

---

## 6. Data retention

| Data | Retention | Mechanism |
|---|---|---|
| Deployment logs | 30 days (configurable) | `LOG_RETENTION_DAYS` job deletes objects + rows |
| Audit logs | 1 year | no automatic delete in v1; export before pruning |
| Failed jobs | 30 days | `queue:prune-failed` |
| Webhook payloads | 30 days | scheduled prune; keep `delivery_id` + status |
| Deployments/releases | indefinite | product history |
| Backups | 14 days | provider lifecycle |

Retention jobs must never delete an object referenced by an active release or a non-terminal deployment.

---

## 7. Emergency controls

Available to platform admins, all audited:

- **Disable new deployments** globally (maintenance flag) — in-flight deployments continue.
- **Drain a worker** — stop new claims, finish current lease.
- **Revoke a worker** — immediate auth failure; leases expire.
- **Disable an environment** — blocks new deployments for that environment only.
- **Freeze an application** — blocks deploys, keeps the active release serving.

There is no "delete everything" control, by design.
