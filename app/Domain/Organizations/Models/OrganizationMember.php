<?php

declare(strict_types=1);

namespace App\Domain\Organizations\Models;

use App\Domain\Identity\Models\User;
use App\Domain\Organizations\Enums\MemberRole;
use Database\Factories\OrganizationMemberFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A link between a user and an organization with a role.
 *
 * @property MemberRole $role
 */
#[Fillable(['organization_id', 'user_id', 'role'])]
class OrganizationMember extends Model
{
    /** @use HasFactory<OrganizationMemberFactory> */
    use HasFactory, HasUuids;

    public const CREATED_AT = 'created_at';

    public const UPDATED_AT = 'updated_at';

    /**
     * The domain namespace does not match Laravel's factory guess, so bind it
     * explicitly.
     */
    protected static function newFactory(): OrganizationMemberFactory
    {
        return OrganizationMemberFactory::new();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'role' => MemberRole::class,
            'updated_at' => 'datetime',
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
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
