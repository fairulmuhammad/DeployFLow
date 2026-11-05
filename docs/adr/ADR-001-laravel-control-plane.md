# ADR-001 — Use Laravel 13 as the control plane

- **Status:** Accepted
- **Date:** 2026-11-05
- **Related:** DF-001

## Context
The platform needs authentication, authorization, queues, scheduling, ORM, mail, validation, and a server-rendered UI.
The team is Laravel-oriented.

## Decision
Build the control plane on Laravel 13 (PHP 8.3–8.5, pinned 8.4) with Laravel's first-party auth, queues, policies, and
scheduler.

## Alternatives
| Option | Why rejected |
|---|---|
| Node/NestJS + React SPA | Duplicates auth/queue infrastructure already provided by Laravel |
| Go control plane | Strong runtime, but slower feature delivery for a CRUD/orchestration surface |
| Symfony | Viable, but no benefit over Laravel for this team |

## Consequences
Fast delivery of tenancy, policies, and jobs; must consciously keep business logic out of controllers and avoid
framework leakage into the domain. Laravel 13 is supported until March 2028 (security).

## Security implications
Use framework defaults for CSRF, password hashing, signed URLs, and request forgery protection; do not hand-roll them.
