# API and Routes

**Transport decision:** the dashboard is Inertia (server-rendered pages, JSON props). There is no public REST API in v1
(ADR-012). JSON endpoints exist only for GitHub webhooks and internal worker traffic. Internal shapes are specified in
`docs/WORKER-CONTRACTS.md`.

---

## 1. Conventions

| Item | Convention |
|---|---|
| Base | web routes at `/`, internal at `/internal`, webhook at `/webhooks/github` |
| Auth (browser) | Laravel session, `auth` + `verified` middleware |
| Auth (internal) | per-worker HMAC signature (`SECURITY.md` §Worker authentication) |
| Auth (webhook) | `X-Hub-Signature-256` HMAC over the raw body |
| CSRF | required for browser mutations; **exempt** only `/webhooks/github` |
| Identifiers | UUIDv7 in URLs and props; slugs only for organization scoping |
| Response envelope (JSON errors) | `{"error":{"code","message","details?"},"meta":{"request_id"}}` |
| Success envelope (JSON actions) | `{"data":{...},"meta":{"request_id"}}` |
| Inertia success | 200 page render or 303 redirect after a mutation |
| Pagination | cursor for logs/events, page-based for lists (`per_page` max 100) |
| Time | ISO-8601 UTC (`2026-11-05T12:00:00Z`) |
| Rate limits | see §6 |

Every request gets `X-Request-Id` (accepted from the client if it is a UUID, otherwise generated) and the same value is
returned and logged.

---

## 2. Authentication and account routes

| Method | Path | Name | Notes |
|---|---|---|---|
| GET/POST | `/register` | `register` | Throttle 6/min/IP |
| GET/POST | `/login` | `login` | Throttle 5/min/email+IP |
| POST | `/logout` | `logout` | Session invalidate |
| GET/POST | `/forgot-password` | `password.email` | Always returns generic success |
| GET/POST | `/reset-password/{token}` | `password.store` | Token single-use |
| GET | `/verify-email` | `verification.notice` | |
| GET | `/verify-email/{id}/{hash}` | `verification.verify` | Signed URL |
| POST | `/email/verification-notification` | `verification.send` | Throttle 1/min |
| GET | `/settings/profile` | `profile.edit` | Name, email, password |
| GET | `/up` | `health` | Liveness only, no auth, no data |

---

## 3. Organizations and members

All routes are scoped by `{organization:slug}` and require membership.

| Method | Path | Name | Permission |
|---|---|---|---|
| GET | `/organizations` | `organizations.index` | any member |
| POST | `/organizations` | `organizations.store` | any verified user |
| GET | `/organizations/{organization:slug}` | `organizations.show` | viewer+ |
| PATCH | `/organizations/{organization:slug}` | `organizations.update` | owner/admin |
| DELETE | `/organizations/{organization:slug}` | `organizations.destroy` | owner |
| GET | `/organizations/{organization:slug}/members` | `members.index` | viewer+ |
| POST | `/organizations/{organization:slug}/invitations` | `invitations.store` | owner/admin |
| DELETE | `/organizations/{organization:slug}/invitations/{invitation}` | `invitations.destroy` | owner/admin |
| GET | `/invitations/{token}` | `invitations.accept` | authenticated, email must match |
| PATCH | `/organizations/{organization:slug}/members/{user}` | `members.update` | owner/admin |
| DELETE | `/organizations/{organization:slug}/members/{user}` | `members.destroy` | owner/admin |
| POST | `/organizations/{organization:slug}/transfer` | `organizations.transfer` | owner |

Rules: admins cannot modify/remove owners; nobody can remove the last owner; role changes and removals are audited.

---

## 4. GitHub integration

