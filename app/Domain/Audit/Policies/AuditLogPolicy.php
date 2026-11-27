<?php

declare(strict_types=1);

namespace App\Domain\Audit\Policies;

use App\Domain\Audit\Models\AuditLog;
use App\Domain\Identity\Models\User;
use App\Domain\Organizations\Enums\RolePermission;

/**
 * Authorization for reading the append-only audit trail.
 *
 * Deny by default; requires the `view_audit` permission in the log's
 * organization.
 */
final class AuditLogPolicy
{
    public function view(?User $user, AuditLog $log): bool
    {
        if ($user === null) {
            return false;
        }

        $organization = $log->organization;

        if ($organization === null) {
            return false;
        }

        $member = $organization->members()
            ->where('user_id', $user->getKey())
            ->first();

        $role = $member?->role;

        return $role !== null && RolePermission::allows($role, RolePermission::ViewAudit);
    }
}
