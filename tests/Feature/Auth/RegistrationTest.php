<?php

declare(strict_types=1);

use App\Domain\Identity\Models\User;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

it('renders the registration screen', function (): void {
    $this->get('/register')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Auth/Register'));
});

it('registers a user, signs them in, and sends a verification link', function (): void {
    Notification::fake();

    $response = $this->post('/register', [
        'name' => 'Ada Lovelace',
        'email' => 'Ada@Example.com',
        'password' => 'correct-horse-battery',
        'password_confirmation' => 'correct-horse-battery',
    ]);

    $user = User::query()->where('email', 'ada@example.com')->first();

    expect($user)->not->toBeNull()
        ->and($user->name)->toBe('Ada Lovelace')
        ->and($user->email_verified_at)->toBeNull()
        ->and(Hash::check('correct-horse-battery', $user->password))->toBeTrue();

    $response->assertRedirect(route('verification.notice'));
    $this->assertAuthenticatedAs($user);

    Notification::assertSentTo($user, VerifyEmail::class);
});

it('rejects a password shorter than 12 characters', function (): void {
    $response = $this->post('/register', [
        'name' => 'Ada Lovelace',
        'email' => 'ada@example.com',
        'password' => 'short',
        'password_confirmation' => 'short',
    ]);

    $response->assertSessionHasErrors('password');
    expect(User::query()->count())->toBe(0);
});

it('rejects a duplicate email address', function (): void {
    User::factory()->create(['email' => 'ada@example.com']);

    $response = $this->post('/register', [
        'name' => 'Ada Lovelace',
        'email' => 'ada@example.com',
        'password' => 'correct-horse-battery',
        'password_confirmation' => 'correct-horse-battery',
    ]);

    $response->assertSessionHasErrors('email');
    expect(User::query()->count())->toBe(1);
});

it('throttles registration at six attempts per minute per IP', function (): void {
    for ($attempt = 1; $attempt <= 6; $attempt++) {
        $this->post('/register', [])->assertSessionHasErrors();
    }

    $this->post('/register', [])->assertStatus(429);
});
