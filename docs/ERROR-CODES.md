# Error Codes and Failure Reasons

Two separate namespaces:

- **API errors** — returned in `{"error":{"code","message"}}`; stable contract for the UI.
- **Failure codes** — stored in `deployments.failure_code` / `worker_jobs.last_error_code`; stable contract for
  operators and metrics.

Never invent a code at the call site. Add it here first (I-14), then use it.

---

## 1. API error codes

| Code | HTTP | Meaning | Client action |
|---|---|---|---|
| `UNAUTHENTICATED` | 401 | No/expired session | Redirect to login |
| `EMAIL_NOT_VERIFIED` | 403 | Session valid, email unverified | Resend verification |
| `FORBIDDEN` | 403 | Policy denied | Do not retry |
| `NOT_FOUND` | 404 | Missing **or** outside active organization | Treat as gone |
| `VALIDATION_FAILED` | 422 | Field errors in `error.details` | Fix input |
| `STATE_CONFLICT` | 409 | Illegal lifecycle transition | Refresh state |
| `DEPLOYMENT_ALREADY_RUNNING` | 409 | Active deployment exists for environment | Wait or cancel |
| `ENVIRONMENT_DISABLED` | 409 | Environment not accepting deployments | Enable it |
| `CANNOT_CANCEL_AFTER_ACTIVATION` | 409 | Traffic already switched | Use rollback |
| `IDEMPOTENCY_KEY_REUSED` | 409 | Same key, different request body | Use a new key |
| `IDEMPOTENCY_KEY_MISSING` | 422 | Required header absent | Send `Idempotency-Key` |
| `ROLLBACK_TARGET_INVALID` | 422 | Release not restorable (wrong env, inactive for too long, digest missing) | Pick another release |
| `APPLICATION_ARCHIVED` | 409 | Application is archived | Unarchive first |
| `REPOSITORY_UNAVAILABLE` | 422 | Installation revoked, repo archived/deleted | Reconnect GitHub |
| `BUILD_CONFIGURATION_INVALID` | 422 | Missing/invalid Dockerfile path, context, port | Fix build config |
| `REGISTRY_NOT_ALLOWED` | 422 | Image registry not in allowlist | Use the configured registry |
| `IMAGE_REFERENCE_INVALID` | 422 | Malformed tag/digest | Fix reference |
| `RATE_LIMITED` | 429 | Too many requests | Back off; `Retry-After` |
| `PAYLOAD_TOO_LARGE` | 413 | Webhook/body over limit | Do not retry |
| `SIGNATURE_INVALID` | 401 | Webhook HMAC mismatch | Never retry; investigate |
| `LEASE_LOST` | 409 | Worker no longer holds the lease | Stop work, discard |
| `JOB_NOT_CLAIMABLE` | 409 | Job taken/cancelled/not for this worker | Claim another |
| `WORKER_UNAUTHORIZED` | 401 | Bad worker signature/credential | Re-register |
| `INTERNAL_ERROR` | 500 | Unexpected | Retry with backoff; report with request ID |

Validation errors keep Laravel's shape but under `error.details`:

```json
{"error":{"code":"VALIDATION_FAILED","message":"The given data was invalid.","details":{"commit_sha":["The commit sha field must be 40 characters."]}}}
```

Every response carries `meta.request_id`; every error is logged with it.

---

## 2. Deployment failure codes

Codes are written by the control plane, not by workers directly (workers send a code, the control plane validates it is
in this table). `failure_reason` is a sanitized human sentence without secrets.

### Source

