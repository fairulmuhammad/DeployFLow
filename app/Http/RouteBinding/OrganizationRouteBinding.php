<?php

declare(strict_types=1);

namespace App\Http\RouteBinding;

use App\Domain\Organizations\Models\Organization;
use Illuminate\Support\Facades\Route;

/**
 * Resolves the `{organization:slug}` route parameter to an Organization model.
 *
 * `Organization` is the tenant root, so it is deliberately not constrained by
 * the tenant global scope: the slug is what establishes the tenant. Membership
 * is enforced by `OrganizationPolicy::view` and `EnsureRole`; tenant-owned
 * children are resolved by {@see TenantRouteBinding} inside the active
 * organization.
 */
final class OrganizationRouteBinding
{
    public function register(): void
    {
        Route::bind('organization', function (string $slug): Organization {
            $organization = Organization::findBySlug($slug);

            if ($organization === null) {
                abort(404);
            }

            return $organization;
        });
    }
}
