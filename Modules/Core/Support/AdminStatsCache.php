<?php

namespace Modules\Core\Support;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class AdminStatsCache
{
    public const ANALYTICS_ALL = 'admin:analytics:all';

    public const DASHBOARD_STATS = 'admin:dashboard:stats';

    public const FINANCIALS_SUMMARY = 'admin:financials:summary';

    public const FINANCIALS_TREND_PREFIX = 'admin:financials:trend:';

    public const FINANCIALS_BY_PROVIDER = 'admin:financials:by-provider';

    public const AUDIT_STATISTICS = 'admin:audit:statistics';

    /**
     * Invalidate analytics/dashboard caches after activate / suspend / verify.
     */
    public static function forgetUserLifecycleStats(): void
    {
        self::forgetMany([
            self::ANALYTICS_ALL,
            self::DASHBOARD_STATS,
        ]);
    }

    /**
     * Invalidate financial aggregate caches.
     */
    public static function forgetFinancials(): void
    {
        self::forgetMany([
            self::FINANCIALS_SUMMARY,
            self::FINANCIALS_BY_PROVIDER,
        ]);

        try {
            for ($months = 3; $months <= 24; $months++) {
                Cache::forget(self::FINANCIALS_TREND_PREFIX.$months);
            }
        } catch (\Throwable $e) {
            Log::warning('AdminStatsCache: failed clearing financial trend keys', [
                'error' => $e->getMessage(),
            ]);
        }
    }

    public static function forgetAuditStatistics(): void
    {
        self::forgetMany([self::AUDIT_STATISTICS]);
    }

    /**
     * @param  list<string>  $keys
     */
    public static function forgetMany(array $keys): void
    {
        foreach ($keys as $key) {
            try {
                Cache::forget($key);
            } catch (\Throwable $e) {
                Log::warning('AdminStatsCache: failed to forget key', [
                    'key' => $key,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }
}
