<?php

declare(strict_types=1);

use App\Domain\Identity\Models\User;
use App\Domain\Organizations\Enums\MemberRole;
use App\Domain\Organizations\Models\Organization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

function makeOwner(array $overrides = []): User
{
    return User::factory()->create($overrides);
}

function makeOrganization(User $owner, string $slug = 'acme'): Organization
{
    $organization = Organization::factory()
        ->ownedBy($owner)
        ->slug($slug)
        ->create();

    useTenant($organization);

    return $organization;
}

it('lists the organizations the user belongs to', function (): void {
    $owner = makeOwner();
    $organization = makeOrganization($owner);

    $this->actingAs($owner)
        ->get('/organizations')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Organizations/Index')
            ->where('organizations.0.slug', $organization->slug)
            ->where('organizations.0.role', 'owner')
        );
});

it('lets a verified user create an organization', function (): void {
    $owner = makeOwner();

    $response = $this->actingAs($owner)
        ->post('/organizations', [
            'name' => 'Acme',
            'slug' => 'acme',
        ]);

    $organization = Organization::query()->where('slug', 'acme')->first();

    expect($organization)->not->toBeNull()
        ->and($organization->owner_id)->toBe($owner->id)
        ->and($organization->members()->count())->toBe(1)
        ->and($organization->members()->first()->role)->toBe(MemberRole::Owner);

    $response->assertRedirect(route('organizations.show', ['organization' => 'acme']));
});

it('auto-generates a slug when none is given', function (): void {
    $owner = makeOwner();

    $this->actingAs($owner)
        ->post('/organizations', ['name' => 'Acme Corp'])
        ->assertRedirect();

    expect(Organization::query()->count())->toBe(1);
    $slug = Organization::query()->first()->slug;

    expect($slug)->toStartWith('acme-corp');
});

it('rejects duplicate slugs', function (): void {
    $owner = makeOwner();
    makeOrganization($owner);

    $this->actingAs($owner)
        ->post('/organizations', ['name' => 'Second', 'slug' => 'acme'])
        ->assertRedirect();

    expect(Organization::query()->where('slug', 'acme')->count())->toBe(1);
    expect(Organization::query()->count())->toBe(2);
});

it('blocks unverified users from creating an organization', function (): void {
    $unverified = User::factory()->unverified()->create();

    $this->actingAs($unverified)
        ->post('/organizations', ['name' => 'Acme'])
        ->assertForbidden();

    expect(Organization::query()->count())->toBe(0);
});

it('shows an organization only to its members', function (): void {
    $owner = makeOwner();
    $organization = makeOrganization($owner, 'acme-show');
    $outsider = makeOwner();

    $this->actingAs($outsider)
        ->get('/organizations/'.$organization->slug)
        ->assertForbidden();

    $this->actingAs($owner)
        ->get('/organizations/'.$organization->slug)
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Organizations/Show')
            ->where('organization.slug', $organization->slug)
        );
});

it('archives an organization only by the owner', function (): void {
    $owner = makeOwner();
    $organization = makeOrganization($owner);
    $admin = makeOwner();
    $organization->members()->create([
        'user_id' => $admin->id,
        'role' => MemberRole::Admin->value,
    ]);

    $this->actingAs($admin)
        ->delete('/organizations/'.$organization->slug)
        ->assertForbidden();

    $this->actingAs($owner)
        ->delete('/organizations/'.$organization->slug)
        ->assertRedirect(route('organizations.index'));

    expect(Organization::withTrashed()->find($organization->id))->not->toBeNull()
        ->and(Organization::find($organization->id))->toBeNull();
});
