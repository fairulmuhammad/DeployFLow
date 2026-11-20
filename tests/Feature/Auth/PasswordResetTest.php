<?php

declare(strict_types=1);

use App\Domain\Identity\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

it('renders the forgot-password screen', function (): void {
    $this->get('/forgot-password')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Auth/ForgotPassword'));
});

it('sends a reset link for a known address', function (): void {
    Notification::fake();

    $user = User::factory()->create();

    $this->post('/forgot-password', ['email' => $user->email])
        ->assertSessionHas('status');

    Notification::assertSentTo($user, ResetPassword::class);
});

it('returns the same generic response for an unknown address', function (): void {
    Notification::fake();

    $this->post('/forgot-password', ['email' => 'nobody@example.com'])
        ->assertSessionHas('status');

    Notification::assertNothingSent();
});

it('resets a password with a valid token', function (): void {
    $user = User::factory()->create();
    $token = Password::broker()->createToken($user);

    $this->post('/reset-password', [
        'token' => $token,
        'email' => $user->email,
        'password' => 'brand-new-passphrase',
        'password_confirmation' => 'brand-new-passphrase',
    ])->assertRedirect(route('login'));

    expect(Hash::check('brand-new-passphrase', $user->fresh()?->password ?? ''))->toBeTrue();
});

it('rejects a reset token that was already used', function (): void {
    $user = User::factory()->create();
    $token = Password::broker()->createToken($user);

    $payload = [
        'token' => $token,
        'email' => $user->email,
        'password' => 'brand-new-passphrase',
        'password_confirmation' => 'brand-new-passphrase',
    ];

    $this->post('/reset-password', $payload)->assertRedirect(route('login'));

    $this->post('/reset-password', $payload)->assertSessionHasErrors('email');
});

it('rejects an expired reset token', function (): void {
    $user = User::factory()->create();
    $token = Password::broker()->createToken($user);

    $this->travel(61)->minutes();

    $this->post('/reset-password', [
        'token' => $token,
        'email' => $user->email,
        'password' => 'brand-new-passphrase',
        'password_confirmation' => 'brand-new-passphrase',
    ])->assertSessionHasErrors('email');

    expect(Hash::check('password', $user->fresh()?->password ?? ''))->toBeTrue();
});

it('rejects a weak replacement password', function (): void {
    $user = User::factory()->create();
    $token = Password::broker()->createToken($user);

    $this->post('/reset-password', [
        'token' => $token,
        'email' => $user->email,
        'password' => 'short',
        'password_confirmation' => 'short',
    ])->assertSessionHasErrors('password');

    expect(Hash::check('password', $user->fresh()?->password ?? ''))->toBeTrue();
});

it('throttles password reset requests at three per minute per email and IP', function (): void {
    for ($attempt = 1; $attempt <= 3; $attempt++) {
        $this->post('/forgot-password', ['email' => 'nobody@example.com'])
            ->assertSessionHas('status');
    }

    $this->post('/forgot-password', ['email' => 'nobody@example.com'])
        ->assertStatus(429);
});
