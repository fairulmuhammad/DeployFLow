<?php

declare(strict_types=1);

use App\Domain\Identity\Models\User;
use App\Domain\Organizations\Enums\MemberRole;
use App\Domain\Organizations\Models\Organization;
use App\Domain\Organizations\Models\OrganizationInvitation;
use App\Infrastructure\Organizations\InvitationToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

function orgForInvitations(User $owner, string $slug = 'acme'): Organization
{
    return Organization::factory()->ownedBy($owner)->slug($slug)->create();
}

function makeInvitation(Organization $organization, string $email, string $role = 'developer'): OrganizationInvitation
{
    $raw = InvitationToken::random();
    $invitation = $organization->invitations()->create([
        'email' => mb_strtolower($email),
        'role' => $role,
        'token_hash' => InvitationToken::hash($raw),
        'invited_by' => $organization->owner_id,
        'expires_at' => now()->addDays(InvitationToken::EXPIRATION_DAYS),
    ]);

    return $invitation;
}

it('lets an admin invite a new member', function (): void {
    $owner = User::factory()->create();
    $organization = orgForInvitations($owner);

    $admin = User::factory()->create();
    $organization->members()->create([
        'user_id' => $admin->id,
        'role' => MemberRole::Admin->value,
    ]);

    $this->actingAs($admin)
        ->post("/organizations/$organization->slug/invitations", [
            'email' => 'dev@example.com',
            'role' => 'developer',
        ])
        ->assertSessionHasNoErrors();

    $invitation = $organization->invitations()->first();

    expect($invitation->email)->toBe('dev@example.com')
        ->and($invitation->role)->toBe(MemberRole::Developer)
        ->and(strlen($invitation->token_hash))->toBe(64);
});

it('rejects inviting a user as the owner role', function (): void {
    $owner = User::factory()->create();
    $organization = orgForInvitations($owner);

    $this->actingAs($owner)
        ->post("/organizations/$organization->slug/invitations", [
            'email' => 'dev@example.com',
            'role' => 'owner',
        ])
        ->assertSessionHasErrors('role');

    expect($organization->invitations()->count())->toBe(0);
});

it('rejects inviting from a viewer', function (): void {
    $owner = User::factory()->create();
    $organization = orgForInvitations($owner);

    $viewer = User::factory()->create();
    $organization->members()->create([
        'user_id' => $viewer->id,
        'role' => MemberRole::Viewer->value,
    ]);

    $this->actingAs($viewer)
        ->post("/organizations/$organization->slug/invitations", [
            'email' => 'dev@example.com',
            'role' => 'developer',
        ])
        ->assertForbidden();

    expect($organization->invitations()->count())->toBe(0);
});

it('allows the invited email to accept through the token URL', function (): void {
    $owner = User::factory()->create();
    $organization = orgForInvitations($owner);

    $newcomer = User::factory()->create([
        'email' => 'dev@example.com',
    ]);

    $invitation = makeInvitation($organization, 'dev@example.com');
    $rawToken = InvitationToken::random();

    // The invitation we created used a random hash; re-create it with a
    // known token for the accept test.
    $invitation->update([
        'token_hash' => InvitationToken::hash($rawToken),
        'email' => 'dev@example.com',
    ]);

    $this->actingAs($newcomer)
        ->get("/invitations/$rawToken")
        ->assertRedirect("/organizations/$organization->slug");

    expect($organization->members()->where('user_id', $newcomer->id)->count())->toBe(1)
        ->and($organization->members()->where('user_id', $newcomer->id)->first()->role)
        ->toBe(MemberRole::Developer);
});

it('blocks a wrong email address from accepting', function (): void {
    $owner = User::factory()->create();
    $organization = orgForInvitations($owner);

    $outsider = User::factory()->create(['email' => 'someone-else@example.com']);
    $invitation = makeInvitation($organization, 'dev@example.com');
    $rawToken = InvitationToken::random();
    $invitation->update(['token_hash' => InvitationToken::hash($rawToken)]);

    $this->actingAs($outsider)
        ->get("/invitations/$rawToken")
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Organizations/Invitation')
            ->where('reason', 'email-mismatch')
        );

    expect($organization->members()->where('user_id', $outsider->id)->exists())->toBeFalse();
});

it('rejects an expired invitation', function (): void {
    $owner = User::factory()->create();
    $organization = orgForInvitations($owner);

    $newcomer = User::factory()->create(['email' => 'dev@example.com']);
    $invitation = makeInvitation($organization, 'dev@example.com');
    $rawToken = InvitationToken::random();
    $invitation->update([
        'token_hash' => InvitationToken::hash($rawToken),
        'expires_at' => now()->subDay(),
    ]);

    $this->actingAs($newcomer)
        ->get("/invitations/$rawToken")
        ->assertInertia(fn (Assert $page) => $page->where('reason', 'expired'));

    expect($organization->members()->where('user_id', $newcomer->id)->exists())->toBeFalse();
});

it('lets an admin revoke a pending invitation', function (): void {
    $owner = User::factory()->create();
    $organization = orgForInvitations($owner);

    $invitation = makeInvitation($organization, 'dev@example.com');

    $this->actingAs($owner)
        ->delete("/organizations/$organization->slug/invitations/$invitation->id")
        ->assertSessionHasNoErrors();

    expect(OrganizationInvitation::query()->find($invitation->id))->toBeNull();
});
