<?php

declare(strict_types=1);

namespace App\Application\DTOs\Identity;

/**
 * Input for updating the authenticated user's profile.
 *
 * A null password means "leave the current password unchanged".
 */
final readonly class UpdateProfileData
{
    public function __construct(
        public string $name,
        public string $email,
        public ?string $password = null,
    ) {}
}
