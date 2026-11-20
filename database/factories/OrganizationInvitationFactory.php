<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Identity\Models\User;
use App\Domain\Organizations\Models\Organization;
use App\Domain\Organizations\Models\OrganizationInvitation;
use App\Infrastructure\Organizations\InvitationToken;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OrganizationInvitation>
 */
class OrganizationInvitationFactory extends Factory
{
    /**
     * The model this factory builds.
     *
     * @var class-string<OrganizationInvitation>
     */
    protected $model = OrganizationInvitation::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $raw = InvitationToken::random();
        $organization = Organization::factory()->create();

        return [
            'organization_id' => $organization->getKey(),
            'email' => mb_strtolower(fake()->unique()->safeEmail()),
            'role' => fake()->randomElement(['admin', 'developer', 'viewer']),
            'token_hash' => InvitationToken::hash($raw),
            'invited_by' => User::factory()->create()->getKey(),
            'expires_at' => now()->addDays(InvitationToken::EXPIRATION_DAYS),
        ];
    }

    /**
     * Override the token hash to match a specific raw token.
     */
    public function withRawToken(string $raw): static
    {
        return $this->state([
            'token_hash' => InvitationToken::hash($raw),
        ]);
    }

    public function expired(): static
    {
        return $this->state([
            'expires_at' => now()->subDay(),
        ]);
    }

    public function accepted(): static
    {
        return $this->state([
            'accepted_at' => now(),
        ]);
    }
}
