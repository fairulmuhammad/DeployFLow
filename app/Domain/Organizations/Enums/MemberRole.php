<?php

declare(strict_types=1);

namespace App\Domain\Organizations\Enums;

/**
 * Membership roles for an organization.
 *
 * The permission matrix lives in docs/PRD.md §3 and is enforced by the
 * OrganizationPolicy (docs/SECURITY.md §Authorization matrix).
 */
enum MemberRole: string
{
    case Owner = 'owner';
    case Admin = 'admin';
    case Developer = 'developer';
    case Viewer = 'viewer';

    /**
     * @return array<int, self>
     */
    public static function invitable(): array
    {
        return [
            self::Admin,
            self::Developer,
            self::Viewer,
        ];
    }

    /**
     * A member can be managed (role change / removal) by an admin or owner.
     * The owner row is managed only through owner transfer.
     */
    public function isManageable(): bool
    {
        return $this !== self::Owner;
    }
}
