<?php

declare(strict_types=1);

namespace App\Infrastructure\Organizations;

use Illuminate\Support\Str;

/**
 * Normalizes organization slugs per docs/API.md: `^[a-z0-9-]+$`, no trailing
 * dash, maximum length 80.
 */
final class OrganizationSlug
{
    public const MAX_LENGTH = 80;

    /**
     * @return string The slug lowercased, trimmed, and constrained to [a-z0-9-].
     */
    public static function normalize(string $slug): string
    {
        $slug = Str::lower(trim($slug));
        $slug = Str::slug($slug, '-');

        $slug = (string) preg_replace('/-{2,}/', '-', $slug);
        $slug = rtrim($slug, '-');

        if (strlen($slug) > self::MAX_LENGTH) {
            $slug = substr($slug, 0, self::MAX_LENGTH);
            $slug = rtrim($slug, '-');
        }

        return $slug;
    }

    public static function isValid(string $slug): bool
    {
        return preg_match('/^[a-z0-9]+$|^[a-z0-9]+-[a-z0-9-]*$/', $slug) === 1
            && strlen($slug) <= self::MAX_LENGTH
            && $slug !== '-';
    }
}
