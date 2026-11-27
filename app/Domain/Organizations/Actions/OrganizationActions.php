<?php

declare(strict_types=1);

namespace App\Domain\Organizations\Actions;

use App\Application\DTOs\Organizations\CreateOrganizationData;
use App\Application\DTOs\Organizations\TransferOwnershipData;
use App\Application\DTOs\Organizations\UpdateOrganizationData;
use App\Domain\Audit\Enums\AuditAction;
use App\Domain\Identity\Models\User;
use App\Domain\Organizations\Enums\MemberRole;
use App\Domain\Organizations\Models\Organization;
use App\Domain\Organizations\Models\OrganizationInvitation;
use App\Domain\Organizations\Models\OrganizationMember;
use App\Domain\Tenancy\TenantContext;
use App\Infrastructure\Audit\AuditRecorder;
use App\Infrastructure\Organizations\InvitationToken;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * All organization mutations in a single place.
 *
 * The owner invariant is enforced at three points:
 *
 * - `create()` makes the creator the owner.
 * - `update()` never touches the owner row.
 * - `transferOwnership()` updates both rows in one transaction.
 */
final class OrganizationActions
{
    public function __construct(
        private readonly AuditRecorder $audits,
        private readonly TenantContext $tenant,
    ) {}

    public function create(User $actor, CreateOrganizationData $data, ?Request $request = null): Organization
    {
        return DB::transaction(function () use ($actor, $data, $request): Organization {
            $slug = $this->uniqueSlug($data->slug());

            $organization = Organization::create([
                'name' => $data->name,
                'slug' => $slug,
                'owner_id' => $actor->getKey(),
                'settings' => [],
            ]);

            $this->tenant->for($organization->getKey());

            $organization->members()->create([
                'user_id' => $actor->getKey(),
                'role' => MemberRole::Owner->value,
            ]);

            $this->audits->record(
                organization: $organization,
                actor: $actor,
                action: AuditAction::OrganizationCreated,
                resourceType: 'organization',
                resourceId: $organization->getKey(),
                metadata: ['name' => $data->name, 'slug' => $organization->slug],
                request: $request,
            );

            return $organization;
        });
    }

    /**
     * Ensure the requested slug is unique across organizations, appending a
     * short random suffix on collision.
     */
    private function uniqueSlug(string $base): string
    {
        $slug = $base;

        while (Organization::where('slug', $slug)->exists()) {
            $slug = $base.'-'.Str::lower(Str::random(4));
        }

        return $slug;
    }

    public function update(Organization $organization, UpdateOrganizationData $data, ?User $actor = null, ?Request $request = null): Organization
    {
        $this->tenant->for($organization->getKey());

        $organization->update(['name' => $data->name]);

        if ($actor !== null) {
            $this->audits->record(
                organization: $organization,
                actor: $actor,
                action: AuditAction::OrganizationUpdated,
                resourceType: 'organization',
                resourceId: $organization->getKey(),
                metadata: ['name' => $data->name],
                request: $request,
            );
        }

        return $organization;
    }

    public function destroy(Organization $organization, ?User $actor = null, ?Request $request = null): void
    {
        $this->tenant->for($organization->getKey());

        $organization->delete();

        if ($actor !== null) {
            $this->audits->record(
                organization: $organization,
                actor: $actor,
                action: AuditAction::OrganizationArchived,
                resourceType: 'organization',
                resourceId: $organization->getKey(),
                metadata: ['name' => $organization->name],
                request: $request,
            );
        }
    }

    /**
     * @throws RuntimeException
     */
    public function transferOwnership(Organization $organization, TransferOwnershipData $data, User $actor, ?Request $request = null): Organization
    {
        $this->tenant->for($organization->getKey());

        $newOwner = $data->newOwner;

        return DB::transaction(function () use ($organization, $newOwner, $actor, $request): Organization {
            $newOwnerRow = $organization->members()->where('user_id', $newOwner->getKey())->first();

            if ($newOwnerRow === null) {
                throw new RuntimeException(
                    'Owner transfer requires the target to be a member of the organization.'
                );
            }

            $organization->members()
                ->where('user_id', $organization->owner_id)
                ->first()?->update(['role' => MemberRole::Admin->value]);

            $newOwnerRow->update(['role' => MemberRole::Owner->value]);
            $organization->update(['owner_id' => $newOwner->getKey()]);

            $this->audits->record(
                organization: $organization,
                actor: $actor,
                action: AuditAction::OrganizationOwnerTransferred,
                resourceType: 'organization',
                resourceId: $organization->getKey(),
                metadata: [
                    'previous_owner' => $actor->getKey(),
                    'new_owner' => $newOwner->getKey(),
                ],
                request: $request,
            );

            return $organization->refresh();
        });
    }

