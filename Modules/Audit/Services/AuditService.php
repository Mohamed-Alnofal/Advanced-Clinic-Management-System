<?php

namespace Modules\Audit\Services;

use Modules\Audit\Models\AuditLog;

class AuditService
{
    public function getAllLogs()
    {
        return AuditLog::with('user')
            ->latest()
            ->paginate(50);
    }

    public function getStatistics()
    {
        return [
            'total_logs' => AuditLog::count(),
            'today_logs' => AuditLog::whereDate('created_at', today())->count(),
            'this_week_logs' => AuditLog::whereBetween('created_at', [
                now()->startOfWeek(),
                now()->endOfWeek(),
            ])->count(),
            'this_month_logs' => AuditLog::whereMonth('created_at', now()->month)
                ->whereYear('created_at', now()->year)
                ->count(),

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
