<?php

declare(strict_types=1);

namespace App\Http\RouteBinding;

use App\Domain\Organizations\Models\Organization;
use Illuminate\Support\Facades\Route;

/**
 * Resolves the `{organization:slug}` route parameter to an Organization model.
 *
 * Membership is enforced by `OrganizationPolicy::view`; a foreign slug or a
 * non-member both produce 403/404. The global scope (DF-008) will tighten this
 * further with a tenant-bound query.
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
