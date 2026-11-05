# ADR-012 — Inertia-first UI, no public REST API in v1

- **Status:** Accepted
- **Date:** 2026-11-05
- **Related:** DF-005…DF-014, blueprint §4

## Context
The blueprint defines REST endpoints for every dashboard resource while also specifying Inertia + React. Implementing
both means two authorization surfaces, two serializers, and duplicated tests.

## Decision
The dashboard uses Inertia pages: Laravel controllers return pages with typed props. JSON endpoints exist only for
GitHub webhooks and internal worker traffic. `/api/v1` is not built in v1.

## Alternatives
| Option | Why rejected |
|---|---|
| Full REST API + React SPA | Double surface, tokens, CORS, serializers, auth duplication |
| Inertia + REST | Redundant for the same data, drift risk |
| Livewire | Contradicts the blueprint's React/TS choice |

## Consequences
Faster, simpler authorization and no API token lifecycle. A public API can be added later as an adapter over the same
Actions without rewriting domain logic.

## Security implications
One browser auth mechanism (session + CSRF), no bearer tokens to leak, smaller attack surface.
