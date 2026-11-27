<?php

declare(strict_types=1);

namespace App\Domain\Tenancy;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/**
 * Constrains every query on a tenant-owned model to the active organization.
 *
 * Throws instead of silently returning all rows when no context is set, so a
 * missing `TenantContext::for()` is caught in development and tests rather than
 * becoming a cross-tenant read in production (docs/SECURITY.md §3).
 *
 * @implements Scope<Model>
 */
final class BelongsToOrganizationScope implements Scope
{
    /**
     * @param  Builder<covariant Model>  $builder
     */
    public function apply(Builder $builder, Model $model): void
    {
        $context = app(TenantContext::class);

        if ($context->bypassesScope()) {
            return;
        }

        $organizationId = $context->organizationId();

        if ($organizationId === null) {
            throw TenantContextMissing::for($model::class);
        }

        $builder->where(
            $model->qualifyColumn('organization_id'),
            $organizationId,
        );
    }
}
