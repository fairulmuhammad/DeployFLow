<?php

declare(strict_types=1);

namespace App\Http\RouteBinding;

use App\Domain\Organizations\Models\Organization;
use App\Domain\Organizations\Models\OrganizationInvitation;
use App\Domain\Organizations\Models\OrganizationMember;
use App\Domain\Tenancy\TenantContext;
use Illuminate\Support\Facades\Route;

/**
 * Resolves tenant-owned route parameters inside the active organization.
 *
 * The `{organization:slug}` parameter is resolved first by
 * {@see OrganizationRouteBinding}; these binders then start the tenant context
 * and query the model through the global scope. A record from another
 * organization (or a missing one) raises `ModelNotFoundException`, which the
 * framework renders as `404` — never `403` or a redirect that leaks existence.
 */
final class TenantRouteBinding
{
    public function register(): void
    {
        Route::bind('member', function (string $id): OrganizationMember {
            $this->startTenantContext();

            return OrganizationMember::query()->whereKey($id)->firstOrFail();
        });

        Route::bind('invitation', function (string $id): OrganizationInvitation {
            $this->startTenantContext();

            return OrganizationInvitation::query()->whereKey($id)->firstOrFail();
        });
    }

    private function startTenantContext(): void
    {
        $organization = request()->route('organization');

        if (! $organization instanceof Organization) {
            abort(404);
        }

        app(TenantContext::class)->for($organization->getKey());
    }
}
