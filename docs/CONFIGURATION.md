# Configuration Reference

Every environment variable the system reads. `.env.example` is generated from this table; if they diverge, this file is
wrong and must be fixed in the same PR.

Legend: **S** = secret (never committed, never logged), **R** = required in production.

App-local config lives under `config/deployflow.php` (added in M0) and reads these keys. Do not read `env()` outside
config files.

---

## 1. Application

| Variable | Default | S | R | Notes |
|---|---|---|---|---|
| `APP_NAME` | `DeployFlow` | | | |
| `APP_ENV` | `production` | | | `local` enables debug tooling only |
| `APP_KEY` | — | S | ✓ | `base64:` AES-256; rotate with key history |
| `APP_DEBUG` | `false` | | | never `true` in production |
| `APP_URL` | `http://localhost` | | ✓ | must match the public scheme/host |
| `APP_TIMEZONE` | `UTC` | | | do not change; UI renders local time |
| `APP_LOCALE` | `en` | | | |
| `LOG_CHANNEL` | `stderr` | | | JSON formatter in production |
| `LOG_LEVEL` | `info` | | | `debug` only locally |
| `LOG_RETENTION_DAYS` | `30` | | | deployment log objects + index rows |

## 2. Database

| Variable | Default | S | R | Notes |
|---|---|---|---|---|
| `DB_CONNECTION` | `pgsql` | | ✓ | |
| `DB_HOST` | `127.0.0.1` | | ✓ | |
| `DB_PORT` | `5432` | | ✓ | |
| `DB_DATABASE` | `deployflow` | | ✓ | |
| `DB_USERNAME` | `deployflow_app` | | ✓ | DML role; migrations use `deployflow_migrate` |
| `DB_PASSWORD` | — | S | ✓ | |
| `DB_SSLMODE` | `prefer` | | | `require` in production |
| `DB_POOL_MAX` | `20` | | | per app/worker process |
| `DB_STATEMENT_TIMEOUT_MS` | `10000` | | | prevents stuck requests |

## 3. Redis

| Variable | Default | S | R | Notes |
|---|---|---|---|---|
| `REDIS_HOST` | `127.0.0.1` | | ✓ | |
| `REDIS_PORT` | `6379` | | ✓ | |
| `REDIS_PASSWORD` | — | S | | required in production |
| `REDIS_DB_CACHE` | `0` | | | |
| `REDIS_DB_QUEUE` | `1` | | | |
| `REDIS_DB_LOCKS` | `2` | | | |
| `REDIS_DB_NONCE` | `3` | | | worker replay cache |
| `CACHE_STORE` | `redis` | | ✓ | |
| `SESSION_DRIVER` | `redis` | | ✓ | |
| `SESSION_LIFETIME` | `120` | | | minutes |
| `SESSION_SECURE_COOKIE` | `true` | | ✓ | must be `true` behind HTTPS |
| `QUEUE_CONNECTION` | `redis` | | ✓ | control-plane internal jobs only |
| `HORIZON_PREFIX` | `deployflow_horizon` | | | |

## 4. Mail

| Variable | Default | S | R | Notes |
|---|---|---|---|---|
| `MAIL_MAILER` | `log` (local) / `smtp` (prod) | | ✓ | invitations, password reset, verification |
| `MAIL_HOST` / `MAIL_PORT` / `MAIL_USERNAME` | — | | ✓ | |
| `MAIL_PASSWORD` | — | S | ✓ | |
| `MAIL_ENCRYPTION` | `tls` | | | |
| `MAIL_FROM_ADDRESS` | `no-reply@deployflow.local` | | ✓ | |
| `MAIL_FROM_NAME` | `DeployFlow` | | | |

## 5. GitHub App

