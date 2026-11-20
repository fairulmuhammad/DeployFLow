# Security Architecture

Security is the product. A deployment platform that leaks a secret or crosses a tenant boundary has failed regardless of
uptime. This document is normative; deviations require an ADR and a review.

---

## 1. Assets

1. Source code and repository access tokens.
2. Runtime environment variables (secrets).
3. GitHub App private key and webhook secret.
4. Registry credentials and worker secrets.
5. Deployment history, logs, and audit records.
6. Availability and integrity of the applications being deployed.
7. The host and the container runtime.

---

## 2. Threat model

| Threat | Vector | Control |
|---|---|---|
| Cross-tenant data access | ID swapping, missing scope | tenant context + policies + composite FKs + tests |
| Secret exfiltration | API, logs, build args, image layers | write-only values, encryption, redaction, no secrets in builds |
| Malicious repository code | Dockerfile / build scripts | isolated rootless builder, egress limits, no host mounts, trusted-repo policy |
| Compromised worker | forged callbacks | per-worker HMAC, nonce, lease ownership, scoped runtime access |
| Webhook forgery/replay | fake push events | signature over raw body, delivery dedupe, installation validation |
| Replay of worker requests | captured request | timestamp window + nonce cache |
| SSRF | user-supplied URL checks | no arbitrary URLs in v1; path/port only against the container |
| Privilege escalation in container | `--privileged`, caps, host paths | cap-drop, no-new-privileges, non-root, no socket, labels-only runtime scope |
| Supply chain | dependency or action compromise | lockfiles, pinned actions, audit, Trivy, SBOM |
| Denial of service | auth floods, giant webhooks | rate limits, body caps, timeouts, queue isolation |
| Audit repudiation | missing actor | append-only audit with actor + request id |

Out of scope for v1: malicious platform administrators, host kernel exploits, physical access, DDoS at the network edge,
and running untrusted third-party repositories for the public (blueprint §6.5).

---

## 3. Tenant isolation

Defense in depth, all required:

1. **Route binding** resolves models through a scope constrained by the active organization; a foreign ID produces `404`.
2. **TenantContext** is bound by `ResolveOrganization` middleware; `BelongsToOrganization` global scope requires it and
   throws when missing.
3. **Policies** check membership and role for every controller action.
4. **Composite FKs** make cross-tenant parent references impossible in the database.
5. **Tests**: every tenant-owned resource has a cross-tenant denial test (`tests/Security/TenantIsolationTest.php`).

System contexts (webhook processing, queue jobs, reaper, console commands) must call `TenantContext::for($orgId)`
explicitly. The only allowed escapes from the global scope are:

| Location | Reason |
|---|---|
| `app/Console/Commands/Ops/*` | platform maintenance |
| migrations and seeders | schema/data setup |
| `ReaperService` | scans across tenants by design; never returns data to a request |
| `AuditExporter` | platform admin export |

Any other `withoutGlobalScope`/`withoutTenantScope` is a review blocker.

Authorization matrix is defined in `docs/PRD.md` §3. Deny by default; a policy method that is not implemented denies.

---

## 4. Authentication

- Laravel session auth; session driver Redis; cookies `Secure`, `HttpOnly`, `SameSite=Lax`.
- Email verification required for all dashboard routes except verification screens (`verified` middleware).
- Passwords: minimum 12 characters; uncompromised check via `Password::defaults()` in production (network-bound, so
  local/test runs skip the breach lookup); Argon2id if available.
- Login throttled 5/min per email+IP; registration 6/min/IP; reset 3/min/email.
- Password reset tokens single-use, hashed at rest, 60-minute expiry.
- Logout invalidates the session and regenerates the CSRF token.
- Session fixation prevented by `session()->regenerate()` on login.
- No "remember me" in v1.
- 2FA, SSO, and public API tokens are post-v1.
- Every credential (user session, worker secret, GitHub installation token, registry credential) is revocable and
  revoked on membership removal / installation disconnect.

---

## 5. Secrets

### Storage

| Secret | Storage |
|---|---|
| `APP_KEY` (encryption) | environment / secret manager, never DB |
| GitHub App private key | environment / mounted secret file |
| GitHub webhook secret | environment |
| Registry credentials | environment / secret manager |
| Worker secrets | `worker_credentials.secret_encrypted` (Laravel encrypted) |
| Environment variables | `environment_variables.encrypted_value` (Laravel encrypted) |
| Session/queue data | Redis, no secrets beyond session payload |

### Rules (I-4)

