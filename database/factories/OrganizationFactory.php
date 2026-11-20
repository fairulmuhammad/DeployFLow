<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Identity\Models\User;
use App\Domain\Organizations\Models\Organization;
use App\Infrastructure\Organizations\OrganizationSlug;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * @extends Factory<Organization>
 */
class OrganizationFactory extends Factory
{
    /**
     * The model this factory builds.
     *
     * @var class-string<Organization>
     */
    protected $model = Organization::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->words(3, true);
        $name = is_array($name) ? implode(' ', $name) : $name;

        return [
            'name' => $name,
            'slug' => $this->nextUniqueSlug($name),
            'owner_id' => User::factory()->create()->getKey(),
            'settings' => [],
        ];
    }

    /**
     * @param  array<string, mixed>  $state
     */
    public function ownedBy(User $user, array $state = []): static
    {
        return $this->state(array_merge($state, ['owner_id' => $user->getKey()]));
    }

    /**
     * @param  array<string, mixed>  $state
     */
    public function withSettings(array $state): static
    {
        return $this->state(['settings' => $state]);
    }

    public function slug(string $slug): static
    {
        return $this->state(['slug' => OrganizationSlug::normalize($slug)]);
    }

    /**
     * Create the model and its owner membership atomically.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function create($attributes = [], ?Model $parent = null): Organization
    {
        $organization = parent::create($attributes, $parent);

        /** @var Organization $organization */
        $organization->members()->create([
            'user_id' => $organization->owner_id,
            'role' => 'owner',
        ]);

        return $organization;
    }

    private function nextUniqueSlug(string $base): string
    {
        $base = OrganizationSlug::normalize($base);

        $slug = $base;

        while (Organization::where('slug', $slug)->exists()) {
            $slug = $base.'-'.Str::lower(Str::random(4));
        }

        return $slug;
    }
}
