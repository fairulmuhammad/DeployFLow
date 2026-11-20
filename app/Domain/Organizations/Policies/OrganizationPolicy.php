<?php

declare(strict_types=1);

namespace App\Domain\Organizations\Policies;

use App\Domain\Identity\Models\User;
use App\Domain\Organizations\Enums\MemberRole;
use App\Domain\Organizations\Models\Organization;
use App\Domain\Organizations\Models\OrganizationInvitation;

/**
 * Authorization for organizations, members, and invitations.
 *
 * Every method is deny-by-default; an unimplemented method means `false`.
 * The truth table comes from docs/PRD.md §3 and docs/API.md §3.
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
        return $organization->isMember($user);
    }

    public function update(?User $user, Organization $organization): bool
    {
        return $organization->roleFor($user, MemberRole::Owner, MemberRole::Admin);
    }

    public function destroy(?User $user, Organization $organization): bool
    {
        return $this->isOwner($user, $organization);
    }

    public function invite(?User $user, Organization $organization): bool
    {
        return $organization->roleFor($user, MemberRole::Owner, MemberRole::Admin);
    }

    public function revokeInvitation(?User $user, OrganizationInvitation $invitation): bool
    {
        $organization = $invitation->organization;

        return $organization !== null && $this->invite($user, $organization);
    }

    public function acceptInvitation(?User $user, OrganizationInvitation $invitation): bool
    {
        if ($user === null) {
            return false;
        }

        if ($invitation->isExpired()) {
            return false;
        }

        return strcasecmp($user->email, $invitation->email) === 0;
    }

    public function manageMembers(?User $user, Organization $organization): bool
    {
        return $this->invite($user, $organization);
    }

    public function transferOwnership(?User $user, Organization $organization): bool
    {
        return $this->isOwner($user, $organization);
    }

    public function isOwner(?User $user, Organization $organization): bool
    {
        return $user !== null
            && $organization->owner_id === $user->getKey()
            && $organization->roleFor($user, MemberRole::Owner);
    }
}
