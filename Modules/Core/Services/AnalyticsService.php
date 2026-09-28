<?php

namespace Modules\Core\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Modules\Core\Support\AdminStatsCache;
use Modules\Doctors\Models\Doctor;
use Modules\Doctors\Models\DoctorSchedule;
use Modules\Pharmacist\Models\Pharmacist;
use Modules\Users\Models\User;

class AnalyticsService
{
    private const TTL_SECONDS = 90;

    /**
     * إحصائيات المستخدمين — استعلامات مجمّعة + كاش.
     */
    public function getUserStats(): array
    {
        return $this->computeUserStats();
    }

    /**
     * إحصائيات الأطباء والتخصصات.
     */
    public function getDoctorStats(): array
    {
        return $this->computeDoctorStats();
    }

    /**
     * إحصائيات الصيادلة.
     */
    public function getPharmacistStats(): array
    {
        return $this->computePharmacistStats();
    }

    /**
     * إحصائيات النشاط.
     */
    public function getActivityStats(): array
    {
        return $this->computeActivityStats();
    }

    /**
     * جميع الإحصائيات مجتمعة (كاش 90 ثانية).
     */
    public function getAllAnalytics(): array
    {
        try {
            return Cache::remember(AdminStatsCache::ANALYTICS_ALL, self::TTL_SECONDS, function () {
                return $this->buildAllAnalytics();
            });
        } catch (\Throwable $e) {
            Log::warning('AnalyticsService: cache miss fallback for all analytics', [
                'error' => $e->getMessage(),
            ]);

            return $this->buildAllAnalytics();
        }
    }

    public function getDashboardStats(): array
    {
        try {
            return Cache::remember(AdminStatsCache::DASHBOARD_STATS, self::TTL_SECONDS, function () {
                return $this->computeDashboardStats();
            });
        } catch (\Throwable $e) {
            Log::warning('AnalyticsService: cache miss fallback for dashboard stats', [
                'error' => $e->getMessage(),
            ]);

            return $this->computeDashboardStats();
        }
    }

    private function buildAllAnalytics(): array
    {
        return [
            'users' => $this->computeUserStats(),
            'doctors' => $this->computeDoctorStats(),
            'pharmacists' => $this->computePharmacistStats(),
            'activity' => $this->computeActivityStats(),
            'last_updated' => now(),
        ];
    }

    private function computeUserStats(): array
    {
        $agg = User::query()
            ->selectRaw("
                COUNT(*) as total_users,
                SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active_users,
                SUM(CASE WHEN status = 'suspended' THEN 1 ELSE 0 END) as suspended_users,
                SUM(CASE WHEN is_verified = 1 THEN 1 ELSE 0 END) as verified_users,
                SUM(CASE WHEN is_verified = 0 THEN 1 ELSE 0 END) as unverified_users,
                SUM(CASE WHEN gender = 'male' THEN 1 ELSE 0 END) as male_users,
                SUM(CASE WHEN gender = 'female' THEN 1 ELSE 0 END) as female_users,
                SUM(CASE WHEN gender IS NULL THEN 1 ELSE 0 END) as gender_unspecified,
                SUM(CASE WHEN DATE(created_at) = CURDATE() THEN 1 ELSE 0 END) as new_users_today,
                SUM(CASE WHEN created_at >= ? THEN 1 ELSE 0 END) as new_users_this_week,
                SUM(CASE WHEN MONTH(created_at) = ? AND YEAR(created_at) = ? THEN 1 ELSE 0 END) as new_users_this_month
            ", [
                now()->startOfWeek()->toDateTimeString(),
                now()->month,
                now()->year,
            ])
            ->first();

        $roleCounts = $this->roleCounts();

