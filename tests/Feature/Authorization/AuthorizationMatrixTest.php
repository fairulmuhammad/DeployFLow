<?php

declare(strict_types=1);

use App\Domain\Identity\Models\User;
use App\Domain\Organizations\Enums\MemberRole;
use App\Domain\Organizations\Enums\RolePermission;
use App\Domain\Organizations\Models\Organization;
use App\Domain\Organizations\Models\OrganizationInvitation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;

uses(RefreshDatabase::class);

/**
 * The role-checkable permissions and how each maps to a policy ability.
 *
 * The boolean second element indicates whether the ability is checked
 * against an `OrganizationInvitation` (true) or an `Organization` (false).
 * `AcceptInvitation` is intentionally excluded: acceptance is governed by the
 * invitation's email match, not by a held role, and is covered separately.
 *
 * @return array<int, array{0: RolePermission, 1: string, 2: bool}>
 */
function organizationPermissions(): array
{
    return [
        [RolePermission::ViewOrganization, 'view', false],
        [RolePermission::UpdateOrganization, 'update', false],
        [RolePermission::ArchiveOrganization, 'destroy', false],
        [RolePermission::TransferOwnership, 'transferOwnership', false],
        [RolePermission::InviteMembers, 'invite', false],
        [RolePermission::ManageMembers, 'manageMembers', false],
        [RolePermission::ViewAudit, 'viewAudit', false],
        [RolePermission::RevokeInvitation, 'revokeInvitation', true],
    ];
}

function authorizeAgainst(User $actor, string $ability, Organization $organization, OrganizationInvitation $invitation, bool $onInvitation): bool
{
    $model = $onInvitation ? $invitation : $organization;

    return Gate::forUser($actor)->allows($ability, $model);
}

/**
 * Role × action authorization matrix.
 *
 * Every {@see MemberRole} × role-checkable {@see RolePermission} combination
 * is asserted twice: allow (when the truth table grants it) and deny (when it
 * does not). This mirrors docs/PRD.md §3 via {@see RolePermission::grantedTo()}.
 */
it('matches the permission truth table for every role and action', function (): void {
    foreach (MemberRole::cases() as $role) {
        $organization = Organization::factory()->slug('acme-'.$role->value)->create();

        if ($role === MemberRole::Owner) {
            $actor = User::find($organization->owner_id);
        } else {
            $actor = User::factory()->create();
            $organization->members()->create([
                'user_id' => $actor->id,
                'role' => $role->value,
            ]);
        }

        $invitation = OrganizationInvitation::factory()
            ->for($organization)
            ->create(['email' => $actor->email, 'role' => MemberRole::Viewer->value]);

        foreach (organizationPermissions() as [$permission, $ability, $onInvitation]) {
            $expected = RolePermission::allows($role, $permission);
            $actual = authorizeAgainst($actor, $ability, $organization, $invitation, $onInvitation);

            expect($actual)
                ->toBe($expected);
        }
    }
});

it('denies every role-gated action to non-members', function (): void {
    $organization = Organization::factory()->slug('acme-nonmember')->create();
    $outsider = User::factory()->create();

    $invitation = OrganizationInvitation::factory()
        ->for($organization)
        ->create(['email' => $outsider->email, 'role' => MemberRole::Viewer->value]);

    foreach (organizationPermissions() as [$permission, $ability, $onInvitation]) {
        expect(authorizeAgainst($outsider, $ability, $organization, $invitation, $onInvitation))
            ->toBeFalse();
    }
});

it('denies every action to unauthenticated callers', function (): void {
    $organization = Organization::factory()->slug('acme-anon')->create();

    $invitation = OrganizationInvitation::factory()
        ->for($organization)
        ->create(['role' => MemberRole::Viewer->value]);

    foreach (organizationPermissions() as [$permission, $ability, $onInvitation]) {
        $model = $onInvitation ? $invitation : $organization;

        expect(Gate::allows($ability, $model))
            ->toBeFalse();
    }
});

it('allows invitation acceptance only for the matching email', function (): void {
    $organization = Organization::factory()->slug('acme-accept')->create();

    $invitee = User::factory()->create(['email' => 'invitee@example.com']);
    $invitation = OrganizationInvitation::factory()
        ->for($organization)
        ->create(['email' => 'invitee@example.com', 'role' => MemberRole::Viewer->value]);

    // Allow: matching email, pending, unexpired.
    expect(Gate::forUser($invitee)->allows('acceptInvitation', $invitation))->toBeTrue();

    // Deny: wrong email.
    $stranger = User::factory()->create(['email' => 'stranger@example.com']);
    expect(Gate::forUser($stranger)->denies('acceptInvitation', $invitation))->toBeTrue();

    // Deny: expired.
    $invitation->update(['expires_at' => now()->subDay()]);
    expect(Gate::forUser($invitee)->denies('acceptInvitation', $invitation))->toBeTrue();

    // Deny: already accepted.
    $invitation->accepted_at = now();
    $invitation->save();
    expect(Gate::forUser($invitee)->denies('acceptInvitation', $invitation))->toBeTrue();

    // Deny: unauthenticated.
    expect(Gate::denies('acceptInvitation', $invitation))->toBeTrue();
});