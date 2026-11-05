# Worker Contracts

Normative interface between the control plane and the two execution services.

- Build worker: `workers/build-worker`
- Deployment agent: `workers/deployment-agent`
- Transport: HTTPS, JSON, contract version `1`.
- Source of truth for types: JSON Schemas in `workers/contracts/` (created in DF-015). This document explains them.

Changes are **additive only** within version 1. Removing/renaming a field requires `contract_version: 2` and a
migration plan (I-14).

---

## 1. Trust model

- Workers are registered, individually credentialed, and never trust each other.
- A worker may only act on a job it holds a live lease for (I-12).
- The control plane is authoritative for deployment state. Workers report facts; they never write status directly.
- Job payloads contain **no runtime secret values**; deploy agents fetch them per job (§7).
- Workers must not accept commands from users. Their only input is the job payload.

---

## 2. Registration

```http
POST /internal/workers/register
X-Bootstrap-Token: <operator-issued token>
Content-Type: application/json

{
  "name": "build-01",
  "type": "build",
  "version": "0.1.0",
  "capabilities": {"buildkit": "rootless", "platforms": ["linux/amd64"]},
  "labels": {"host": "df-host-1"},
  "max_concurrency": 1
}
```

Response `201`:

```json
{
  "data": {
    "worker_id": "0192f0c4-...",
    "worker_secret": "dfw_3f9c...",
    "expires_at": "2027-02-01T00:00:00Z"
  },
  "meta": {"request_id": "..."}
}
```

Rules:

- `name` unique; duplicate → `409`.
- `type` is immutable after registration.
- `worker_secret` is returned **once**, stored encrypted server-side, and never retrievable again.
- Bootstrap token comes from `WORKER_BOOTSTRAP_TOKEN` (env/secret store), rotatable, rate-limited 5/min/IP.
- Re-registration with the same name requires operator revocation of the old credential.

---

## 3. Request signing (every authenticated worker call)

Headers:

```text
X-Worker-Id: 0192f0c4-...
X-Worker-Timestamp: 1762345678          # unix seconds, UTC
X-Worker-Nonce: 4b1c2f7e-...            # uuid v4, unique per request
X-Worker-Signature: <hex hmac-sha256>
```

Canonical string (UTF-8, `\n` separators, no trailing newline):

```text
METHOD
PATH
TIMESTAMP
NONCE
SHA256_HEX(RAW_BODY)
```

- `PATH` is the request path only (no query string; internal endpoints use none).
- `RAW_BODY` is the exact bytes sent; empty body hashes as `e3b0c442...b855`.
- `signature = hex(HMAC_SHA256(worker_secret, canonical))`.
- Server checks: credential exists and not revoked/expired → skew ≤ 300 s → nonce unseen (Redis `SET NX EX 600`) →
  `hash_equals` compare → worker `status != offline` → lease ownership when applicable.
- Every failure returns `401 WORKER_UNAUTHORIZED` with no detail about which check failed.
- Clocks must be NTP-synced. A worker with persistent skew fails registration health checks.

Nonce cache is per worker; a replayed request is rejected even if the signature is valid.

---

## 4. Claim

```http
POST /internal/jobs/claim
{"types": ["build", "deploy", "rollback"], "capacity": 1}
```

- Server-side: transaction + `SELECT ... FOR UPDATE SKIP LOCKED` on `worker_jobs`
  (`status='queued'`, `available_at <= now()`, type in `types`), oldest first.
- Marks `leased`, sets `worker_id`, `lease_token`, `lease_expires_at = now() + 90s`, `attempts += 1`.
- Returns `200` with the full job payload, or `204` with no body when nothing is claimable.
- Never returns a job of a type the worker did not declare.

```json
{
  "data": {
    "job_id": "0192f1...",
    "job_type": "build",
    "contract_version": 1,
    "lease_token": "0192f1aa-...",
    "lease_expires_at": "2026-11-05T12:01:30Z",
    "deployment_id": "0192f1ab-...",
    "organization_id": "0192e0...",
    "attempt": 1,
    "max_attempts": 3,
    ...job-specific fields...
  },
  "meta": {"request_id": "..."}
}
```

---

## 5. Build job payload

```json
{
  "job_id": "0192f1...",
  "job_type": "build",
  "contract_version": 1,
  "deployment_id": "0192f1ab-...",
  "organization_id": "0192e0...",
  "source": {
    "provider": "github",
    "installation_id": 12345678,
    "repository": "acme/api",
    "commit_sha": "0123456789abcdef0123456789abcdef01234567",
    "ref": "refs/heads/main"
  },
  "build": {
    "dockerfile_path": "Dockerfile",
    "context": ".",
    "target": null,
    "platforms": ["linux/amd64"],
    "build_args": {"NODE_ENV": "production"},
    "cache": {"enabled": true, "scope": "app-0192e0"}
  },
  "image": {
    "registry": "registry.internal:5000",
    "repository": "acme/api",
    "tag": "df-0192f1ab-0123456"
  },
  "limits": {
    "cpu": 2,
    "memory_mb": 2048,
    "disk_mb": 10240,
    "timeout_seconds": 1200
  },
  "callback": {"base_url": "https://deployflow.internal"}
}
```

