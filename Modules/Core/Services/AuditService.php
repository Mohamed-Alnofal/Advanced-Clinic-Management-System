<?php

namespace Modules\Core\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Modules\Audit\Models\AuditLog;
use Modules\Core\Support\AdminStatsCache;

class AuditService
{
    private const STATISTICS_TTL = 300;

    public function getAllLogs()
    {
        return AuditLog::with('user')
            ->latest()
            ->paginate(50);
    }

    public function getStatistics(): array
    {
        try {
            return Cache::remember(AdminStatsCache::AUDIT_STATISTICS, self::STATISTICS_TTL, function () {
                return $this->computeStatistics();
            });
        } catch (\Throwable $e) {
            Log::warning('AuditService: cache fallback for statistics', ['error' => $e->getMessage()]);

            return $this->computeStatistics();
        }
    }

    private function computeStatistics(): array
    {
        $agg = AuditLog::query()
            ->selectRaw("
                COUNT(*) as total_logs,
                SUM(CASE WHEN DATE(created_at) = CURDATE() THEN 1 ELSE 0 END) as today_logs,
                SUM(CASE WHEN created_at >= ? THEN 1 ELSE 0 END) as this_week_logs,
                SUM(CASE WHEN MONTH(created_at) = ? AND YEAR(created_at) = ? THEN 1 ELSE 0 END) as this_month_logs
            ", [
                now()->startOfWeek()->toDateTimeString(),
                now()->month,
                now()->year,
            ])
            ->first();

        return [
            'total_logs' => (int) ($agg->total_logs ?? 0),
            'today_logs' => (int) ($agg->today_logs ?? 0),
            'this_week_logs' => (int) ($agg->this_week_logs ?? 0),
            'this_month_logs' => (int) ($agg->this_month_logs ?? 0),

            'by_action' => AuditLog::query()
                ->groupBy('action')
                ->selectRaw('action, count(*) as total')
                ->get(),

            'by_role' => AuditLog::query()
                ->whereNotNull('role')
                ->groupBy('role')
                ->selectRaw('role, count(*) as total')
                ->get(),

            'top_users' => AuditLog::query()
                ->whereNotNull('user_id')
                ->groupBy('user_id', 'user_name')
                ->selectRaw('user_id, user_name, count(*) as total')
                ->orderByDesc('total')
                ->limit(10)
                ->get(),
        ];
    }
}
