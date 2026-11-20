<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Application\DTOs\Organizations\CreateOrganizationData;
use App\Application\DTOs\Organizations\UpdateOrganizationData;
use App\Domain\Identity\Models\User;
use App\Domain\Organizations\Actions\OrganizationActions;
use App\Domain\Organizations\Enums\MemberRole;
use App\Domain\Organizations\Models\Organization;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class OrganizationController extends Controller
{
    public function __construct(
        private readonly OrganizationActions $actions,
    ) {}

    /**
     * The list of organizations the current user belongs to.
     */
    public function index(Request $request): Response
    {
        /** @var User $user */
        $user = $request->user();

        $organizations = Organization::with('owner')
            ->whereHas(
                'members',
                fn ($query) => $query->where('user_id', $user->getKey()),
            )
            ->orderBy('created_at')
            ->get()
            ->map(function (Organization $organization) use ($user) {
                $role = $organization->roleForUser($user)?->value;

                return [
                    'id' => $organization->getKey(),
                    'name' => $organization->name,
                    'slug' => $organization->slug,
                    'role' => $role,
                    'created_at' => $organization->created_at?->toIso8601String(),
                ];
            })
            ->values()
            ->all();

        return Inertia::render('Organizations/Index', [
            'organizations' => $organizations,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', Organization::class);

        /** @var User $user */
        $user = $request->user();

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'slug' => ['nullable', 'string', 'max:80', 'regex:/^[a-z0-9-]+$/'],
        ]);

        $organization = $this->actions->create(
            $user,
            new CreateOrganizationData(
                name: $validated['name'],
                slug: $validated['slug'] ?? null,
            ),
        );

        return redirect()
            ->route('organizations.show', ['organization' => $organization->slug])
            ->with('status', 'Organization created.');
    }

    public function show(Request $request, Organization $organization): Response
    {
        $this->authorize('view', $organization);

        /** @var User $user */
        $user = $request->user();

        $role = $organization->roleForUser($user)?->value;

        $members = $organization->members()
            ->with('user')
            ->orderBy('created_at')
            ->get()
            ->map(fn ($member) => [
                'id' => $member->getKey(),
                'name' => $member->user?->name,
                'email' => $member->user?->email,
                'role' => $member->role->value,
                'is_owner' => $member->role === MemberRole::Owner,
                'created_at' => $member->created_at !== null ? $member->created_at->toIso8601String() : null,
            ])
            ->values()
            ->all();

        $invitations = $organization->invitations()
            ->whereNull('accepted_at')
            ->orderBy('created_at')
            ->get()
            ->map(fn ($invitation) => [
                'id' => $invitation->getKey(),
                'email' => $invitation->email,
                'role' => $invitation->role->value,
                'invited_by' => $invitation->invited_by,
                'expires_at' => $invitation->expires_at->toIso8601String(),
                'created_at' => $invitation->created_at !== null ? $invitation->created_at->toIso8601String() : null,
            ])
            ->values()
            ->all();

        return Inertia::render('Organizations/Show', [
            'organization' => [
                'id' => $organization->getKey(),
                'name' => $organization->name,
                'slug' => $organization->slug,
                'role' => $role,
                'created_at' => $organization->created_at?->toIso8601String(),
            ],
            'members' => $members,
            'invitations' => $invitations,
            'memberCount' => count($members),
        ]);
    }

    public function update(Request $request, Organization $organization): RedirectResponse
    {
        $this->authorize('update', $organization);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
        ]);

        /** @var User $user */
        $user = $request->user();

        $this->actions->update(
            $organization,
            new UpdateOrganizationData(name: $validated['name']),
            $user,
        );

        return back()->with('status', 'Organization updated.');
    }

    public function destroy(Request $request, Organization $organization): RedirectResponse
    {
        $this->authorize('destroy', $organization);

        /** @var User $user */
        $user = $request->user();

        $this->actions->destroy($organization, $user);

        return redirect()
            ->route('organizations.index')
            ->with('status', 'Organization archived.');
    }
}
