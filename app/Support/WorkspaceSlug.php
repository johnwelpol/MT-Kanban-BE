<?php

namespace App\Support;

use App\Models\Tenant;

/**
 * Workspace URL slug rules. The frontend enforces the same rules.
 */
class WorkspaceSlug
{
    public const REGEX = '/^[a-z0-9][a-z0-9-]{1,38}[a-z0-9]$/';

    public const RESERVED = [
        'www', 'app', 'api', 'auth', 'admin', 'site', 'mail', 'help', 'support', 'status', 'docs', 'blog',
        'static', 'assets', 'ws', 'reverb', 'dashboard', 'billing', 'login', 'register',
    ];

    /**
     * @return 'invalid'|'reserved'|'taken'|null null when the slug is available
     */
    public static function check(string $slug): ?string
    {
        return match (true) {
            preg_match(self::REGEX, $slug) !== 1 => 'invalid',
            in_array($slug, self::RESERVED, true) => 'reserved',
            Tenant::where('slug', $slug)->exists() => 'taken',
            default => null,
        };
    }
}
