<?php

declare(strict_types=1);

namespace App\Infrastructure\Organizations;

/**
 * Invitation tokens.
 *
 * Raw tokens are 32 random bytes base64url-encoded (43 chars). They are
 * emailed once and never stored; only the SHA-256 hex digest (64 chars) is
 * persisted in `organization_invitations.token_hash`.
 */
final class InvitationToken
{
    public const EXPIRATION_DAYS = 7;

    public static function random(): string
    {
        return rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
    }

    public static function hash(string $raw): string
    {
        return hash('sha256', $raw);
    }
}