        return [
            'total_users' => (int) ($agg->total_users ?? 0),
            'active_users' => (int) ($agg->active_users ?? 0),
            'suspended_users' => (int) ($agg->suspended_users ?? 0),
            'verified_users' => (int) ($agg->verified_users ?? 0),
            'unverified_users' => (int) ($agg->unverified_users ?? 0),

            'users_by_role' => [
                'admins' => (int) ($roleCounts['admin'] ?? 0),
                'doctors' => (int) ($roleCounts['doctor'] ?? 0),
                'pharmacists' => (int) ($roleCounts['pharmacist'] ?? 0),
                'regular_users' => (int) ($roleCounts['user'] ?? 0),
            ],

            'users_by_gender' => [
                'male' => (int) ($agg->male_users ?? 0),
                'female' => (int) ($agg->female_users ?? 0),
                'not_specified' => (int) ($agg->gender_unspecified ?? 0),
            ],

            'new_users_today' => (int) ($agg->new_users_today ?? 0),
            'new_users_this_week' => (int) ($agg->new_users_this_week ?? 0),
            'new_users_this_month' => (int) ($agg->new_users_this_month ?? 0),
        ];
    }

    /**
     * Single join/group query instead of 4× Spatie User::role()->count().
     *
     * @return array<string, int>
     */
    private function roleCounts(): array
    {
        try {
            return DB::table('model_has_roles')
                ->join('roles', 'roles.id', '=', 'model_has_roles.role_id')
                ->where('model_has_roles.model_type', User::class)
                ->whereIn('roles.name', ['admin', 'doctor', 'pharmacist', 'user'])
                ->groupBy('roles.name')
                ->selectRaw('roles.name as name, COUNT(*) as total')
                ->pluck('total', 'name')
                ->map(fn ($v) => (int) $v)
                ->all();
        } catch (\Throwable $e) {
            Log::warning('AnalyticsService: roleCounts fallback', ['error' => $e->getMessage()]);

            return [
                'admin' => User::role('admin')->count(),
                'doctor' => User::role('doctor')->count(),
                'pharmacist' => User::role('pharmacist')->count(),
                'user' => User::role('user')->count(),
            ];
        }
    }

    private function computeDoctorStats(): array
    {
        $doctorAgg = Doctor::query()
            ->selectRaw("
                COUNT(*) as total_doctors,
                SUM(CASE WHEN is_verified = 1 THEN 1 ELSE 0 END) as verified_doctors,
                SUM(CASE WHEN is_verified = 0 THEN 1 ELSE 0 END) as unverified_doctors,
                AVG(experience_years) as average_experience_years
            ")
            ->first();

        $priceAgg = DoctorSchedule::query()
            ->selectRaw("
                AVG(price) as average_price,
                SUM(CASE WHEN price < 100 THEN 1 ELSE 0 END) as price_low,
                SUM(CASE WHEN price BETWEEN 100 AND 300 THEN 1 ELSE 0 END) as price_medium,
                SUM(CASE WHEN price > 300 THEN 1 ELSE 0 END) as price_high
            ")
            ->first();

        $mostRequested = $this->mostRequestedSpecializations();

        return [
            'total_doctors' => (int) ($doctorAgg->total_doctors ?? 0),
            'verified_doctors' => (int) ($doctorAgg->verified_doctors ?? 0),
            'unverified_doctors' => (int) ($doctorAgg->unverified_doctors ?? 0),
            'average_experience_years' => $doctorAgg->average_experience_years !== null
                ? round((float) $doctorAgg->average_experience_years, 2)
                : null,
            'average_price' => $priceAgg->average_price !== null
                ? round((float) $priceAgg->average_price, 2)
                : null,
            'most_requested_specializations' => $mostRequested,
            'doctors_by_price_range' => [
                'low' => (int) ($priceAgg->price_low ?? 0),
                'medium' => (int) ($priceAgg->price_medium ?? 0),
                'high' => (int) ($priceAgg->price_high ?? 0),
            ],
        ];
    }

    /**
     * Rank specialties by real patient demand (bookings), not by how many
     * doctor accounts share a name. Duplicate specialty rows are collapsed.
     */
    private function mostRequestedSpecializations(): array
    {
        $requested = DB::table('appointments')
            ->join('doctors', 'doctors.id', '=', 'appointments.doctor_id')
            ->join('specializations', 'specializations.id', '=', 'doctors.specialization_id')
            ->whereNotIn('appointments.status', ['cancelled', 'rejected'])
            ->selectRaw('MIN(specializations.id) as id, specializations.name as name, COUNT(*) as appointments_count, COUNT(DISTINCT doctors.id) as doctors_count')
            ->groupBy('specializations.name')
            ->orderByDesc('appointments_count')
            ->limit(5)
            ->get();

        if ($requested->isNotEmpty()) {
            return $requested->map(fn ($row) => [
                'id' => (int) $row->id,
                'name' => $row->name,
                'doctors_count' => (int) $row->doctors_count,
                'appointments_count' => (int) $row->appointments_count,
            ])->all();
        }

        return DB::table('doctors')
            ->join('specializations', 'specializations.id', '=', 'doctors.specialization_id')
            ->where('doctors.is_verified', 1)
            ->selectRaw('MIN(specializations.id) as id, specializations.name as name, COUNT(*) as doctors_count')
            ->groupBy('specializations.name')
            ->orderByDesc('doctors_count')
            ->limit(5)
            ->get()
            ->map(fn ($row) => [
                'id' => (int) $row->id,
                'name' => $row->name,
                'doctors_count' => (int) $row->doctors_count,
                'appointments_count' => 0,
            ])
            ->all();
    }

    private function computePharmacistStats(): array
    {
        $agg = Pharmacist::query()
            ->selectRaw("
                COUNT(*) as total_pharmacists,
                SUM(CASE WHEN is_verified = 1 THEN 1 ELSE 0 END) as verified_pharmacists,
                SUM(CASE WHEN is_verified = 0 THEN 1 ELSE 0 END) as unverified_pharmacists
            ")
            ->first();

        return [
            'total_pharmacists' => (int) ($agg->total_pharmacists ?? 0),
            'verified_pharmacists' => (int) ($agg->verified_pharmacists ?? 0),
            'unverified_pharmacists' => (int) ($agg->unverified_pharmacists ?? 0),
        ];
    }

    private function computeActivityStats(): array
    {
        $cutoff = now()->subDays(30)->toDateTimeString();

        $agg = User::query()
            ->selectRaw("
                SUM(CASE WHEN updated_at >= ? THEN 1 ELSE 0 END) as active_users_last_30_days,
                SUM(CASE WHEN updated_at < ? THEN 1 ELSE 0 END) as inactive_users_last_30_days
            ", [$cutoff, $cutoff])
            ->first();

        return [
            'active_users_last_30_days' => (int) ($agg->active_users_last_30_days ?? 0),
            'inactive_users_last_30_days' => (int) ($agg->inactive_users_last_30_days ?? 0),
            'growth_percentage' => $this->calculateGrowth(),
        ];
    }

    private function calculateGrowth(): float|int
    {
        $lastMonthUsers = User::whereBetween('created_at', [
            now()->subMonths(2),
            now()->subMonth(),
        ])->count();

        $thisMonthUsers = User::whereMonth('created_at', now()->month)
            ->whereYear('created_at', now()->year)
            ->count();

        if ($lastMonthUsers == 0) {
            return $thisMonthUsers > 0 ? 100 : 0;
        }

        return round((($thisMonthUsers - $lastMonthUsers) / $lastMonthUsers) * 100, 2);
    }

    private function computeDashboardStats(): array
    {
        $userAgg = User::query()
            ->selectRaw("
                COUNT(*) as total_users,
                SUM(CASE WHEN is_verified = 0 THEN 1 ELSE 0 END) as unverified_users,
                SUM(CASE WHEN status = 'suspended' THEN 1 ELSE 0 END) as suspended_users
            ")
            ->first();

        $roleCounts = $this->roleCounts();
        $medicalStaff = (int) ($roleCounts['doctor'] ?? 0) + (int) ($roleCounts['pharmacist'] ?? 0);

        $pendingDoctors = Doctor::where('is_verified', false)->count();
        $pendingPharmacists = Pharmacist::where('is_verified', false)->count();

        return [
            'total_users' => (int) ($userAgg->total_users ?? 0),
            'medical_staff' => $medicalStaff,
            'pending_verification' => (int) ($userAgg->unverified_users ?? 0)
                + $pendingDoctors
                + $pendingPharmacists,
            'suspended_users' => (int) ($userAgg->suspended_users ?? 0),
        ];
    }
}
