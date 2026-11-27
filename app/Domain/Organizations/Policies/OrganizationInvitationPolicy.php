<?php

declare(strict_types=1);

namespace App\Domain\Organizations\Policies;

use App\Domain\Identity\Models\User;
use App\Domain\Organizations\Enums\RolePermission;
use App\Domain\Organizations\Models\OrganizationInvitation;

/**
 * Authorization for organization invitations.
 *
 * Revocation requires the caller to hold `revoke_invitation` in the inviting
 * organization; acceptance requires the invitation to be pending, unexpired,
 * and addressed to the caller. Deny by default.
 */
final class OrganizationInvitationPolicy
{
    public function revokeInvitation(?User $user, OrganizationInvitation $invitation): bool
    {
        if ($user === null) {
            return false;
        }

        $organization = $invitation->organization;

        if ($organization === null) {
            return false;
        }

        $membership = $organization->members()
            ->where('user_id', $user->getKey())
            ->first();

        $role = $membership?->role;

        return $role !== null && RolePermission::allows($role, RolePermission::RevokeInvitation);
    }

    public function acceptInvitation(?User $user, OrganizationInvitation $invitation): bool
    {
        if ($user === null) {
            return false;
        }

        if ($invitation->accepted_at !== null) {
            return false;
        }

        if ($invitation->isExpired()) {
            return false;
        }

        return strcasecmp($user->email, $invitation->email) === 0;
    }
}
