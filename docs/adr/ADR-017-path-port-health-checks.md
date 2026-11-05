# ADR-017 — Health checks are path/port probes only

- **Status:** Accepted
- **Date:** 2026-11-05
- **Related:** DF-024, blueprint §6.7

## Context
User-supplied absolute URLs in health checks create an SSRF primitive from inside the deployment network, including
cloud metadata endpoints.

## Decision
v1 health checks accept only a path (`/health`) and a port, validated and executed by the agent against the new
container's internal address. No scheme, host, redirects, or user-controlled DNS.

## Alternatives
| Option | Why rejected |
|---|---|
| Absolute URLs + blocklist | Bypassable via DNS rebinding and redirects; hard to review |
| URL checks with allowlist | Still requires the full SSRF control set for marginal value |
| No health check | Violates the product's core safety guarantee |

## Consequences
Some applications need a lightweight `/health` endpoint; that is documented in the app setup guide.

## Security implications
The SSRF class is removed rather than mitigated in v1. URL checks would require the full control set in
`SECURITY.md` §10.
