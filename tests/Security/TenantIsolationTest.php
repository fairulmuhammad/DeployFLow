<?php

declare(strict_types=1);

use App\Domain\Audit\Models\AuditLog;
use App\Domain\Identity\Models\User;
use App\Domain\Organizations\Enums\MemberRole;
use App\Domain\Organizations\Models\Organization;
use App\Domain\Organizations\Models\OrganizationInvitation;
use App\Domain\Organizations\Models\OrganizationMember;
use App\Domain\Tenancy\TenantContext;
use App\Domain\Tenancy\TenantContextMissing;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * Create an organization and a user holding the given role in it.
 *
 * @return array{0: Organization, 1: User}
 */
function tenantWithMember(string $slug, MemberRole $role): array
{
    $organization = Organization::factory()->slug($slug)->create();

    if ($role === MemberRole::Owner) {
        return [$organization, User::findOrFail($organization->owner_id)];
    }

    $user = User::factory()->create();

    $organization->members()->create([
        'user_id' => $user->getKey(),
        'role' => $role->value,
    ]);

    return [$organization, $user];
}

it('filters tenant-owned queries to the active organization', function (): void {
    [$acme] = tenantWithMember('acme', MemberRole::Owner);
    [$globex] = tenantWithMember('globex', MemberRole::Owner);

    app(TenantContext::class)->for($acme->getKey());

    expect(OrganizationMember::query()->pluck('organization_id')->unique()->values()->all())
        ->toBe([$acme->getKey()])
        ->and(OrganizationMember::query()->where('organization_id', $globex->getKey())->exists())
        ->toBeFalse();
});

it('throws instead of silently reading across tenants when no context is set', function (): void {
    tenantWithMember('acme', MemberRole::Owner);

    app(TenantContext::class)->clear();

    expect(fn () => OrganizationMember::query()->count())
        ->toThrow(TenantContextMissing::class);
});

it('only crosses tenants inside the explicit withoutScope escape', function (): void {
    [$acme] = tenantWithMember('acme', MemberRole::Owner);
    [$globex] = tenantWithMember('globex', MemberRole::Owner);

    app(TenantContext::class)->for($acme->getKey());

    /** @var array<int, string> $organizationIds */
    $organizationIds = app(TenantContext::class)->withoutScope(
        fn (): array => OrganizationMember::query()
            ->pluck('organization_id')
            ->unique()
            ->sort()
            ->values()
            ->all(),
    );

    expect($organizationIds)
        ->toBe(collect([$acme->getKey(), $globex->getKey()])->sort()->values()->all())
        ->and(app(TenantContext::class)->organizationId())->toBe($acme->getKey());
});

it('returns 404 for another organization\'s invitation id (SEC-01)', function (): void {
    [$acme, $owner] = tenantWithMember('acme', MemberRole::Owner);
    [$globex] = tenantWithMember('globex', MemberRole::Owner);

    $foreign = OrganizationInvitation::factory()->for($globex)->create([
        'email' => 'dev@globex.example',
    ]);

    $this->actingAs($owner)
        ->delete("/organizations/$acme->slug/invitations/{$foreign->getKey()}")
        ->assertNotFound();

    $stillThere = app(TenantContext::class)->withoutScope(
        fn (): ?OrganizationInvitation => OrganizationInvitation::query()->find($foreign->getKey()),
    );

    expect($stillThere)->not->toBeNull();
});

it('hides another organization\'s audit rows and members (SEC-02)', function (): void {
    [$acme, $owner] = tenantWithMember('acme', MemberRole::Owner);
    [$globex] = tenantWithMember('globex', MemberRole::Owner);

    $foreignLog = AuditLog::create([
        'organization_id' => $globex->getKey(),
        'actor_id' => null,
        'actor_type' => 'system',
        'action' => 'organization.updated',
        'resource_type' => 'organization',
        'resource_id' => $globex->getKey(),
        'metadata' => [],
        'created_at' => now(),
    ]);

    $foreignMember = app(TenantContext::class)->run(
        $globex->getKey(),
        fn (): OrganizationMember => OrganizationMember::query()
            ->where('user_id', $globex->owner_id)
            ->firstOrFail(),
    );

    app(TenantContext::class)->for($acme->getKey());

    expect(AuditLog::query()->whereKey($foreignLog->getKey())->exists())->toBeFalse();

    $this->actingAs($owner)
        ->delete("/organizations/$acme->slug/members/{$foreignMember->getKey()}")
        ->assertNotFound();
});

it('denies a viewer a mutating tenant action (SEC-03)', function (): void {
    [$acme, $viewer] = tenantWithMember('acme', MemberRole::Viewer);

    $this->actingAs($viewer)
        ->post("/organizations/$acme->slug/invitations", [
            'email' => 'dev@example.com',
            'role' => 'developer',
        ])
        ->assertForbidden();

    $this->actingAs($viewer)
        ->delete("/organizations/$acme->slug")
        ->assertForbidden();

    expect(OrganizationInvitation::query()->count())->toBe(0);
});

it('revokes access immediately when a member is removed (SEC-19)', function (): void {
    [$acme, $owner] = tenantWithMember('acme', MemberRole::Owner);

    $developer = User::factory()->create();
    $member = $acme->members()->create([
        'user_id' => $developer->getKey(),
        'role' => MemberRole::Developer->value,
    ]);

    $this->actingAs($developer)
        ->get("/organizations/$acme->slug")
        ->assertOk();

    $this->actingAs($owner)
        ->delete("/organizations/$acme->slug/members/{$member->getKey()}")
        ->assertSessionHasNoErrors();

    $this->actingAs($developer)
        ->get("/organizations/$acme->slug")
        ->assertForbidden();
});

it('ignores an organization id supplied in request input', function (): void {
    [$acme, $owner] = tenantWithMember('acme', MemberRole::Owner);
    [$globex] = tenantWithMember('globex', MemberRole::Owner);

    $this->actingAs($owner)
        ->post("/organizations/$acme->slug/invitations", [
            'email' => 'dev@example.com',
            'role' => 'developer',
            'organization_id' => $globex->getKey(),
        ])
        ->assertSessionHasNoErrors();

    /** @var OrganizationInvitation $invitation */
    $invitation = app(TenantContext::class)->withoutScope(
        fn (): OrganizationInvitation => OrganizationInvitation::query()
            ->where('email', 'dev@example.com')
            ->firstOrFail(),
    );

    expect($invitation->organization_id)->toBe($acme->getKey());
});
