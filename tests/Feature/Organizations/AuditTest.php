<?php

declare(strict_types=1);

use App\Domain\Audit\Models\AuditLog;
use App\Domain\Identity\Models\User;
use App\Domain\Organizations\Enums\MemberRole;
use App\Domain\Organizations\Models\Organization;
use App\Infrastructure\Organizations\InvitationToken;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function orgForAudit(User $owner, string $slug = 'acme'): Organization
{
    $organization = Organization::factory()->ownedBy($owner)->slug($slug)->create();

    useTenant($organization);

    return $organization;
}

it('creates an audit row on organization creation', function (): void {
    $owner = User::factory()->create();

    $this->actingAs($owner)
        ->post('/organizations', [
            'name' => 'Acme',
            'slug' => 'acme',
        ]);

    expect(AuditLog::query()->count())->toBe(1)
        ->and(AuditLog::query()->first()->action->value)
        ->toBe('organization.created')
        ->and(AuditLog::query()->first()->actor_id)->toBe($owner->id);
});

it('creates an audit row on role change', function (): void {
    $owner = User::factory()->create();
    $organization = orgForAudit($owner);

    $developer = User::factory()->create();
    $member = $organization->members()->create([
        'user_id' => $developer->id,
        'role' => MemberRole::Viewer->value,
    ]);

    $this->actingAs($owner)
        ->patch("/organizations/$organization->slug/members/$member->id", [
            'role' => 'developer',
        ]);

    expect(AuditLog::query()->where('action', 'member.role_updated')->count())->toBe(1);
});

it('creates an audit row on member removal', function (): void {
    $owner = User::factory()->create();
    $organization = orgForAudit($owner);

    $developer = User::factory()->create();
    $member = $organization->members()->create([
        'user_id' => $developer->id,
        'role' => MemberRole::Developer->value,
    ]);

    $this->actingAs($owner)
        ->delete("/organizations/$organization->slug/members/$member->id");

    expect(AuditLog::query()->where('action', 'member.removed')->count())->toBe(1);
});

it('creates an audit row on owner transfer', function (): void {
    $owner = User::factory()->create();
    $organization = orgForAudit($owner);

    $newOwner = User::factory()->create();
    $newOwnerRow = $organization->members()->create([
        'user_id' => $newOwner->id,
        'role' => MemberRole::Admin->value,
    ]);

    $this->actingAs($owner)
        ->post("/organizations/$organization->slug/transfer", [
            'member_id' => $newOwnerRow->id,
        ]);

    expect(AuditLog::query()->where('action', 'organization.owner_transferred')->count())->toBe(1);
});

it('creates an audit row on invitation accept', function (): void {
    $owner = User::factory()->create();
    $organization = orgForAudit($owner);

    $newcomer = User::factory()->create(['email' => 'dev@example.com']);
    $raw = InvitationToken::random();

    $organization->invitations()->create([
        'email' => 'dev@example.com',
        'role' => MemberRole::Developer->value,
        'token_hash' => InvitationToken::hash($raw),
        'invited_by' => $owner->id,
        'expires_at' => now()->addDays(7),
    ]);

    $this->actingAs($newcomer)
        ->get("/invitations/$raw");

    expect(AuditLog::query()->where('action', 'invitation.accepted')->count())->toBe(1);
});
