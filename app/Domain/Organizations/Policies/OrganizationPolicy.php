<?php

declare(strict_types=1);

namespace App\Domain\Organizations\Policies;

use App\Domain\Identity\Models\User;
use App\Domain\Organizations\Enums\MemberRole;
use App\Domain\Organizations\Enums\RolePermission;
use App\Domain\Organizations\Models\Organization;

/**
 * Authorization for organizations, members, and invitations.
 *
 * Every method is deny-by-default; an unimplemented method means `false`.
 * The truth table comes from docs/PRD.md §3 and is centralized in
 * {@see RolePermission::grantedTo()}.
 */
final class OrganizationPolicy
{
    /**
     * The caller must be a verified user with no active membership
     * requirement — any authenticated verified user may create.
     */
    public function create(?User $user): bool
    {
        return $user !== null && $user->hasVerifiedEmail();
    }

    public function view(?User $user, Organization $organization): bool
    {
        return $this->check($user, $organization, RolePermission::ViewOrganization);
    }

    public function update(?User $user, Organization $organization): bool
    {
        return $this->check($user, $organization, RolePermission::UpdateOrganization);
    }

    public function destroy(?User $user, Organization $organization): bool
    {
        return $this->check($user, $organization, RolePermission::ArchiveOrganization);
    }

    public function invite(?User $user, Organization $organization): bool
    {
        return $this->check($user, $organization, RolePermission::InviteMembers);
    }

    public function manageMembers(?User $user, Organization $organization): bool
    {
        return $this->check($user, $organization, RolePermission::ManageMembers);
    }

    public function transferOwnership(?User $user, Organization $organization): bool
    {
        return $this->isOwner($user, $organization)
            && $this->check($user, $organization, RolePermission::TransferOwnership);
    }

    public function viewAudit(?User $user, Organization $organization): bool
    {
        return $this->check($user, $organization, RolePermission::ViewAudit);
    }

    /**
     * @internal
     */
    public function check(?User $user, Organization $organization, RolePermission $permission): bool
    {
        if ($user === null) {
            return false;
        }

        $member = $organization->members()
            ->where('user_id', $user->getKey())
            ->first();

        $role = $member?->role;

        return $role !== null && RolePermission::allows($role, $permission);
    }

    /**
     * Owner-specific shortcut for `transferOwnership` which requires the
     * caller to be the *canonical* owner (owner_id + owner role).
     */
    private function isOwner(?User $user, Organization $organization): bool
    {
        return $user !== null
            && $organization->owner_id === $user->getKey()
            && $organization->roleFor($user, MemberRole::Owner);
    }
}