1. Secret values are write-only through every interface. No endpoint returns them, including admin paths.
2. No secret values in logs, exception traces, audit metadata, job payloads, or validation messages.
3. Secrets never become build args, image layers, or `ARG`/`ENV` in a Dockerfile.
4. Runtime secrets are delivered only to the deploy agent, per job, with a live lease, and are audited by key name.
5. Redaction is applied twice: worker before upload, control plane before storage.
6. Redaction matches exact values from the current environment plus patterns: `ghp_`, `gho_`, `github_pat_`,
   `Bearer `, `-----BEGIN * PRIVATE KEY-----`, `AKIA[0-9A-Z]{16}`, and long base64/hex strings in known positions.
7. Key rotation: `APP_KEY` rotation uses Laravel's key history; re-encrypt via an ops command; document the runbook.
8. Removing a variable takes effect on the next deployment; running containers keep their env until replaced.

### Redaction test requirement

Every path that handles a secret must have a test asserting the value is absent from: response body, log records
(`Log::spy`), the job payload, audit metadata, and the stored object (for logs).

---

## 6. GitHub integration

- GitHub App, not personal access tokens. Installation tokens are short-lived, cached per installation, and never
  exposed to the browser.
- Callback validates the `state` parameter (signed, single-use, bound to the user session) before storing anything.
- Webhook: HMAC-SHA256 over the **raw** body with `hash_equals`; reject > 2 MiB; dedupe by `X-GitHub-Delivery`;
  persist before processing; process asynchronously.
- Validate that `installation.id` and `repository.id` belong to the organization's installation; never trust names.
- `installation` / `installation_repositories` events update or revoke access; a revoked installation immediately
  blocks new deployments (`REPOSITORY_UNAVAILABLE`) and revokes cached tokens.
- Do not log webhook bodies or headers containing signatures.

Reference: <https://docs.github.com/en/webhooks/using-webhooks/validating-webhook-deliveries>

---

## 7. Worker authentication

- Each worker gets a unique 32-byte secret at registration; returned once; stored encrypted because HMAC verification
  needs the original value.
- Request signing as specified in `WORKER-CONTRACTS.md` §3: timestamp + nonce + body hash, 5-minute skew, nonce cache.
- Secrets expire (default 180 days) and are revocable; revocation takes effect immediately.
- Transport is HTTPS with a trusted certificate. mTLS is a documented upgrade path (ADR-020), not v1.
- Failed auth is rate-limited per IP and per worker id; repeated failures alert.
- A worker can only see jobs it claimed. `job_id` alone is never proof of ownership; `lease_token` is required.
- A compromised worker can affect only environments assigned to it; it cannot read other organizations' data.

---

## 8. Build isolation (highest-risk boundary)

- The control-plane container has **no** Docker socket, no build tools, and no repository checkout (I-1).
- Builds run in a dedicated worker with rootless BuildKit. No privileged containers, no host mounts other than the
  ephemeral workspace, no `--network host`.
- CPU, memory, disk, and PID limits from the job payload; hard timeout; process-group kill.
- Egress default-deny, allowlist Git host, registry, and configured package mirrors.
- Workspace created per job and destroyed in `finally`; a startup sweep removes stale workspaces.
- No production runtime secrets in the build environment; build args allowlisted and validated.
- Build logs are redacted before leaving the worker.
- Scope restriction (blueprint §6.5): v1 accepts repositories the operator owns or explicitly authorizes. Public
  multi-tenant builds require a hardened, separately reviewed builder.

---

## 9. Deployment agent

- Narrow runtime access: containers/networks carrying `deployflow.managed=true` for assigned environments only.
- Pull by digest; registry allowlist; no tags in the deploy path.
- Container hardening: `--cap-drop ALL`, `no-new-privileges`, non-root user, pids/memory/cpu limits, no host network,
  no host paths, no devices, read-only root filesystem when the app permits.
- Env file written to `tmpfs` with `0600`, deleted immediately after `docker create`.
- No arbitrary shell/exec endpoint. The agent never runs commands supplied by users.
- The agent verifies the route after switching and restores the previous config on failure.
- Container logs are streamed with a bounded buffer; the agent does not persist them outside the log endpoint.

---

## 10. SSRF stance

v1 health checks are **not** arbitrary URLs. The agent probes `http://<container-ip>:<port><path>` inside the
environment network, where `path` is validated (`/`, no scheme, no host, no `..`) and `port` is an integer 1–65535.

If URL-based checks are added later (post-v1), the following are mandatory before merge:

- resolve DNS and reject loopback, private, link-local, multicast, and metadata addresses;
- revalidate on every redirect (and cap redirects);
- block non-HTTP(S) schemes and unapproved ports;
- pin the resolved IP for the request to prevent DNS rebinding;
- test all of the above in `tests/Security/SsrfTest.php`.

