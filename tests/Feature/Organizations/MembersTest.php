<?php

declare(strict_types=1);

use App\Domain\Identity\Models\User;
use App\Domain\Organizations\Enums\MemberRole;
use App\Domain\Organizations\Models\Organization;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function orgForMembers(User $owner): Organization
{
    $organization = Organization::factory()->ownedBy($owner)->slug('acme')->create();

    useTenant($organization);

    return $organization;
}

it('lets an admin change a non-owner member role', function (): void {
    $owner = User::factory()->create();
    $organization = orgForMembers($owner);

    $admin = User::factory()->create();
    $organization->members()->create([
        'user_id' => $admin->id,
        'role' => MemberRole::Admin->value,
    ]);

    $developer = User::factory()->create();
    $member = $organization->members()->create([
        'user_id' => $developer->id,
        'role' => MemberRole::Viewer->value,
    ]);

    $this->actingAs($admin)
        ->patch("/organizations/$organization->slug/members/$member->id", [
            'role' => 'developer',
        ])
        ->assertSessionHasNoErrors();

    expect($member->fresh()->role)->toBe(MemberRole::Developer);
});

it('rejects role changes by a viewer', function (): void {
    $owner = User::factory()->create();
    $organization = orgForMembers($owner);

    $viewer = User::factory()->create();
    $viewerMember = $organization->members()->create([
        'user_id' => $viewer->id,
        'role' => MemberRole::Viewer->value,
    ]);

    $this->actingAs($viewer)
        ->patch("/organizations/$organization->slug/members/$viewerMember->id", [
            'role' => 'admin',
        ])
        ->assertForbidden();

    expect($viewerMember->fresh()->role)->toBe(MemberRole::Viewer);
});

it('does not let a member change the owner row', function (): void {
    $owner = User::factory()->create();
    $organization = orgForMembers($owner);

    $admin = User::factory()->create();
    $organization->members()->create([
        'user_id' => $admin->id,
        'role' => MemberRole::Admin->value,
    ]);

    $ownerRow = $organization->members()->first();

    $this->actingAs($admin)
        ->patch("/organizations/$organization->slug/members/$ownerRow->id", [
            'role' => 'developer',
        ])
        ->assertSessionHas('status');

    expect($ownerRow->fresh()->role)->toBe(MemberRole::Owner);
});

it('removes a member without touching the owner', function (): void {
    $owner = User::factory()->create();
    $organization = orgForMembers($owner);

    $developer = User::factory()->create();
    $member = $organization->members()->create([
        'user_id' => $developer->id,
        'role' => MemberRole::Developer->value,
    ]);

    $admin = User::factory()->create();
    $organization->members()->create([
        'user_id' => $admin->id,
        'role' => MemberRole::Admin->value,
    ]);

    $this->actingAs($admin)
        ->delete("/organizations/$organization->slug/members/$member->id")
        ->assertSessionHasNoErrors();

    expect($organization->members()->where('user_id', $developer->id)->exists())->toBeFalse()
        ->and($organization->members()->where('role', 'owner')->count())->toBe(1);
});

it('preserves exactly one owner after a transfer', function (): void {
    $owner = User::factory()->create();
    $organization = orgForMembers($owner);

    $newOwner = User::factory()->create();
    $newOwnerRow = $organization->members()->create([
        'user_id' => $newOwner->id,
        'role' => MemberRole::Admin->value,
    ]);

    $this->actingAs($owner)
        ->post("/organizations/$organization->slug/transfer", [
            'member_id' => $newOwnerRow->id,
        ])
        ->assertSessionHasNoErrors();

    $fresh = $organization->fresh();

    expect($fresh->owner_id)->toBe($newOwner->id)
        ->and($fresh->members()->where('role', 'owner')->count())->toBe(1)
        ->and($fresh->members()->where('role', 'owner')->first()->user_id)->toBe($newOwner->id);
});

it('rejects a transfer to a non-member', function (): void {
    $owner = User::factory()->create();
    $organization = orgForMembers($owner);

    $outsider = User::factory()->create();

    $this->actingAs($owner)
        ->post("/organizations/$organization->slug/transfer", [
            'member_id' => $outsider->id,
        ])
        ->assertSessionHasErrors('member_id');

    expect($organization->fresh()->owner_id)->toBe($owner->id);
});
