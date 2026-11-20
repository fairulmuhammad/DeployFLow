<?php

declare(strict_types=1);

namespace App\Application\DTOs\Identity;

/**
 * Input for registering a new account.
 */
final readonly class RegisterUserData
{
    public function __construct(
        public string $name,
        public string $email,
        public string $password,
    ) {}
}