Constraints:

- `build_args` never contains values derived from `environment_variables`; the control plane rejects them at
  configuration time and again before dispatch.
- The worker must check out `commit_sha` exactly; a branch name is metadata only.
- The image is pushed by tag, then the worker **reads the digest from the registry** and reports it. Tags are never
  reported as the deployable artifact (I-5).
- `cache` is best-effort; a cache miss must not fail the build.

### Completion

```http
POST /internal/jobs/{job}/complete

{
  "lease_token": "0192f1aa-...",
  "image_uri": "registry.internal:5000/acme/api@sha256:<64hex>",
  "image_digest": "sha256:<64hex>",
  "steps": [{"name": "clone", "status": "succeeded"}, {"name": "build", "status": "succeeded"}, {"name": "push", "status": "succeeded"}]
}
```

Server verifies: digest format, registry host allowlist, digest belongs to the pushed manifest (worker also reports
`manifest_sha256` optionally). Mismatch → `DIGEST_MISMATCH`, deployment fails.

---

## 6. Deploy and rollback job payload

```json
{
  "job_id": "0192f2...",
  "job_type": "deploy",
  "contract_version": 1,
  "deployment_id": "0192f2ab-...",
  "organization_id": "0192e0...",
  "environment": {
    "id": "0192e1...",
    "name": "staging",
    "public_host": "api.staging.deployflow.test"
  },
  "image": {
    "uri": "registry.internal:5000/acme/api@sha256:<64hex>",
    "digest": "sha256:<64hex>"
  },
  "runtime": {
    "container_port": 8080,
    "env_keys": ["APP_ENV", "DATABASE_URL", "REDIS_URL"],
    "resources": {"cpu": 1.0, "memory_mb": 512, "pids": 256},
    "read_only_rootfs": false,
    "user": "10001:10001"
  },
  "health": {
    "path": "/health",
    "port": 8080,
    "interval_seconds": 5,
    "timeout_seconds": 2,
    "retries": 12,
    "startup_grace_seconds": 10,
    "success_statuses": [200, 204]
  },
  "strategy": {
    "type": "blue_green",
    "slots": ["blue", "green"],
    "previous_release": {
      "release_id": "0192e9...",
      "slot": "blue",
      "image_uri": "registry.internal:5000/acme/api@sha256:<64hex>"
    }
  },
  "callback": {"base_url": "https://deployflow.internal"}
}
```

`job_type=rollback` is identical except it carries `rollback_of_deployment_id` and the image comes from the target
release. There is no build, push, or clone step.

---

## 7. Runtime configuration fetch (deploy only)

```http
GET /internal/jobs/{job}/config
X-Worker-*: ...
```

```json
{
  "data": {
    "env": {"APP_ENV": "staging", "DATABASE_URL": "postgres://..."},
    "health": {...},
    "container_port": 8080
  },
  "meta": {"request_id": "..."}
}
```

- Only for a deploy/rollback job with a live lease, and only for the worker holding it.
- Response is `Cache-Control: no-store`; the control plane never logs the body.
- Each access writes `audit_logs.action = worker.config_access` (keys only, never values).
- The worker keeps values in memory, passes them to the container runtime via an env file created with `0600` in a
  `tmpfs`, and deletes it immediately after `docker create`.
- The worker must never write values to its own logs, the job log stream, or disk outside the ephemeral file.

---

## 8. Step reporting

```http
POST /internal/jobs/{job}/step
{"lease_token":"...", "step":"build", "status":"running", "attempt":1, "error_code":null, "metadata":{"digest_progress":0.4}}
```

Mapping worker step names → deployment steps:

| Worker `step` | Deployment step | Allowed next |
|---|---|---|
| `clone` | `clone` | — |
| `build` | `build` | — |
| `push` | `push` | — |
| `pull` | `pull` | — |
| `deploy` | `deploy` | — |
| `health_check` | `health_check` | — |
| `activate` | `activate` | — |

The server validates order and rejects out-of-order reports with `409 STATE_CONFLICT`. `metadata` must not contain
secrets or host paths.

---

## 9. Logs

```http
POST /internal/jobs/{job}/logs
{
  "lease_token": "...",
  "sequence": 42,
  "step": "build",
  "stream": "stdout",
  "content": "Step 1/8 : FROM node:22-alpine\n",
  "truncated": false
}
```

Rules:

- `content` ≤ 64 KiB; split larger output.
- `sequence` is monotonic per deployment. Duplicate `(sequence, sha256(content))` is a no-op `200`; a conflicting
  payload with the same sequence is `409 LOG_SEQUENCE_CONFLICT`.