The control plane never fetches user-supplied URLs in v1.

---

## 11. Browser and transport

- HTTPS enforced (`URL::forceScheme('https')` in production); HSTS.
- CSP: `default-src 'self'`; scripts only from self and the Vite dev origin in local; no inline scripts except the
  Inertia nonce; `frame-ancestors 'none'`.
- `X-Content-Type-Options: nosniff`, `Referrer-Policy: strict-origin-when-cross-origin`,
  `Permissions-Policy` minimal.
- CORS: disabled for browser routes (same origin); internal endpoints are server-to-server only.
- CSRF: enabled everywhere except `/webhooks/github`; Laravel 13 `PreventRequestForgery` origin checks remain on.
- File uploads: none in v1 (no avatars, no artifacts from users).
- All user-provided text is escaped by React by default; `dangerouslySetInnerHTML` is banned.
- Log/content viewers render text, never HTML.

---

## 12. Database and infrastructure

- Separate DB roles: `deployflow_app` (DML), `deployflow_migrate` (DDL), read-only ops role.
- No superuser for the app; `search_path` pinned to `public`.
- Redis: password, no public port, separate logical DBs for queue/cache/locks/nonces.
- Object storage: private buckets; access only through short-lived signed URLs (5 min) scoped to one object.
- Backups encrypted at rest; restore tested quarterly (`OPERATIONS.md`).
- Secrets injected at runtime, never baked into images; image builds use build args only for non-secret configuration.
- CI actions pinned to full commit SHAs; `permissions:` least privilege; no `pull_request_target` with secrets.

---

## 13. Supply chain

- `composer.lock` and `package-lock.json` committed; `npm ci`, `composer install --no-dev --prefer-dist` in images.
- `composer audit` and `npm audit --omit=dev` in CI; high/critical blocks merge unless an accepted-risk ADR exists.
- Trivy scans the control-plane image and the worker images; SBOM generated per release.
- Third-party GitHub Actions pinned by SHA (blueprint §8.3 note).
- PHPStan level 8 and TypeScript strict are security-relevant gates, not style preferences.

---

## 14. Audit

Recorded for at least: login success/failure (metadata only), organization/member changes, installation connect/revoke,
application/environment changes, variable writes (keys only), deployment create/cancel/rollback, worker registration and
revocation, secret config access by a worker, and admin exports.

Each record: `organization_id`, `actor_id` + `actor_type`, `action`, `resource_type` + `resource_id`, `ip_address`,
`user_agent`, sanitized `metadata`, `created_at`. Append-only; no update/delete path in the application.

---

## 15. Incident procedures

| Event | Immediate action |
|---|---|
| Secret exposure suspected | rotate `APP_KEY` history, rotate GitHub/registry/worker credentials, revoke sessions, audit access, notify |
| Worker compromised | revoke credential, drain, kill its jobs, inspect `worker_jobs` and audit |
| Cross-tenant access suspected | freeze deploys, preserve logs, audit `TenantContext` escapes, patch + regression test |
| Malicious repository | disable application, revoke installation, rotate registry creds, rebuild trusted images |
| Host compromise | isolate host, rotate all host-mounted secrets, rebuild from images |

Every incident ends with a regression test and a doc update. See `docs/OPERATIONS.md`.

---

## 16. Invariant → test map

| Invariant | Test |
|---|---|
| I-1 no docker socket in app | `Security\ContainerSurfaceTest` (compose file assertion) + review |
| I-2 tenant isolation | `Security\TenantIsolationTest` (all resources) |
| I-3 policies | `Feature\AuthorizationMatrixTest` |
| I-4 secrets | `Security\SecretExposureTest`, `Security\RedactionTest` |
| I-5 immutable artifacts | `Unit\ImageReferenceTest`, `Feature\DeploymentImageTest` |
| I-6 state machine | `Unit\DeploymentStateMachineTest` (all transitions) |
| I-7 outbox | `Feature\OutboxTest` (transaction rollback leaves no job) |
| I-8 idempotency | `Feature\DeploymentIdempotencyTest` |
| I-9 health gate | `Security\HealthGateTest`, `FailureInjection\HealthCheckTest` |
| I-10 release retention | `Feature\ReleaseRetentionTest` |
| I-11 webhook | `Feature\WebhookSignatureTest`, `Feature\WebhookDeduplicationTest` |
| I-12 lease | `Feature\JobLeaseTest`, `Feature\LeaseReplayTest` |
| I-13 migrations | CI `migrate` + `migrate:rollback` |
| I-14 docs | PR checklist + contract tests |
