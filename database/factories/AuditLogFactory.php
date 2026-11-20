<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Audit\Models\AuditLog;
use App\Domain\Identity\Models\User;
use App\Domain\Organizations\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AuditLog>
 */
class AuditLogFactory extends Factory
{
    /**
     * The model this factory builds.
     *
     * @var class-string<AuditLog>
     */
    protected $model = AuditLog::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $organization = Organization::factory()->create();

        return [
            'organization_id' => $organization->getKey(),
            'actor_id' => User::factory()->create()->getKey(),
            'actor_type' => 'user',
            'action' => 'organization.created',
            'resource_type' => 'organization',
            'metadata' => [],
        ];
    }
}
