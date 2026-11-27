<?php

declare(strict_types=1);

namespace App\Domain\Tenancy;

/**
 * The active tenant for the current unit of work.
 *
 * `ResolveOrganization` starts the context for organization-scoped requests.
 * System contexts (queue jobs, webhook processing, workers, console commands)
 * must call {@see self::for()} explicitly before touching tenant-owned models.
 *
 * Queries against tenant-owned models throw {@see TenantContextMissing} when no
 * organization is set. The only sanctioned escape is {@see self::withoutScope()},
 * which docs/SECURITY.md §3 restricts to migrations, seeders, `Ops` commands,
 * `ReaperService`, and `AuditExporter`.
 *
 * The instance is registered as a scoped container binding so it is flushed
 * between requests (Octane) and queue jobs.
 */
final class TenantContext
{
    private ?string $organizationId = null;

    private bool $bypassesScope = false;

    /**
     * Start (or replace) the tenant context.
     */
    public function for(string $organizationId): void
    {
        $this->organizationId = $organizationId;
    }

    public function organizationId(): ?string
    {
        return $this->organizationId;
    }

    public function isSet(): bool
    {
        return $this->organizationId !== null;
    }

    /**
     * Clear the tenant context. Called at the start of every request so a
     * previous request in the same process cannot leak its tenant.
     */
    public function clear(): void
    {
        $this->organizationId = null;
    }

    /**
     * Run a callback in a system context that must query across tenants.
     *
     * Every call site must be listed in docs/SECURITY.md §3.
     *
     * @template TValue
     *
     * @param  callable(): TValue  $callback
     * @return TValue
     */
    public function withoutScope(callable $callback): mixed
    {
        $previous = $this->bypassesScope;
        $this->bypassesScope = true;

        try {
            return $callback();
        } finally {
            $this->bypassesScope = $previous;
        }
    }

    /**
     * Run a callback against a specific tenant, restoring the previous context
     * afterwards. Used when the tenant is discovered mid-request (for example,
     * an invitation token) before the rest of the flow has a context.
     *
     * @template TValue
     *
     * @param  callable(): TValue  $callback
     * @return TValue
     */
    public function run(string $organizationId, callable $callback): mixed
    {
        $previous = $this->organizationId;
        $this->organizationId = $organizationId;

        try {
            return $callback();
        } finally {
            $this->organizationId = $previous;
        }
    }

    public function bypassesScope(): bool
    {
        return $this->bypassesScope;
    }
}
