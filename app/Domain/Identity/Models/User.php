<?php

declare(strict_types=1);

namespace App\Domain\Identity\Models;

use App\Domain\Organizations\Models\Organization;
use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

/**
 * A person with a DeployFlow account.
 *
 * Identity is global; tenancy is expressed through `organization_members`
 * (DF-006). Authorization always happens in an organization context.
 */
#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasUuids, Notifiable;

    /**
     * The domain namespace does not match Laravel's factory guess
     * (`Database\Factories\Domain\Identity\Models\UserFactory`), so bind it
     * explicitly.
     */
    protected static function newFactory(): UserFactory
    {
        return UserFactory::new();
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /**
     * Organizations the user belongs to, with the user's role on the pivot.
     *
     * This is the cross-tenant query behind the organization list. It joins
     * the membership table as a pivot rather than querying the tenant-owned
     * `OrganizationMember` model, so no tenant context is required.
     *
     * @return BelongsToMany<Organization, $this>
     */
    public function organizations(): BelongsToMany
    {
        return $this->belongsToMany(Organization::class, 'organization_members')
            ->withPivot('role')
            ->withTimestamps();
    }
}
