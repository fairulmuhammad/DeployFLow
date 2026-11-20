<?php

declare(strict_types=1);

namespace App\Domain\Audit\Enums;

/**
 * Who wrote an audit row.
 *
 * `worker` and `system` are reserved for M2+ (webhook processing, reaper,
 * deployment engine); M1 only produces `user` rows.
 */
enum AuditActorType: string
{
    case User = 'user';
    case System = 'system';
    case Worker = 'worker';
}