- Worker redacts known secret values before sending (it may hold them only in memory during a deploy job; during a
  build it holds none).
- Control plane redacts again (defense in depth) then writes to object storage:
  `org/{organization_id}/deployments/{deployment_id}/{step}/{sequence}.log`.
- Only the chunk index is stored in PostgreSQL (`deployment_logs`).
- Log content is never used to derive state.
- A worker must not log request headers, signatures, env values, or repository credentials.

---

## 10. Heartbeat, cancellation, failure

```http
POST /internal/jobs/{job}/heartbeat
{"lease_token": "...", "progress": {"step": "build", "percent": 30}}
```

Response:

```json
{"data":{"lease_expires_at":"...","cancel_requested":false,"cancel_deadline":null},"meta":{"request_id":"..."}}
```

- Worker renews every 30 s; lease is 90 s.
- `cancel_requested=true` → stop at the next safe point, report `fail` with `CANCELLED` (idempotent).
- `409 LEASE_LOST` → stop immediately and discard results; do not call `complete`.

```http
POST /internal/jobs/{job}/fail
{"lease_token":"...", "code":"HEALTH_CHECK_FAILED", "message":"Health check returned 502 for 12 attempts.", "step":"health_check", "retriable":false}
```

The code must exist in `docs/ERROR-CODES.md` and be valid for the step. Unknown codes are replaced with
`INTERNAL_ERROR` and logged.

---

## 11. Timeouts, retries, cancellation

| Operation | Timeout | Auto-retry | Notes |
|---|---:|---|---|
| Clone | 300 s | 2 | exponential 5/15 s |
| Build | 1200 s (max 3600) | 0 | user retries |
| Push | 600 s | 3 | backoff 5/15/45 s |
| Pull | 600 s | 3 | backoff 5/15/45 s |
| Container start | 120 s | 0 | |
| Health check | grace 10 s + `retries × interval` (default 70 s) | 0 | retries are probes, not job retries |
| Traffic switch | 60 s | 2 | verify route before declaring success |
| Whole job | 3600 s | — | hard kill, `WORKER_LOST` after lease expiry |
| Cancellation | 120 s to acknowledge | — | then `cancelled` best-effort; container cleanup by reaper |

Worker rules on shutdown (SIGTERM): stop claiming, finish or fail the current job within the cancellation deadline,
destroy ephemeral workspaces, release the lease.

---

## 12. Build worker execution rules

- Ephemeral workspace per job: `mktemp -d` under an isolated volume; deleted in a `finally` block.
- Rootless BuildKit; never the host Docker socket; never privileged.
- CPU, memory, and disk limits from the payload; kill the process group on timeout.
- Egress limited to the Git host, the registry, and the package mirrors explicitly configured. Default deny.
- No runtime secrets in the build environment. Build args allowlisted.
- No shell interpolation of user/DB values; arguments passed as arrays.
- The build never runs on the control-plane host or in the app container (I-1).

---

## 13. Deployment agent execution rules

Container creation (reference):

```text
docker create --name df-<env-short>-<slot> \
  --label deployflow.managed=true \
  --label deployflow.organization=<uuid> \
  --label deployflow.environment=<uuid> \
  --label deployflow.release=<uuid> \
  --label deployflow.slot=<blue|green> \
  --network df-env-<env-short> \
  --restart unless-stopped \
  --memory <m> --cpus <c> --pids-limit <p> \
  --cap-drop ALL --security-opt no-new-privileges \
  --user <uid:gid> \
  --env-file <tmpfs file, 0600> \
  <image>@<digest>
```

- Pull by digest only. Reject tags and registries outside the allowlist.
- Never mount the host filesystem, the Docker socket, or host devices.
- No published ports; Traefik reaches the container over the environment network.
- The agent may only create/stop/inspect containers and networks labelled `deployflow.managed=true` for environments
  it was assigned. Any other target is a hard error.
- On failure, always remove the green container and its env file; never touch the active slot.

Traffic switching (Traefik file provider):

- Dynamic config per environment: `infra/traefik/dynamic/<environment_id>.yml`, referencing the active slot's service.
- Write to a temp file, `fsync`, then atomic `rename`; Traefik watches the directory.
- Switch, poll the public host for the expected release header/body, and only then report `activate` success.
- If verification fails, atomically restore the previous router config and report `ROUTE_VERIFY_FAILED`.

Container names, labels, and network names are contract: the agent must not invent a different scheme.

---

## 14. Contract tests

CI validates both implementations against `workers/contracts/*.schema.json`:

1. Control plane serializes each job payload and validates it.
2. Worker parses the fixture and validates it.
3. Adding a required field fails until both sides are updated.
4. Round-trip tests cover digest format, step order, and error codes.

Any change to this document without a schema/contract test change is an incomplete PR (I-14).
