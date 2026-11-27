<?php

declare(strict_types=1);

namespace App\Domain\Identity\Policies;

use App\Domain\Identity\Models\User;
use App\Domain\Organizations\Models\Organization;

/**
 * Authorization for identity-level actions.
 *
 * Currently only `view_self` is needed; other actions are delegated to
 * the organization context where applicable.
 */
final class UserPolicy
{
    public function viewSelf(?User $user): bool
    {
        return $user !== null;
    }
}
