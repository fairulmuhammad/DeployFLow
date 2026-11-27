<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Domain\Organizations\Enums\RolePermission;
use App\Domain\Organizations\Models\Organization;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Ensures the authenticated user holds the required permission in the active
 * organization.
 *
 * The required permission is passed as a single route-middleware parameter:
 *
 *     Route::get('/...', ...)->middleware('ensure.role:view_organization');
 *
 * A non-member or unauthenticated caller is denied with 403.
 */
final class EnsureRole
{
    public function handle(Request $request, Closure $next, string $permission): Response
    {
        $user = $request->user();

        if ($user === null) {
            abort(403);
        }

        $organization = $request->route('organization');

        if (! $organization instanceof Organization) {
            abort(403);
        }

        $member = $organization->members()
            ->where('user_id', $user->getKey())
            ->first();

        $role = $member?->role;

        if ($role === null || ! RolePermission::allows($role, RolePermission::from($permission))) {
            abort(403);
        }

        return $next($request);
    }
}