    /**
     * Create an invitation. The raw token is returned on the model as
     * `raw_token` so the caller can surface it (email or UI).
     *
     * @throws RuntimeException
     */
    public function invite(
        Organization $organization,
        string $email,
        MemberRole $role,
        User $actor,
        ?Request $request = null,
    ): OrganizationInvitation {
        $this->tenant->for($organization->getKey());

        $rawToken = InvitationToken::random();

        $invitation = new OrganizationInvitation([
            'organization_id' => $organization->getKey(),
            'email' => mb_strtolower(trim($email)),
            'role' => $role->value,
            'token_hash' => InvitationToken::hash($rawToken),
            'invited_by' => $actor->getKey(),
            'expires_at' => now()->addDays(InvitationToken::EXPIRATION_DAYS),
        ]);

        $invitation->save();
        $invitation->setAttribute('raw_token', $rawToken);

        $this->audits->record(
            organization: $organization,
            actor: $actor,
            action: AuditAction::InvitationCreated,
            resourceType: 'invitation',
            resourceId: $invitation->getKey(),
            metadata: ['email' => $invitation->email, 'role' => $role->value],
            request: $request,
        );

        return $invitation;
    }

    public function revokeInvitation(OrganizationInvitation $invitation, User $actor, ?Request $request = null): void
    {
        $organization = $invitation->organization;

        if ($organization === null) {
            throw new RuntimeException('Invitation has no organization.');
        }

        $this->tenant->for($organization->getKey());

        $invitation->delete();

        $this->audits->record(
            organization: $organization,
            actor: $actor,
            action: AuditAction::InvitationRevoked,
            resourceType: 'invitation',
            resourceId: $invitation->getKey(),
            metadata: ['email' => $invitation->email],
            request: $request,
        );
    }

    /**
     * @throws RuntimeException
     */
    public function acceptInvitation(
        OrganizationInvitation $invitation,
        User $user,
        ?string $rawToken = null,
        ?Request $request = null,
    ): OrganizationMember {
        return DB::transaction(function () use ($invitation, $user, $rawToken, $request): OrganizationMember {
            $organization = $invitation->organization;

            if ($organization === null) {
                throw new RuntimeException('Invitation has no organization.');
            }

            if ($invitation->accepted_at !== null) {
                throw new RuntimeException('Invitation already accepted.');
            }

            if ($invitation->isExpired()) {
                throw new RuntimeException('Invitation expired.');
            }

            $this->tenant->for($organization->getKey());

            if ($rawToken !== null && ! $invitation->matchesToken($rawToken)) {
                throw new RuntimeException('Invitation token does not match.');
            }

            $invitation->markAccepted();

            $existing = $organization->members()->where('user_id', $user->getKey())->first();

            if ($existing !== null) {
                return $existing;
            }

            $member = $organization->members()->make([
                'user_id' => $user->getKey(),
                'role' => $invitation->role->value,
            ]);

            $member->save();

            $this->audits->record(
                organization: $organization,
                actor: $user,
                action: AuditAction::InvitationAccepted,
                resourceType: 'member',
                resourceId: $member->getKey(),
                metadata: ['email' => $user->email, 'role' => $invitation->role->value],
                request: $request,
            );

            return $member;
        });
    }

    /**
     * @throws RuntimeException
     */
    public function updateMemberRole(
        Organization $organization,
        OrganizationMember $member,
        MemberRole $role,
        User $actor,
        ?Request $request = null,
    ): OrganizationMember {
        $this->tenant->for($organization->getKey());

        if ($member->role === MemberRole::Owner) {
            throw new RuntimeException(
                'The owner role cannot be changed directly. Use owner transfer instead.'
            );
        }

        $from = $member->role;
        $member->update(['role' => $role->value]);

        $this->audits->record(
            organization: $organization,
            actor: $actor,
            action: AuditAction::MemberRoleUpdated,
            resourceType: 'member',
            resourceId: $member->getKey(),
            metadata: [
                'member_user' => $member->user_id,
                'from' => $from->value,
                'to' => $role->value,
            ],
            request: $request,
        );

        return $member->refresh();
    }

    /**
     * @throws RuntimeException
     */
    public function removeMember(
        Organization $organization,
        OrganizationMember $member,
        User $actor,
        ?Request $request = null,
    ): void {
        $this->tenant->for($organization->getKey());

        if ($member->role === MemberRole::Owner) {
            throw new RuntimeException(
                'The last owner cannot be removed. Transfer ownership first.'
            );
        }

        $this->audits->record(
            organization: $organization,
            actor: $actor,
            action: AuditAction::MemberRemoved,
            resourceType: 'member',
            resourceId: $member->getKey(),
            metadata: ['member_user' => $member->user_id],
            request: $request,
        );

        $member->delete();
    }
}