| Method | Path | Name | Notes |
|---|---|---|---|
| GET | `/organizations/{organization:slug}/integrations/github/install` | `integrations.github.install` | Redirects to GitHub App installation |
| GET | `/integrations/github/callback` | `integrations.github.callback` | Validates `state`, stores installation, syncs repos |
| GET | `/organizations/{organization:slug}/repositories` | `repositories.index` | Lists installation repositories |
| POST | `/organizations/{organization:slug}/repositories/{repository}/sync` | `repositories.sync` | Re-fetch metadata |
| DELETE | `/organizations/{organization:slug}/integrations/github/{installation}` | `integrations.github.destroy` | Revokes local access; audit |
| POST | `/webhooks/github` | `webhooks.github` | Public, CSRF-exempt, signature-verified |

### Webhook behavior

1. Reject bodies > 2 MiB (`413`).
2. Verify `X-Hub-Signature-256` with `hash_equals` against the raw body → `401 SIGNATURE_INVALID`.
3. Read `X-GitHub-Event` and `X-GitHub-Delivery`; reject unknown event types with `202` and no processing.
4. Insert `github_webhook_events` (unique `delivery_id`). Duplicate → `202`, stored as `ignored`.
5. Acknowledge `202` **before** any processing.
6. Dispatch `ProcessGithubWebhook` through the outbox.

Supported events in v1: `push`, `installation`, `installation_repositories`, `repository` (archived/renamed/transferred).
`pull_request` is ignored by design.

---

## 5. Applications, environments, variables, deployments

| Method | Path | Name | Permission |
|---|---|---|---|
| GET | `/organizations/{organization:slug}/applications` | `applications.index` | viewer+ |
| POST | `/organizations/{organization:slug}/applications` | `applications.store` | developer+ |
| GET | `/applications/{application}` | `applications.show` | viewer+ |
| PATCH | `/applications/{application}` | `applications.update` | developer+ |
| DELETE | `/applications/{application}` | `applications.archive` | admin+ |
| POST | `/applications/{application}/restore` | `applications.restore` | admin+ |
| GET | `/applications/{application}/environments` | `environments.index` | viewer+ |
| POST | `/applications/{application}/environments` | `environments.store` | developer+ |
| GET | `/environments/{environment}` | `environments.show` | viewer+ |
| PATCH | `/environments/{environment}` | `environments.update` | developer+ |
| GET | `/environments/{environment}/variables` | `variables.index` | developer+ (names only) |
| PUT | `/environments/{environment}/variables` | `variables.upsert` | developer+ |
| DELETE | `/environments/{environment}/variables/{key}` | `variables.destroy` | developer+ |
| GET | `/applications/{application}/deployments` | `deployments.index` | viewer+ |
| POST | `/environments/{environment}/deployments` | `deployments.store` | developer+ |
| GET | `/deployments/{deployment}` | `deployments.show` | viewer+ |
| POST | `/deployments/{deployment}/cancel` | `deployments.cancel` | developer+ |
| POST | `/deployments/{deployment}/rollback` | `deployments.rollback` | developer+ |
| GET | `/deployments/{deployment}/logs` | `deployments.logs` | viewer+ |
| GET | `/deployments/{deployment}/events` | `deployments.events` | viewer+ |

Route-model binding is tenant-aware: a model outside the active organization yields `404`, never `403`.

### Trigger a deployment

```http
POST /environments/{environment}/deployments
Idempotency-Key: 7f3c0b9e-...
Content-Type: application/json

{"commit_sha":"0123456789abcdef0123456789abcdef01234567","branch":"main"}
```

- `commit_sha` optional; defaults to the environment branch head resolved at creation time and persisted.
- Returns `202` with the deployment id and status `queued`. Never waits for a build.
- Replay with the same key + same body returns `202` and the existing deployment (`Idempotency-Replayed: true`).
- Replay with the same key + different body returns `409 IDEMPOTENCY_KEY_REUSED`.
- An active deployment in the environment returns `409 DEPLOYMENT_ALREADY_RUNNING`.

```json
{"data":{"id":"0192f...","status":"queued","commit_sha":"0123...","environment_id":"0192e..."},"meta":{"request_id":"..."}}
```

### Cancel

