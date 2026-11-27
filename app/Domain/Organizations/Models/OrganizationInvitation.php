<?php

declare(strict_types=1);

namespace App\Domain\Organizations\Models;

use App\Domain\Identity\Models\User;
use App\Domain\Organizations\Enums\MemberRole;
use App\Domain\Tenancy\BelongsToOrganization;
use App\Domain\Tenancy\TenantContext;
use App\Infrastructure\Organizations\InvitationToken;
use Database\Factories\OrganizationInvitationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * An invitation to join an organization.
 *
 * The raw token is emailed exactly once and is never stored. Only its
 * SHA-256 hash is kept, so a database leak does not leak tokens.
 *
 * @property MemberRole $role
 * @property Carbon $expires_at
 * @property Carbon|null $accepted_at
 */
#[Fillable(['organization_id', 'email', 'role', 'token_hash', 'invited_by', 'expires_at'])]
class OrganizationInvitation extends Model
{
    use BelongsToOrganization;

    /** @use HasFactory<OrganizationInvitationFactory> */
    use HasFactory, HasUuids;

    public const CREATED_AT = 'created_at';

    public const UPDATED_AT = 'updated_at';

    /**
     * The domain namespace does not match Laravel's factory guess, so bind it
     * explicitly.
     */
    protected static function newFactory(): OrganizationInvitationFactory
    {
        return OrganizationInvitationFactory::new();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'role' => MemberRole::class,
            'expires_at' => 'datetime',
            'accepted_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Organization, $this>
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function inviter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invited_by');
    }

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    public function matchesToken(string $raw): bool
    {
        return hash_equals(
            $this->token_hash,
            InvitationToken::hash($raw),
        );
    }

    /**
     * Resolve an invitation from its raw bearer token.
     *
     * The token selects the tenant before any `TenantContext` exists, so the
     * lookup starts from the unscoped `organizations` aggregate and only then
     * queries the tenant-owned row inside the resolved context. An unknown or
     * foreign token returns `null`; it can never read another tenant's row.
     */
    public static function findByRawToken(string $rawToken): ?self
    {
        $hash = InvitationToken::hash($rawToken);

        $organizationId = Organization::query()
            ->join(
                'organization_invitations',
                'organization_invitations.organization_id',
                '=',
                'organizations.id',
            )
            ->where('organization_invitations.token_hash', $hash)
            ->value('organizations.id');

        if ($organizationId === null) {
            return null;
        }

        /** @var self|null $invitation */
        $invitation = app(TenantContext::class)->run(
            (string) $organizationId,
            fn (): ?self => self::query()->where('token_hash', $hash)->first(),
        );

        return $invitation;
    }

    public function markAccepted(): void
    {
        $this->accepted_at = now();
        $this->save();
    }
}
