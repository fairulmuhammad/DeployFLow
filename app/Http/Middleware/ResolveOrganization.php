<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Domain\Organizations\Models\Organization;
use App\Domain\Tenancy\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Starts the tenant context for the active organization.
 *
 * The `{organization:slug}` route parameter is already resolved to an
 * `Organization` model by `OrganizationRouteBinding`. This middleware exposes
 * it as `request.organization` and starts `TenantContext` so every tenant-owned
 * query in the request is constrained to it.
 *
 * Requests without an organization (the organization list, invitation
 * acceptance) clear the context first, so a previous request in the same
 * process cannot leak its tenant. Those paths either use the unscoped
 * `organizations` aggregate or establish the tenant explicitly once it is known.
 *
 * Downstream code (e.g., `EnsureRole`) still denies when the organization is
 * missing.
 */
final class ResolveOrganization
{
    public function __construct(
        private readonly TenantContext $tenant,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $organization = $request->route('organization');

        if ($organization instanceof Organization) {
            $request->attributes->set('organization', $organization);
            $this->tenant->for($organization->getKey());
        } else {
            $this->tenant->clear();
        }

        return $next($request);
    }
}
