<?php

declare(strict_types=1);

namespace App\Application\DTOs\Organizations;

use App\Domain\Identity\Models\User;

/**
 * Input for transferring ownership.
 */
final readonly class TransferOwnershipData
{
    public function __construct(
        public User $newOwner,
    ) {}
}
