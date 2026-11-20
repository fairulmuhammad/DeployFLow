<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Identity\Models\User;
use App\Domain\Organizations\Models\Organization;
use App\Domain\Organizations\Models\OrganizationMember;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OrganizationMember>
 */
class OrganizationMemberFactory extends Factory
{
    /**
     * The model this factory builds.
     *
     * @var class-string<OrganizationMember>
     */
    protected $model = OrganizationMember::class;

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
            'user_id' => User::factory()->create()->getKey(),
            'role' => 'developer',
        ];
    }
}
