<?php

declare(strict_types=1);

use App\Domain\Organizations\Models\Organization;
use App\Domain\Tenancy\TenantContext;
use Tests\TestCase;

pest()->extend(TestCase::class)->in('Feature', 'Security');

/**
 * Start the tenant context for a test that queries tenant-owned models
 * directly (outside an HTTP request).
 */
function useTenant(Organization $organization): void
{
    app(TenantContext::class)->for($organization->getKey());
}
