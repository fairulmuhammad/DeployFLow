<?php

declare(strict_types=1);

namespace App\Application\Actions\Identity;

use App\Application\DTOs\Identity\UpdateProfileData;
use App\Domain\Identity\Models\User;

/**
 * Updates the authenticated user's profile.
 *
 * Changing the email address invalidates the previous verification: the new
 * address has not been proven to belong to the user, so a fresh verification
 * link is sent.
 */
final class UpdateProfile
{
    public function execute(User $user, UpdateProfileData $data): User
    {
        $emailChanged = $user->email !== $data->email;

        $user->fill([
            'name' => $data->name,
            'email' => $data->email,
        ]);

        if ($emailChanged) {
            $user->email_verified_at = null;
        }

        if ($data->password !== null) {
            $user->password = $data->password;
        }

        $user->save();

        if ($emailChanged) {
            $user->sendEmailVerificationNotification();
        }

        return $user;
    }
}
