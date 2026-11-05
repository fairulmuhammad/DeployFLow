# ADR-008 — Deploy immutable images by digest

- **Status:** Accepted
- **Date:** 2026-11-05
- **Related:** DF-019, DF-023

## Context
Tags are mutable; a tag can be repointed after verification.

## Decision
Every deployment records `image_digest` (`sha256:...`) from the registry after push, and the agent pulls by digest only.
Tags are used for human display and registry push, never as the deploy reference.

## Alternatives
| Option | Why rejected |
|---|---|
| Tag-based deploy | Non-reproducible; rollback could deploy different bytes |
| Commit SHA as image reference | Convenient but still a mutable mapping |

## Consequences
Rollback is exact. Image retention must preserve digests referenced by releases.

## Security implications
Prevents post-review image substitution and supply-chain confusion.
