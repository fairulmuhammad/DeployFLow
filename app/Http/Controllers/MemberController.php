<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Identity\Models\User;
use App\Domain\Organizations\Actions\OrganizationActions;
use App\Domain\Organizations\Enums\MemberRole;
use App\Domain\Organizations\Models\Organization;
use App\Domain\Organizations\Models\OrganizationMember;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

final class MemberController extends Controller
{
    public function __construct(
        private readonly OrganizationActions $actions,
    ) {}

    /**
     * Standalone members list (optional; the primary view is `organizations.show`).
     */
    public function index(Request $request, Organization $organization): Response
    {
        /** @var User $user */
        $user = $request->user();

        $members = $organization->members()
            ->with('user')
            ->orderBy('created_at')
            ->get()
            ->map(fn (OrganizationMember $member) => [
                'id' => $member->getKey(),
                'name' => $member->user?->name,
                'email' => $member->user?->email,
                'role' => $member->role->value,
                'is_owner' => $member->role === MemberRole::Owner,
                'can_manage' => $member->role->isManageable(),
                'created_at' => $member->created_at?->toIso8601String(),
            ])
            ->values()
            ->all();

        $role = $organization->roleForUser($user)?->value;

        return Inertia::render('Organizations/Members', [
            'organization' => [
                'id' => $organization->getKey(),
                'slug' => $organization->slug,
                'name' => $organization->name,
                'role' => $role,
            ],
            'members' => $members,
            'role' => $role,
        ]);
    }

    public function update(Request $request, Organization $organization, string $memberId): RedirectResponse
    {
        $member = $organization->members()->findOrFail($memberId);

        $this->authorize('manageMembers', $organization);

        if (! $member->role->isManageable()) {
            return back()->with('status', 'Owners are managed through transfer.');
        }

        $validated = $request->validate([
            'role' => ['required', Rule::enum(MemberRole::class), 'not_in:owner'],
        ]);

        /** @var User $user */
        $user = $request->user();

        $this->actions->updateMemberRole(
            $organization,
            $member,
            MemberRole::from($validated['role']),
            $user,
            $request,
        );

        return back()->with('status', 'Member role updated.');
    }

    public function destroy(Request $request, Organization $organization, string $memberId): RedirectResponse
    {
        $member = $organization->members()->findOrFail($memberId);

        $this->authorize('manageMembers', $organization);

        if (! $member->role->isManageable()) {
            return back()->with('status', 'Owners cannot be removed. Transfer ownership first.');
        }

        /** @var User $user */
        $user = $request->user();

        $this->actions->removeMember($organization, $member, $user, $request);

        return back()->with('status', 'Member removed.');
    }
}
