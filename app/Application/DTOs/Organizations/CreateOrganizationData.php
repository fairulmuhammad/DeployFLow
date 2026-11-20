<?php

declare(strict_types=1);

namespace App\Application\DTOs\Organizations;

use App\Infrastructure\Organizations\OrganizationSlug;

/**
 * Input for creating an organization.
 */
final readonly class CreateOrganizationData
{
    public function __construct(
        public string $name,
        public ?string $slug = null,
    ) {}

    /**
     * The slug to persist: caller-provided, or derived from the name.
     */
    public function slug(): string
    {
        if ($this->slug !== null) {
            return OrganizationSlug::normalize($this->slug);
        }

        return OrganizationSlug::normalize($this->name);
    }
}