`POST /deployments/{deployment}/cancel` → `202` with `{status, cancellation_requested_at}`. See
`STATE-MACHINES.md` §Cancellation; post-activation returns `409 CANNOT_CANCEL_AFTER_ACTIVATION`.

### Rollback

`POST /deployments/{deployment}/rollback` with an optional `{"release_id": "..."}` (default: the most recent release
before this deployment in the same environment).

- Creates a **new** deployment, `trigger_type=rollback`, `rollback_of_deployment_id` set.
- Returns `202`; never rebuilds; reuses the release digest.
- Target must be `inactive` or `active`, belong to the same environment, and have a stored digest.

### Logs

`GET /deployments/{deployment}/logs?step=build&cursor=<sequence>&limit=200`

```json
{"data":{"chunks":[{"sequence":42,"step":"build","size_bytes":8192,"url":"https://.../signed?sig=..."}],"next_cursor":43},"meta":{"request_id":"..."}}
```

- Signed object-storage URLs expire in 5 minutes.
- `403` if the deployment is outside the tenant; `404` if it has no logs.
- v1 is cursor polling; SSE arrives in M6 (`text/event-stream`, `Last-Event-ID` = sequence).

### Variables

`GET` returns names and `is_secret` only. The value is never serialized, including in validation error messages,
audit metadata, and Inertia props.

```json
{"data":[{"key":"DATABASE_URL","is_secret":true,"updated_at":"..."}],"meta":{"request_id":"..."}}
```

`PUT` accepts `{"variables":[{"key":"APP_ENV","value":"staging","is_secret":false}]}` and is transactional per request.

---

## 6. Rate limits

| Scope | Limit |
|---|---|
| Login | 5/min per email+IP |
| Registration | 6/min per IP |
| Password reset | 3/min per email |
| Deployment creation | 10/min per environment |
| Cancel/rollback | 10/min per deployment |
| Webhook | 200/min per installation |
| Internal worker API | 600/min per worker |
| Log reads | 120/min per user |

Responses include `Retry-After` and `429 RATE_LIMITED`.

---

## 7. Internal worker endpoints (summary)

Full request/response schemas: `docs/WORKER-CONTRACTS.md`. All require worker HMAC auth except `register`.

| Method | Path | Purpose |
|---|---|---|
| POST | `/internal/workers/register` | One-time registration with bootstrap token |
| POST | `/internal/workers/heartbeat` | Worker liveness + capabilities refresh |
| POST | `/internal/jobs/claim` | Atomically lease a job |
| POST | `/internal/jobs/{job}/start` | Acknowledge start |
| POST | `/internal/jobs/{job}/step` | Report step transition (addition to blueprint §4.8) |
| GET | `/internal/jobs/{job}/config` | Fetch runtime env values + health rules (deploy only) |
| POST | `/internal/jobs/{job}/logs` | Upload a log chunk |
| POST | `/internal/jobs/{job}/heartbeat` | Renew lease / receive cancel flag |
| POST | `/internal/jobs/{job}/complete` | Report success with artifact facts |
| POST | `/internal/jobs/{job}/fail` | Report failure with a code |

Worker endpoints return `204` for empty success, never redirect, and never render HTML.

---

## 8. Web UI routes (pages)

| Path | Page |
|---|---|
| `/dashboard` | Cross-application overview |
| `/organizations/{slug}` | Org overview |
| `/organizations/{slug}/members` | Members + invitations |
| `/organizations/{slug}/repositories` | Repository picker |
| `/organizations/{slug}/applications` | Application list |
| `/applications/{app}` | Application overview + environments |
| `/applications/{app}/deployments` | Deployment history |
| `/deployments/{deployment}` | Deployment detail (steps, timeline, logs) |
| `/organizations/{slug}/audit` | Audit log (admin+) |
| `/organizations/{slug}/workers` | Workers and leases (platform admin only) |
| `/notifications` | In-app notifications |

Screen contracts are in `docs/UI-SPEC.md`.
