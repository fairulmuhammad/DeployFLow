<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Domain\Organizations\Models\Organization;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Resolves the active organization from the route and attaches it to the
 * request attributes for downstream consumers (policies, controllers, etc.).
 *
 * The `{organization:slug}` route parameter is already resolved to an
 * `Organization` model by `OrganizationRouteBinding`. This middleware simply
 * exposes it as `request.organization` for consistent access.
 *
 * If the route has no organization parameter, or the parameter could not be
 * resolved, the request continues without an organization context. Downstream
 * code (e.g., `EnsureRole`) will deny when the organization is missing.
 */
final class ResolveOrganization
{
    public function handle(Request $request, Closure $next): Response
    {
        $organization = $request->route('organization');

        if ($organization instanceof Organization) {
            $request->attributes->set('organization', $organization);
        }

        return $next($request);
    }
}
