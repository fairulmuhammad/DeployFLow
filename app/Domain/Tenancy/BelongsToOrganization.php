<?php

declare(strict_types=1);

namespace App\Domain\Tenancy;

/**
 * Marks an Eloquent model as tenant-owned.
 *
 * Every model that stores `organization_id` (including denormalized children,
 * ADR-015) uses this trait so reads are constrained to the active tenant.
 * Writes are not scoped by Eloquent global scopes; callers must set the
 * organization explicitly, as the models already do.
 */
trait BelongsToOrganization
{
    public static function bootBelongsToOrganization(): void
    {
        static::addGlobalScope(new BelongsToOrganizationScope);
    }
}
