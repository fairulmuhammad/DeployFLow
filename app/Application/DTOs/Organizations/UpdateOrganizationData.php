<?php

declare(strict_types=1);

namespace App\Application\DTOs\Organizations;

/**
 * Input for updating an organization.
 */
final readonly class UpdateOrganizationData
{
    public function __construct(
        public string $name,
    ) {}
}
