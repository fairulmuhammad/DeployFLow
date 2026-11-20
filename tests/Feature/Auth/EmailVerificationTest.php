<?php

declare(strict_types=1);

use App\Domain\Identity\Models\User;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

it('renders the verification notice for an unverified user', function (): void {
    $user = User::factory()->unverified()->create();

    $this->actingAs($user)
        ->get('/verify-email')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Auth/VerifyEmail'));
});

it('redirects a verified user away from the verification notice', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get('/verify-email')
        ->assertRedirect(route('home'));
});

it('blocks unverified users from verified-only routes', function (): void {
    $user = User::factory()->unverified()->create();

    $this->actingAs($user)
        ->get('/settings/profile')
        ->assertRedirect(route('verification.notice'));
});

it('verifies an email address through a signed link', function (): void {
    $user = User::factory()->unverified()->create();

    $url = URL::temporarySignedRoute('verification.verify', now()->addMinutes(60), [
        'id' => $user->id,
        'hash' => sha1($user->email),
    ]);

    $this->actingAs($user)
        ->get($url)
        ->assertRedirect(route('home'));

    expect($user->fresh()?->hasVerifiedEmail())->toBeTrue();
});

it('rejects a signed link with the wrong hash', function (): void {
    $user = User::factory()->unverified()->create();

    $url = URL::temporarySignedRoute('verification.verify', now()->addMinutes(60), [
        'id' => $user->id,
        'hash' => sha1('someone-else@example.com'),
    ]);

    $this->actingAs($user)
        ->get($url)
        ->assertForbidden();

    expect($user->fresh()?->hasVerifiedEmail())->toBeFalse();
});

it('resends the verification link at most once per minute', function (): void {
    Notification::fake();

    $user = User::factory()->unverified()->create();

    $this->actingAs($user)
        ->post('/email/verification-notification')
        ->assertRedirect();

    Notification::assertSentTo($user, VerifyEmail::class);

    $this->actingAs($user)
        ->post('/email/verification-notification')
        ->assertStatus(429);
});
