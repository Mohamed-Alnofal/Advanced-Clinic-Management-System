<?php

namespace Modules\Admin\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Modules\Appointments\Models\Appointment;
use Modules\Core\Support\AdminStatsCache;
use Modules\Order\Models\Order;
use Modules\Pharmacy\Models\Pharmacy;
use Modules\WalletTransaction\Models\WalletTransaction;

class FinancialsService
{
    private const SUMMARY_TTL = 180;

    private const TREND_TTL = 300;

    public function summary(): array
    {
        try {
            return Cache::remember(AdminStatsCache::FINANCIALS_SUMMARY, self::SUMMARY_TTL, function () {
                return $this->computeSummary();
            });
        } catch (\Throwable $e) {
            Log::warning('FinancialsService: cache fallback for summary', ['error' => $e->getMessage()]);

            return $this->computeSummary();
        }
    }

    public function trend(int $months = 6): array
    {
        $months = max(3, min(24, $months));
        $cacheKey = AdminStatsCache::FINANCIALS_TREND_PREFIX.$months;

        try {
            return Cache::remember($cacheKey, self::TREND_TTL, function () use ($months) {
                return $this->computeTrend($months);
            });
        } catch (\Throwable $e) {
            Log::warning('FinancialsService: cache fallback for trend', ['error' => $e->getMessage()]);

            return $this->computeTrend($months);
        }
    }

    public function byProvider(): array
    {
        try {
            return Cache::remember(AdminStatsCache::FINANCIALS_BY_PROVIDER, self::SUMMARY_TTL, function () {
                return $this->computeByProvider();
            });
        } catch (\Throwable $e) {
            Log::warning('FinancialsService: cache fallback for byProvider', ['error' => $e->getMessage()]);

            return $this->computeByProvider();
        }
    }

    /**
     * DB-level UNION + LIMIT/OFFSET pagination (no 400-row PHP merge).
     */
    public function transactions(int $page = 1, int $perPage = 10): array
    {
        $page = max(1, $page);
        $perPage = max(5, min(50, $perPage));
        $offset = ($page - 1) * $perPage;

        $ordersSql = "
            SELECT
                CONCAT('#ORD-', orders.id) as id,
                'order' as source,
                'Pharmacy order' as label,
                orders.payment_status as status,
                ROUND(orders.total_price, 2) as amount,
                orders.created_at as created_at
            FROM orders
        ";

        $walletSql = "
            SELECT
                CONCAT('#WTX-', wallet_transactions.id) as id,
                'wallet' as source,
                CONCAT(
                    UPPER(SUBSTRING(wallet_transactions.type, 1, 1)),
                    LOWER(SUBSTRING(wallet_transactions.type, 2)),
                    ' · ',
                    COALESCE(users.name, 'User')
                ) as label,
                wallet_transactions.status as status,
                ROUND(wallet_transactions.amount, 2) as amount,
                wallet_transactions.created_at as created_at
            FROM wallet_transactions
            LEFT JOIN wallets ON wallets.id = wallet_transactions.wallet_id
            LEFT JOIN users ON users.id = wallets.user_id
        ";

        $unionSql = "({$ordersSql}) UNION ALL ({$walletSql})";

        $total = (int) DB::table(DB::raw("({$unionSql}) as financial_tx"))->count();

        $rows = DB::table(DB::raw("({$unionSql}) as financial_tx"))
            ->orderByDesc('created_at')
            ->offset($offset)
            ->limit($perPage)
            ->get();

        $items = $rows->map(function ($row) {
            $createdAt = $row->created_at
                ? Carbon::parse($row->created_at)->toIso8601String()
                : null;

            return [
                'id' => $row->id,
                'source' => $row->source,
                'label' => $row->label,
                'status' => $row->status,
                'amount' => round((float) $row->amount, 2),
                'created_at' => $createdAt,
            ];
        })->values()->all();

        return [
            'data' => $items,
            'meta' => [
                'current_page' => $page,
                'per_page' => $perPage,
                'total' => $total,
            ],
        ];
    }

