<?php

declare(strict_types=1);

namespace App\Application\Actions\Identity;

use App\Application\DTOs\Identity\RegisterUserData;
use App\Domain\Identity\Models\User;
use Illuminate\Auth\Events\Registered;

/**
 * Creates an account and announces it.
 *
 * `Registered` triggers Laravel's verification notification listener, so the
 * new user receives a signed verification link.
 */
final class RegisterUser
{
    public function execute(RegisterUserData $data): User
    {
        $user = User::create([
            'name' => $data->name,
            'email' => $data->email,
            'password' => $data->password,
        ]);

        event(new Registered($user));

        return $user;
    }
}
