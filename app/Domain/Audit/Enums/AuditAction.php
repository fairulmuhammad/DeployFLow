<?php

declare(strict_types=1);

namespace App\Domain\Audit\Enums;

/**
 * Audited actions. The action column is `varchar(64)`; the enum documents the
 * contract so the UI can filter and humans can reason about history.
 *
 * New actions must be added here and used by their `AuditRecorder` calls.
 */
enum AuditAction: string
{
    case OrganizationCreated = 'organization.created';
    case OrganizationUpdated = 'organization.updated';
    case OrganizationArchived = 'organization.archived';
    case OrganizationOwnerTransferred = 'organization.owner_transferred';
    case MemberRoleUpdated = 'member.role_updated';
    case MemberRemoved = 'member.removed';
    case InvitationCreated = 'invitation.created';
    case InvitationRevoked = 'invitation.revoked';
    case InvitationAccepted = 'invitation.accepted';
}