    private function computeSummary(): array
    {
        $orderRevenue = (float) Order::where('payment_status', 'paid')->sum('total_price');
        $walletDeposits = (float) WalletTransaction::where('type', 'deposit')
            ->where('status', 'success')
            ->sum('amount');
        $walletPayments = (float) WalletTransaction::where('type', 'payment')
            ->where('status', 'success')
            ->sum('amount');

        $collected = $orderRevenue + $walletPayments;
        $walletShare = $collected > 0
            ? round(($walletPayments / $collected) * 100, 2)
            : 0.0;

        $since = Carbon::now()->subDays(30);
        $last30Orders = (float) Order::where('payment_status', 'paid')
            ->where('created_at', '>=', $since)
            ->sum('total_price');
        $last30Wallet = (float) WalletTransaction::where('type', 'payment')
            ->where('status', 'success')
            ->where('created_at', '>=', $since)
            ->sum('amount');
        $last30 = $last30Orders + $last30Wallet;

        return [
            'total_revenue' => round($collected, 2),
            'order_revenue' => round($orderRevenue, 2),
            'wallet_deposits' => round($walletDeposits, 2),
            'wallet_payments' => round($walletPayments, 2),
            'commission_rate' => $walletShare,
            'wallet_share_pct' => $walletShare,
            'subscription_estimate' => round($walletPayments, 2),
            'last_30_days_revenue' => round($last30, 2),
            'projected_annual' => round($last30 * 12, 2),
            'completed_appointments' => Appointment::where('status', 'completed')->count(),
        ];
    }

    private function computeTrend(int $months): array
    {
        $start = Carbon::now()->subMonths($months - 1)->startOfMonth();

        $orderRows = Order::query()
            ->where('payment_status', 'paid')
            ->where('created_at', '>=', $start)
            ->selectRaw('DATE_FORMAT(created_at, "%Y-%m") as ym, SUM(total_price) as total')
            ->groupBy('ym')
            ->pluck('total', 'ym');

        $walletRows = WalletTransaction::query()
            ->where('type', 'payment')
            ->where('status', 'success')
            ->where('created_at', '>=', $start)
            ->selectRaw('DATE_FORMAT(created_at, "%Y-%m") as ym, SUM(amount) as total')
            ->groupBy('ym')
            ->pluck('total', 'ym');

        $points = [];
        for ($i = 0; $i < $months; $i++) {
            $month = $start->copy()->addMonths($i);
            $key = $month->format('Y-m');
            $value = (float) ($orderRows[$key] ?? 0) + (float) ($walletRows[$key] ?? 0);
            $points[] = [
                'month' => $month->format('M'),
                'value' => round($value, 2),
            ];
        }

        return $points;
    }

    private function computeByProvider(): array
    {
        $orders = Order::where('payment_status', 'paid')
            ->selectRaw('pharmacy_id, SUM(total_price) as total, COUNT(*) as cnt')
            ->groupBy('pharmacy_id')
            ->orderByDesc('total')
            ->limit(6)
            ->get();

        $max = max(1.0, (float) $orders->max('total'));
        $pharmacies = Pharmacy::with('medicalFacility:id,name')
            ->whereIn('id', $orders->pluck('pharmacy_id')->filter())
            ->get()
            ->keyBy('id');

        return $orders->map(function ($row) use ($max, $pharmacies) {
            $pharmacy = $pharmacies->get($row->pharmacy_id);
            $facilityName = $pharmacy?->medicalFacility?->name;

            return [
                'name' => $facilityName
                    ?: ($row->pharmacy_id ? 'Pharmacy #'.$row->pharmacy_id : 'Unassigned orders'),
                'amount' => round((float) $row->total, 2),
                'count' => (int) $row->cnt,
                'pct' => (int) round(((float) $row->total / $max) * 100),
            ];
        })->values()->all();
    }
}
