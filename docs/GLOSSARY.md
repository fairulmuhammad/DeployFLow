# Glossary

Vocabulary is normative. If code or UI uses a term differently from this file, the code is wrong.

| Term | Meaning | Not to be confused with |
|---|---|---|
| **Organization** | Tenant boundary. Owns members, GitHub installations, applications, audit records. | GitHub organization |
| **Member** | User ↔ organization link with a role (`owner`, `admin`, `developer`, `viewer`). | User |
| **GitHub installation** | A DeployFlow GitHub App installed on a GitHub account. Grants repository access via installation tokens. | OAuth login |
| **Repository** | A GitHub repository made visible through an installation, owned by one organization. | Application |
| **Application** | A deployable unit bound to exactly one repository, with one or more environments. | Deployment |
| **Environment** | A deploy target within an application (`staging`, `production`), with its own branch, variables, and active release. | Application |
| **Environment variable** | A key/value pair attached to an environment. Secret values are write-only. | Build argument |
| **Deployment** | One attempt to take a specific commit from built image to an active release. Immutable record, never reused. | Release |
| **Deployment step** | A phase inside a deployment (`clone`, `build`, `push`, `deploy`, `health_check`, `activate`, `rollback`). | Worker job |
| **Release** | A verified, activated artifact in an environment. Exactly one active release per environment. | Deployment |
| **Rollback** | A new deployment with `trigger_type=rollback` that activates a previous release's image digest. It does not rebuild. | Re-deploy |
| **Build worker** | Isolated process/service that clones a commit, builds an OCI image with BuildKit, and pushes it. | Queue worker |
| **Deployment agent** | Isolated process/service with narrow container-runtime access that pulls an image by digest, starts a container, checks health, and switches traffic. | Build worker |
| **Worker** | Either a build worker or a deployment agent registered with the control plane. | Laravel queue worker |
| **Worker job** | A leased unit of execution work (`build`, `deploy`, `rollback`) tracked in `worker_jobs`. | Laravel queued job |
| **Lease** | Time-bounded exclusive right of a worker to act on a job. Expires unless renewed. | Lock |
| **Queue worker** | `php artisan queue:work` process for control-plane internal jobs (webhooks, outbox relay, notifications). | Execution worker |
| **Outbox** | Transactionally written table of events pending dispatch to the queue. | Job table |
| **Release activation** | The moment traffic is switched to a verified container. | Deployment success |
| **Health check** | Agent-executed HTTP probe against the new container's internal address: `path` + `port` + status/timeout rules. | Uptime monitor |
| **Blue-green** | Two container slots per environment; traffic moves only after the green slot passes health checks. | Rolling update |
| **Traffic switch** | Traefik routing change that points the environment's public host to the verified slot. | DNS change |
| **Digest** | Immutable image identifier `sha256:<64 hex>`. The only acceptable deploy reference. | Tag |
| **Idempotency key** | Client-supplied key, unique per environment, that makes deployment creation repeatable. | Request ID |
| **Request ID** | Per-HTTP-request correlation UUID. | Idempotency key |
| **Failure code** | Stable machine-readable reason from `docs/ERROR-CODES.md`. | Failure message |
| **Audit log** | Attributable record of a sensitive action. Never contains secret values. | Application log |
| **Application log** | Structured JSON operational log with correlation fields. | Audit log |

## State vocabulary

Deployment: `queued`, `cloning`, `building`, `pushing`, `deploying`, `health_checking`, `succeeded`, `failed`, `cancelled`.
Step: `queued`, `running`, `succeeded`, `failed`, `skipped`.
Worker job: `queued`, `leased`, `running`, `completed`, `failed`, `cancelled`.
Release: `active`, `inactive`.
Worker: `online`, `offline`, `draining`.

Only these spellings are valid. Do not introduce synonyms (`pending`, `in_progress`, `done`, `error`).