| Variable | Default | S | R | Notes |
|---|---|---|---|---|
| `GITHUB_APP_ID` | — | | ✓ | |
| `GITHUB_APP_CLIENT_ID` | — | | ✓ | |
| `GITHUB_APP_CLIENT_SECRET` | — | S | ✓ | |
| `GITHUB_APP_SLUG` | — | | ✓ | installation URL segment |
| `GITHUB_APP_PRIVATE_KEY_PATH` | `/run/secrets/github_app.pem` | S | ✓ | mounted file preferred over inline |
| `GITHUB_APP_PRIVATE_KEY` | — | S | | base64 PEM alternative; prefer the path |
| `GITHUB_WEBHOOK_SECRET` | — | S | ✓ | HMAC key for `X-Hub-Signature-256` |
| `GITHUB_API_URL` | `https://api.github.com` | | | Enterprise override |
| `GITHUB_TOKEN_CACHE_TTL` | `3000` | | | installation token cache (s) |
| `GITHUB_HTTP_TIMEOUT` | `15` | | | seconds |

App permissions required: **Contents: read**, **Metadata: read**, **Webhooks: read**. Add **Packages: write** only if the
chosen registry driver pushes to GHCR with installation tokens (verify in DF-009; otherwise use a dedicated token).

## 6. Registry

| Variable | Default | S | R | Notes |
|---|---|---|---|---|
| `REGISTRY_DRIVER` | `local` | | ✓ | `local` \| `ghcr` |
| `REGISTRY_HOST` | `registry.local:5000` | | ✓ | must be in the allowlist |
| `REGISTRY_USERNAME` | — | | ✓ | |
| `REGISTRY_PASSWORD` | — | S | ✓ | |
| `REGISTRY_REPOSITORY_PREFIX` | `deployflow` | | | `prefix/org/app` |
| `REGISTRY_ALLOWED_HOSTS` | `registry.local:5000` | | ✓ | comma-separated; enforced on job payloads |
| `REGISTRY_PUSH_RETRIES` | `3` | | | bounded backoff 5/15/45 s |
| `GHCR_USERNAME` | — | | | only when driver=ghcr |
| `GHCR_TOKEN` | — | S | | `read:packages`, `write:packages` (classic PAT or app token) |

## 7. Object storage (logs)

| Variable | Default | S | R | Notes |
|---|---|---|---|---|
| `AWS_ACCESS_KEY_ID` | — | S | ✓ | |
| `AWS_SECRET_ACCESS_KEY` | — | S | ✓ | |
| `AWS_DEFAULT_REGION` | `us-east-1` | | | |
| `AWS_BUCKET` | `deployflow-logs` | | ✓ | private |
| `AWS_ENDPOINT` | — | | | S3-compatible local store (S3Mock) |
| `AWS_USE_PATH_STYLE_ENDPOINT` | `true` (local) | | | |
| `LOG_SIGNED_URL_TTL` | `300` | | | seconds |
| `LOG_CHUNK_MAX_BYTES` | `65536` | | | worker upload cap |

## 8. Routing / deployment target

| Variable | Default | S | R | Notes |
|---|---|---|---|---|
| `DEPLOY_BASE_DOMAIN` | `apps.deployflow.test` | | ✓ | environment `public_host` is `<env>.<app>.<base>` |
| `DEPLOY_NETWORK_PREFIX` | `df-env-` | | | container network per environment |
| `TRAEFIK_DYNAMIC_PATH` | `/traefik/dynamic` | | ✓ | agent writes config here (shared volume) |
| `TRAEFIK_ROUTER_ENTRYPOINT` | `websecure` | | ✓ | |
| `TRAEFIK_CERT_RESOLVER` | `letsencrypt` | | | |
| `DEPLOY_SLOT_RETENTION_SECONDS` | `900` | | | previous slot kept before removal |
| `CONTAINER_PULL_POLICY` | `always-by-digest` | | | tags are rejected regardless |

## 9. Execution limits

