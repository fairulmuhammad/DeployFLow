<?php

declare(strict_types=1);

namespace App\Domain\Organizations\Policies;

use App\Domain\Identity\Models\User;
use App\Domain\Organizations\Enums\RolePermission;
use App\Domain\Organizations\Models\Organization;
use App\Domain\Organizations\Models\OrganizationMember;

/**
 * Authorization for organization members.
 *
 * All checks delegate to the centralized truth table in
 * {@see RolePermission::grantedTo()}.
 */
final class OrganizationMemberPolicy
{
    public function view(?User $user, ?Organization $organization, ?OrganizationMember $member): bool
    {
        return $this->check($user, $organization, $member, RolePermission::ViewOrganization);
    }

    public function update(?User $user, ?Organization $organization, ?OrganizationMember $member): bool
    {
        return $this->check($user, $organization, $member, RolePermission::ManageMembers);
    }

    public function destroy(?User $user, ?Organization $organization, ?OrganizationMember $member): bool
    {
        return $this->check($user, $organization, $member, RolePermission::ManageMembers);
    }

    /**
     * @internal
     */
    private function check(?User $user, ?Organization $organization, ?OrganizationMember $member, RolePermission $permission): bool
    {
        if ($user === null || $organization === null || $member === null) {
            return false;
        }

        $membership = $organization->members()
            ->where('user_id', $user->getKey())
            ->first();

        $role = $membership?->role;

        return $role !== null && RolePermission::allows($role, $permission);
    }
}
