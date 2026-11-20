<?php

declare(strict_types=1);

namespace App\Infrastructure\Audit;

use App\Domain\Audit\Enums\AuditAction;
use App\Domain\Audit\Models\AuditLog;
use App\Domain\Identity\Models\User;
use App\Domain\Organizations\Models\Organization;
use Illuminate\Http\Request;

/**
 * Single entry point for audit rows. Callers never construct `AuditLog`
 * directly.
 *
 * `metadata` must be JSON-serializable and never contain secret values
 * (docs/SECURITY.md §5).
 */
final class AuditRecorder
{
    /**
     * @param  array<string, mixed>  $metadata
     */
    public function record(
        Organization $organization,
        ?User $actor,
        AuditAction $action,
        string $resourceType,
        ?string $resourceId = null,
        array $metadata = [],
        ?Request $request = null,
    ): AuditLog {
        return AuditLog::create([
            'organization_id' => $organization->getKey(),
            'actor_id' => $actor?->getKey(),
            'actor_type' => $actor === null ? 'system' : 'user',
            'action' => $action->value,
            'resource_type' => $resourceType,
            'resource_id' => $resourceId,
            'ip_address' => $request?->ip(),
            'user_agent' => $request?->userAgent(),
            'metadata' => $metadata,
        ]);
    }
}
