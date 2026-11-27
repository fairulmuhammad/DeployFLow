<?php

declare(strict_types=1);

use App\Domain\Identity\Models\User;
use App\Domain\Organizations\Enums\MemberRole;
use App\Domain\Organizations\Models\Organization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

function ownedOrg(User $owner, string $slug = 'acme'): Organization
{
    $organization = Organization::factory()->ownedBy($owner)->slug($slug)->create();
    $organization->refresh();

    useTenant($organization);

    return $organization;
}

it('creates an owner membership row when an organization is made', function (): void {
    $owner = User::factory()->create();
    $organization = ownedOrg($owner, 'acme-invariant');

    $ownerRow = $organization->members()->where('role', 'owner')->first();

    expect($ownerRow)->not->toBeNull()
        ->and($ownerRow->user_id)->toBe($owner->id)
        ->and($organization->owner_id)->toBe($owner->id);
});

it('keeps exactly one owner row per organization', function (): void {
    $owner = User::factory()->create();
    $organization = ownedOrg($owner, 'acme-invariant');

    // A direct second owner row is blocked by the partial unique index.
    expect(function (): void {
        $organization->members()->create([
            'user_id' => User::factory()->create()->id,
            'role' => MemberRole::Owner->value,
        ]);
    })->toThrow(Exception::class);

    expect($organization->members()->where('role', 'owner')->count())->toBe(1);
});

it('keeps the owner canonical across transfer and removal', function (): void {
    $owner = User::factory()->create();
    $organization = ownedOrg($owner, 'acme-invariant');

    $newOwner = User::factory()->create();
    $newOwnerRow = $organization->members()->create([
        'user_id' => $newOwner->id,
        'role' => MemberRole::Admin->value,
    ]);

    DB::transaction(function () use ($organization, $newOwner, $newOwnerRow): void {
        $organization->members()
            ->where('user_id', $organization->owner_id)
            ->first()?->update(['role' => MemberRole::Admin->value]);

        $newOwnerRow->update(['role' => MemberRole::Owner->value]);
        $organization->update(['owner_id' => $newOwner->id]);
    });

    $fresh = $organization->fresh();

    expect($fresh->owner_id)->toBe($newOwner->id)
        ->and($fresh->members()->where('role', 'owner')->count())->toBe(1)
        ->and($fresh->members()->where('role', 'owner')->first()->user_id)->toBe($newOwner->id);
});

it('refuses to delete the last owner', function (): void {
    $owner = User::factory()->create();
    $organization = ownedOrg($owner, 'acme-invariant');

    $ownerRow = $organization->members()->where('role', 'owner')->first();

    expect($ownerRow)->not->toBeNull();

    $this->actingAs($owner)
        ->delete("/organizations/$organization->slug/members/$ownerRow->id")
        ->assertSessionHas('status');

    expect($organization->members()->where('user_id', $owner->id)->first()->role)
        ->toBe(MemberRole::Owner);
});
