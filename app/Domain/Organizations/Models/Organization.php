<?php

declare(strict_types=1);

namespace App\Domain\Organizations\Models;

use App\Domain\Audit\Models\AuditLog;
use App\Domain\Identity\Models\User;
use App\Domain\Organizations\Enums\MemberRole;
use App\Infrastructure\Organizations\OrganizationSlug;
use Database\Factories\OrganizationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A tenant boundary. Every tenant-owned table carries `organization_id`.
 *
 * `owner_id` is canonical: the owner always has a matching
 * `organization_members` row with role `owner`. Owner transfer updates both
 * in one transaction.
 *
 * @property string $slug
 * @property string $name
 * @property string $owner_id
 */
#[Fillable(['name', 'slug', 'owner_id', 'settings'])]
class Organization extends Model
{
    /** @use HasFactory<OrganizationFactory> */
    use HasFactory, HasUuids, SoftDeletes;

    public const CREATED_AT = 'created_at';

    public const UPDATED_AT = 'updated_at';

    /**
     * The domain namespace does not match Laravel's factory guess
     * (`Database\Factories\Domain\Organizations\Models\OrganizationFactory`),
     * so bind it explicitly.
     */
    protected static function newFactory(): OrganizationFactory
    {
        return OrganizationFactory::new();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'settings' => 'array',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
            'deleted_at' => 'datetime',
        ];
    }

    /**
     * @return HasOne<User, $this>
     */
    public function owner(): HasOne
    {
        return $this->hasOne(User::class, 'id', 'owner_id');
    }

    /**
     * @return HasMany<OrganizationMember, $this>
     */
    public function members(): HasMany
    {
        return $this->hasMany(OrganizationMember::class);
    }

    /**
     * @return HasMany<OrganizationInvitation, $this>
     */
    public function invitations(): HasMany
    {
        return $this->hasMany(OrganizationInvitation::class);
    }

    /**
     * @return HasMany<AuditLog, $this>
     */
    public function auditLogs(): HasMany
    {
        return $this->hasMany(AuditLog::class);
    }

    public function roleFor(?User $user, MemberRole ...$roles): bool
    {
        if ($user === null) {
            return false;
        }

        $member = $this->members()->where('user_id', $user->getKey())->first();

        if ($member === null) {
            return false;
        }

        foreach ($roles as $role) {
            if ($member->role === $role) {
                return true;
            }
        }

        return false;
    }

    public function isMember(?User $user): bool
    {
        return $user !== null
            && $this->members()->where('user_id', $user->getKey())->exists();
    }

    /**
     * The current user's role in this organization, or null.
     */
    public function roleForUser(?User $user): ?MemberRole
    {
        if ($user === null) {
            return null;
        }

        $member = $this->members()->where('user_id', $user->getKey())->first();

        return $member?->role;
    }

    /**
     * Resolve an organization by slug, or return null.
     */
    public static function findBySlug(string $slug): ?self
    {
        return self::query()->where('slug', OrganizationSlug::normalize($slug))->first();
    }
}