| Code | Stage | Retryable | Operator action |
|---|---|---|---|
| `GIT_CLONE_FAILED` | clone | yes (transient) | Check GitHub status; retry deployment |
| `GIT_AUTH_FAILED` | clone | no | Reconnect GitHub installation / check token permissions |
| `COMMIT_NOT_FOUND` | clone | no | Verify SHA exists on the repository |
| `BRANCH_NOT_FOUND` | clone | no | Fix environment branch |
| `REPOSITORY_ARCHIVED` | clone | no | Unarchive or pick another repo |
| `DOCKERFILE_MISSING` | build | no | Fix `build_configuration.dockerfile_path` |
| `BUILD_FAILED` | build | no | Read build logs; fix source |
| `BUILD_TIMEOUT` | build | yes | Raise limit or reduce build time |
| `BUILD_OOM` | build | yes | Reduce build memory use or raise limit |
| `BUILD_CONTEXT_TOO_LARGE` | build | no | Use `.dockerignore` |
| `REGISTRY_AUTH_FAILED` | push | no | Fix registry credentials |
| `REGISTRY_UNAVAILABLE` | push | yes | Retry with backoff |
| `PUSH_FAILED` | push | yes (bounded) | Check registry quota/network |
| `DIGEST_MISMATCH` | push | no | Treat as tampering/corruption; investigate |
| `IMAGE_PULL_FAILED` | pull | yes (bounded) | Check registry auth and digest existence |
| `IMAGE_NOT_ALLOWED` | pull | no | Registry allowlist violation |
| `CONTAINER_START_FAILED` | deploy | no | Read container logs; check env/ports |
| `PORT_NOT_LISTENING` | health_check | no | App must listen on configured port |
| `HEALTH_CHECK_FAILED` | health_check | no | Fix `/health` or app startup |
| `HEALTH_CHECK_TIMEOUT` | health_check | no | Fix startup time or raise timeout |
| `TRAFFIC_SWITCH_FAILED` | activate | yes (bounded) | Check Traefik file provider and dynamic config |
| `ROUTE_VERIFY_FAILED` | activate | yes (bounded) | Check public host / proxy config |
| `ACTIVATION_CONFLICT` | activate | no | Another release activated concurrently |
| `ROLLBACK_FAILED` | rollback | yes | Investigate; current release untouched |
| `ROLLBACK_TARGET_INVALID` | rollback | no | Pick a verified release |
| `WORKER_LOST` | any | yes | Check worker logs/health; retry |
| `LEASE_EXPIRED` | any | yes | Reaper will requeue if attempts remain |
| `CANCELLED` | any | — | User action |
| `STUCK_DEPLOYMENT` | any | no | Manual investigation; safe to retry |
| `INTERNAL_ERROR` | any | yes | Report with deployment ID |

### Step mapping

| Step | Allowed codes |
|---|---|
| `clone` | `GIT_CLONE_FAILED`, `GIT_AUTH_FAILED`, `COMMIT_NOT_FOUND`, `BRANCH_NOT_FOUND`, `REPOSITORY_ARCHIVED` |
| `build` | `DOCKERFILE_MISSING`, `BUILD_FAILED`, `BUILD_TIMEOUT`, `BUILD_OOM`, `BUILD_CONTEXT_TOO_LARGE` |
| `push` | `REGISTRY_AUTH_FAILED`, `REGISTRY_UNAVAILABLE`, `PUSH_FAILED`, `DIGEST_MISMATCH` |
| `pull` | `IMAGE_PULL_FAILED`, `IMAGE_NOT_ALLOWED` |
| `deploy` | `CONTAINER_START_FAILED` |
| `health_check` | `PORT_NOT_LISTENING`, `HEALTH_CHECK_FAILED`, `HEALTH_CHECK_TIMEOUT` |
| `activate` | `TRAFFIC_SWITCH_FAILED`, `ROUTE_VERIFY_FAILED`, `ACTIVATION_CONFLICT` |
| `rollback` | `ROLLBACK_FAILED`, `ROLLBACK_TARGET_INVALID` |
| any | `WORKER_LOST`, `LEASE_EXPIRED`, `CANCELLED`, `STUCK_DEPLOYMENT`, `INTERNAL_ERROR` |

---

## 3. Rules

1. Codes are `UPPER_SNAKE_CASE` and never contain dynamic data.
2. Messages are user-facing, ≤ 200 chars, and contain no secrets, paths on the host, or stack traces.
3. Stack traces go to application logs, correlated by `request_id` / `deployment_id`, never to the API.
4. A retryable failure may be retried by the user; automatic retries are bounded by `max_attempts`.
5. Adding a code requires updating this file and the failure-code enum in the same PR.
6. Metrics label on the code; keep cardinality bounded (this table is the full list).
