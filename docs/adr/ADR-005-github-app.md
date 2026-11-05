# ADR-005 — GitHub App integration

- **Status:** Accepted
- **Date:** 2026-11-05
- **Related:** DF-009, DF-010

## Context
Users must connect repositories without pasting broad personal access tokens.

## Decision
Use a GitHub App with installation tokens, webhook HMAC verification, and repository selection scoped by installation.
Minimum permissions: contents read, metadata read, webhooks read. Packages write only if required by the registry driver.

## Alternatives
| Option | Why rejected |
|---|---|
| OAuth app + PAT | Broad, long-lived, poor revocation |
| Deploy keys per repo | Manual, one key per repo, no central revocation |
| GitHub Actions as the builder | Untrusted runner control, conflates CI with deployment platform |

## Consequences
Revocation is centralized; installation tokens are short-lived; repository access is explicit.

## Security implications
Private key and webhook secret live outside the database; tokens never reach the browser.
