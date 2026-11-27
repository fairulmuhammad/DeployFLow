<?php

declare(strict_types=1);

namespace App\Domain\Organizations\Enums;

/**
 * Every RBAC permission recognized by DeployFlow.
 *
 * Each case is a string permission name checked by policy methods and the
 * `EnsureRole` middleware. The truth table is defined by
 * {@see RolePermission::grantedTo()}, which maps each {@see MemberRole} to
 * the permissions that role holds.
 *
 * Permissions are organized by resource area following docs/PRD.md §3.
 */
enum RolePermission: string
{
    // Organization-level permissions
    case ViewOrganization = 'view_organization';
    case UpdateOrganization = 'update_organization';
    case ArchiveOrganization = 'archive_organization';
    case TransferOwnership = 'transfer_ownership';

    // Member permissions
    case InviteMembers = 'invite_members';
    case RevokeInvitation = 'revoke_invitation';
    case AcceptInvitation = 'accept_invitation';
    case ManageMembers = 'manage_members';

    // Audit permissions
    case ViewAudit = 'view_audit';

    // Application permissions (forward-compatible with M2)
    case ManageApplications = 'manage_applications';

    // Environment permissions (forward-compatible with M2)
    case ManageEnvironments = 'manage_environments';

    // Deployment permissions (forward-compatible with M2)
    case TriggerDeployments = 'trigger_deployments';
    case ReadDeployments = 'read_deployments';

    // Secret permissions (forward-compatible with M3)
    case WriteSecrets = 'write_secrets';
    case ReadSecretNames = 'read_secret_names';

    /**
     * Truth table: the permissions granted to each membership role.
     *
     * Based on docs/PRD.md §3:
     * - Owner: full across all areas, incl. transfer/delete org.
     * - Admin: full across all areas; invite/remove non-owner members.
     * - Developer: manage applications / non-secret env config; trigger,
     *   cancel, rollback deployments; write-only secrets.
     * - Viewer: read applications / environments / deployment history;
     *   secret names only.
     *
     * @return array<int, RolePermission>
     */
    public static function grantedTo(MemberRole $role): array
    {
        return match ($role) {
            MemberRole::Owner => [
                self::ViewOrganization,
                self::UpdateOrganization,
                self::ArchiveOrganization,
                self::TransferOwnership,
                self::InviteMembers,
                self::RevokeInvitation,
                self::AcceptInvitation,
                self::ManageMembers,
                self::ViewAudit,
                self::ManageApplications,
                self::ManageEnvironments,
                self::TriggerDeployments,
                self::ReadDeployments,
                self::WriteSecrets,
                self::ReadSecretNames,
            ],
            MemberRole::Admin => [
                self::ViewOrganization,
                self::UpdateOrganization,
                self::InviteMembers,
                self::RevokeInvitation,
                self::AcceptInvitation,
                self::ManageMembers,
                self::ViewAudit,
                self::ManageApplications,
                self::ManageEnvironments,
                self::TriggerDeployments,
                self::ReadDeployments,
                self::WriteSecrets,
                self::ReadSecretNames,
            ],
            MemberRole::Developer => [
                self::ViewOrganization,
                self::ManageApplications,
                self::ManageEnvironments,
                self::TriggerDeployments,
                self::ReadDeployments,
                self::WriteSecrets,
                self::ReadSecretNames,
            ],
            MemberRole::Viewer => [
                self::ViewOrganization,
                self::ReadDeployments,
                self::ReadSecretNames,
            ],
        };
    }

    /**
     * Whether `$role` holds `$permission`.
     */
    public static function allows(MemberRole $role, self $permission): bool
    {
        return in_array($permission, self::grantedTo($role), true);
    }
}
