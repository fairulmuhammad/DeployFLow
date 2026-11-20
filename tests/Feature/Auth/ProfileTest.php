<?php

declare(strict_types=1);

use App\Domain\Identity\Models\User;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

it('renders the profile screen for a verified user', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get('/settings/profile')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Settings/Profile')
            ->where('mustVerifyEmail', true)
        );
});

it('updates the name without touching the email verification state', function (): void {
    $user = User::factory()->create(['name' => 'Old Name']);

    $this->actingAs($user)
        ->patch('/settings/profile', [
            'name' => 'New Name',
            'email' => $user->email,
        ])
        ->assertSessionHasNoErrors();

    expect($user->fresh()?->name)->toBe('New Name')
        ->and($user->fresh()?->email_verified_at)->not->toBeNull();
});

it('requires a fresh verification after an email change', function (): void {
    Notification::fake();

    $user = User::factory()->create();

    $this->actingAs($user)
        ->patch('/settings/profile', [
            'name' => $user->name,
            'email' => 'new-address@example.com',
        ])
        ->assertRedirect(route('verification.notice'));

    $fresh = $user->fresh();

    expect($fresh?->email)->toBe('new-address@example.com')
        ->and($fresh?->email_verified_at)->toBeNull();

    Notification::assertSentTo($fresh, VerifyEmail::class);
});

it('updates the password when the current password is correct', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->patch('/settings/profile', [
            'name' => $user->name,
            'email' => $user->email,
            'current_password' => 'password',
            'password' => 'a-brand-new-passphrase',
            'password_confirmation' => 'a-brand-new-passphrase',
        ])
        ->assertSessionHasNoErrors();

    expect(Hash::check('a-brand-new-passphrase', $user->fresh()?->password ?? ''))->toBeTrue();
});

it('rejects a password change with the wrong current password', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->patch('/settings/profile', [
            'name' => $user->name,
            'email' => $user->email,
            'current_password' => 'not-my-password',
            'password' => 'a-brand-new-passphrase',
            'password_confirmation' => 'a-brand-new-passphrase',
        ])
        ->assertSessionHasErrors('current_password');

    expect(Hash::check('password', $user->fresh()?->password ?? ''))->toBeTrue();
});

it('never exposes the password hash in the profile response', function (): void {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get('/settings/profile');

    $response->assertOk();
    $response->assertDontSee($user->password, false);
});