| Variable | Default | S | R | Notes |
|---|---|---|---|---|
| `WORKER_BOOTSTRAP_TOKEN` | — | S | ✓ | rotate after registration |
| `WORKER_SECRET_TTL_DAYS` | `180` | | | credential expiry |
| `WORKER_REQUEST_MAX_SKEW_SECONDS` | `300` | | | signature window |
| `WORKER_NONCE_TTL_SECONDS` | `600` | | | replay cache |
| `WORKER_LEASE_SECONDS` | `90` | | | |
| `WORKER_HEARTBEAT_SECONDS` | `30` | | | |
| `WORKER_OFFLINE_AFTER_SECONDS` | `60` | | | reaper threshold |
| `BUILD_TIMEOUT_SECONDS` | `1200` | | | per job |
| `BUILD_MAX_TIMEOUT_SECONDS` | `3600` | | | config ceiling |
| `BUILD_MAX_CONTEXT_MB` | `2048` | | | |
| `BUILD_CPU` / `BUILD_MEMORY_MB` | `2` / `2048` | | | defaults, overridable per app |
| `DEPLOY_TIMEOUT_SECONDS` | `120` | | | container start |
| `HEALTH_INTERVAL_SECONDS` | `5` | | | |
| `HEALTH_TIMEOUT_SECONDS` | `2` | | | |
| `HEALTH_RETRIES` | `12` | | | |
| `HEALTH_STARTUP_GRACE_SECONDS` | `10` | | | |
| `DEPLOYMENT_HARD_TIMEOUT_SECONDS` | `3600` | | | whole lifecycle |
| `OUTBOX_MAX_ATTEMPTS` | `5` | | | |
| `REAPER_INTERVAL_SECONDS` | `30` | | | |

## 10. Platform / operations

| Variable | Default | S | R | Notes |
|---|---|---|---|---|
| `PLATFORM_ADMIN_EMAILS` | — | | | comma-separated; worker/ops screens |
| `TRUSTED_PROXIES` | `*` (local) | | ✓ | explicit CIDRs in production |
| `FORCE_HTTPS` | `true` | | ✓ | |
| `RATE_LIMIT_DEPLOYMENTS_PER_MINUTE` | `10` | | | per environment |
| `RATE_LIMIT_WEBHOOKS_PER_MINUTE` | `200` | | | per installation |
| `RATE_LIMIT_INTERNAL_PER_MINUTE` | `600` | | | per worker |
| `METRICS_ENABLED` | `true` | | | Prometheus endpoint on the internal port only |
| `SENTRY_DSN` | — | S | | optional; scrub secrets before send |

---

## 11. Secret inventory

| Secret | Where | Rotation |
|---|---|---|
| `APP_KEY` | env/secret manager | key history + re-encrypt command; sessions invalidated |
| GitHub App private key | mounted file | rotate in GitHub, replace file, restart |
| `GITHUB_WEBHOOK_SECRET` | env | rotate in GitHub App settings first, then env |
| `GITHUB_APP_CLIENT_SECRET` | env | rotate in GitHub, then env |
| Registry credentials | env | rotate at registry, then env, then rollout |
| `WORKER_BOOTSTRAP_TOKEN` | env | rotate; existing worker credentials unaffected |
| Worker secrets | DB (encrypted) | revoke + re-register the worker |
| `AWS_*` | env | rotate in IAM, then env |
| `MAIL_PASSWORD` | env | provider flow |
| `SENTRY_DSN` | env | revoke project key |

Rule: rotate in the provider first, then deploy the new value, then revoke the old one (see `OPERATIONS.md`).

---

## 12. Local defaults

`make setup` copies `.env.example` with local-safe values:

```text
APP_ENV=local
APP_DEBUG=true
DB_HOST=postgres
REDIS_HOST=redis
REGISTRY_DRIVER=local
REGISTRY_HOST=registry.local:5000
AWS_ENDPOINT=http://127.0.0.1:9000
AWS_USE_PATH_STYLE_ENDPOINT=true
MAIL_MAILER=log
QUEUE_CONNECTION=redis
```

Local values must never be reused in staging/production.
