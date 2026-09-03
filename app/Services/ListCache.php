<?php

declare(strict_types=1);

namespace App\Services;

use Closure;
use Illuminate\Support\Facades\Cache;

/**
 * Versioned cache helper for list endpoints.
 *
 * Problem being solved: list pages (activities / announcements / jobs) are
 * cached per filter combination. When staff posts new content, readers kept
 * seeing stale lists until every TTL (60-600s) expired, because there was no
 * cheap way to invalidate N pattern-matched keys.
 *
 * Solution: every key embeds a per-group version counter. `bump()` increments
 * the counter once, which instantly invalidates ALL keys of that group with a
 * single cache write, while readers pay only one tiny cache GET per request.
 */
final class ListCache
{
    public const GROUP_ACTIVITIES = 'activities';

    public const GROUP_ACTIVITY_CATEGORIES = 'activities:categories';

    public const GROUP_ANNOUNCEMENTS = 'announcements';

    public const GROUP_JOBS = 'jobs';

    public static function version(string $group): int
    {
        return (int) Cache::get(self::versionKey($group), 1);
    }

    /** Build a versioned cache key, e.g. "list:jobs:v7:geo_all". */
    public static function key(string $group, string $suffix = ''): string
    {
        $suffix = $suffix !== '' ? ':' . ltrim($suffix, ':') : '';

        return "list:{$group}:v" . self::version($group) . $suffix;
    }

    /** Invalidate every cached key of the group (old entries age out unused). */
    public static function bump(string $group): void
    {
        Cache::forever(self::versionKey($group), self::version($group) + 1);
    }

    public static function remember(string $group, string $suffix, int $ttlSeconds, Closure $callback): mixed
    {
        return Cache::remember(self::key($group, $suffix), $ttlSeconds, $callback);
    }

    private static function versionKey(string $group): string
    {
        return "list:version:{$group}";
    }
}
