<?php

declare(strict_types=1);

namespace App\Domain\Audit\Models;

use App\Domain\Audit\Enums\AuditAction;
use App\Domain\Audit\Enums\AuditActorType;
use App\Domain\Identity\Models\User;
use App\Domain\Organizations\Models\Organization;
use Database\Factories\AuditLogFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Append-only audit trail. Writers go through `AuditRecorder`; direct
 * inserts are rejected in review.
 *
 * `metadata` is sanitized and never contains secret values (docs/SECURITY.md §5).
 *
 * @property AuditActorType $actor_type
 * @property AuditAction $action
 */
#[Fillable([
    'organization_id',
    'actor_id',
    'actor_type',
    'action',
    'resource_type',
    'resource_id',
    'ip_address',
    'user_agent',
    'metadata',
])]
class AuditLog extends Model
{
    /** @use HasFactory<AuditLogFactory> */
    use HasFactory, HasUuids;

    public const CREATED_AT = 'created_at';

    public const UPDATED_AT = null;

    /**
     * The domain namespace does not match Laravel's factory guess, so bind it
     * explicitly.
     */
    protected static function newFactory(): AuditLogFactory
    {
        return AuditLogFactory::new();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'actor_type' => AuditActorType::class,
            'action' => AuditAction::class,
            'metadata' => 'array',
            'created_at' => 'datetime',
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
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
