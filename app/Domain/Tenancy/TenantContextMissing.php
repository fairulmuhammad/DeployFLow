<?php

declare(strict_types=1);

namespace App\Domain\Tenancy;

use RuntimeException;

/**
 * Thrown when a tenant-owned model is queried without a tenant context.
 *
 * Silent cross-tenant reads are treated as a bug (docs/ARCHITECTURE.md §4), so
 * the query fails loudly instead of returning every tenant's rows.
 */
final class TenantContextMissing extends RuntimeException
{
    public static function for(string $model): self
    {
        return new self(sprintf(
            'No tenant context is set while querying %s. Call TenantContext::for($organizationId) first.',
            $model,
        ));
    }
}
