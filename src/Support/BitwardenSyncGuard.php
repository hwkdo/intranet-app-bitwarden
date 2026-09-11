<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppBitwarden\Support;

use Illuminate\Support\Facades\Cache;

/**
 * Pausiert Membership-/ACL-Syncs während eines Full Resets.
 */
final class BitwardenSyncGuard
{
    public const CACHE_KEY = 'intranet-app-bitwarden.sync-paused';

    public static function pause(int $seconds = 3600): void
    {
        Cache::put(self::CACHE_KEY, true, $seconds);
    }

    public static function resume(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    public static function isPaused(): bool
    {
        return (bool) Cache::get(self::CACHE_KEY, false);
    }
}
