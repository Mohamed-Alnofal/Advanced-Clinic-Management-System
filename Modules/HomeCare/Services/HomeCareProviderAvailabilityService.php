<?php

namespace Modules\HomeCare\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Modules\HomeCare\Models\HomeCareProfile;
use Modules\HomeCare\Models\HomeCareProviderAvailability;

class HomeCareProviderAvailabilityService
{
    /**
     * Get authenticated provider availability.
     */
    public function getMyAvailability()
    {
        $user = Auth::user();

        if (! $user) {
            throw new \RuntimeException('Unauthenticated.');
        }

        $profile = HomeCareProfile::where('user_id', $user->id)->first();

        if (! $profile) {
            return collect();
        }

        return HomeCareProviderAvailability::where(
            'home_care_profile_id',
            $profile->id
        )
            ->orderBy('type')
            ->orderBy('start_date')
            ->orderBy('day_of_week')
            ->orderBy('start_time')
            ->get();
    }

    /**
     * Create provider availability.
     *
     * Supported types:
     *
     * weekly
     * monthly
     * daily
     */
    public function createAvailability(
        array $data
    ): HomeCareProviderAvailability {

        $user = Auth::user();

        if (! $user) {
            throw new \RuntimeException('Unauthenticated.');
        }

        $profile = HomeCareProfile::where(
            'user_id',
            $user->id
        )->firstOrFail();

        $this->assertUniqueAvailability($profile, $data);

        return match ($data['type']) {

            'weekly' => $this->createWeekly(
                $profile,
                $data
            ),

            'monthly' => $this->createMonthly(
                $profile,
                $data
            ),

            'daily' => $this->createDaily(
                $profile,
                $data
            ),

            default => throw new \InvalidArgumentException(
                'Invalid availability type.'
            ),
        };
    }

    /**
     * Create recurring weekly availability.
     *
     * Example:
     *
     * Monday
     * 09:00 - 14:00
     *
     * This repeats every week.
     */
    private function createWeekly(
        HomeCareProfile $profile,
        array $data
    ): HomeCareProviderAvailability {

        return HomeCareProviderAvailability::create([
            'home_care_profile_id' => $profile->id,

            'type' => 'weekly',

            'day_of_week' => $data['day_of_week'],

            'start_date' => null,
            'end_date' => null,

            'excluded_dates' => null,

            'start_time' => $data['start_time'],
            'end_time' => $data['end_time'],

            'is_available' => $data['is_available'] ?? true,
        ]);
    }

    /**
     * Create monthly availability.
     *
     * The provider is available throughout the selected month.
     *
     * Specific dates can be excluded.
     */
    private function createMonthly(
        HomeCareProfile $profile,
        array $data
    ): HomeCareProviderAvailability {

        $month = Carbon::createFromFormat(
            'Y-m',
            $data['month']
        );

        $monthStart = $month
            ->copy()
            ->startOfMonth();

        $monthEnd = $month
            ->copy()
            ->endOfMonth();

        $excludedDates = $data['excluded_dates'] ?? [];

        $excludedDates = $this->validateExcludedDates(
            $excludedDates,
            $monthStart,
            $monthEnd
        );

        return HomeCareProviderAvailability::create([
            'home_care_profile_id' => $profile->id,

            'type' => 'monthly',

            'day_of_week' => null,

            'start_date' => $monthStart->toDateString(),
            'end_date' => $monthEnd->toDateString(),

            'excluded_dates' => $excludedDates,

            'start_time' => $data['start_time'],
            'end_time' => $data['end_time'],

            'is_available' => $data['is_available'] ?? true,
        ]);
    }

    /**
     * Create availability for one specific date.
     */
    private function createDaily(
        HomeCareProfile $profile,
        array $data
    ): HomeCareProviderAvailability {

        $date = Carbon::parse(
            $data['date']
        )->toDateString();

        return HomeCareProviderAvailability::create([
            'home_care_profile_id' => $profile->id,

            'type' => 'daily',

            'day_of_week' => null,

            'start_date' => $date,
            'end_date' => $date,

            'excluded_dates' => null,

            'start_time' => $data['start_time'],
            'end_time' => $data['end_time'],

            'is_available' => $data['is_available'] ?? true,
        ]);
    }

    /**
     * Update provider availability.
     */
    public function updateAvailability(
        int $id,
        array $data
    ): HomeCareProviderAvailability {

        $user = Auth::user();

        if (! $user) {
            throw new \RuntimeException('Unauthenticated.');
        }

        $profile = HomeCareProfile::where(
            'user_id',
            $user->id
        )->firstOrFail();

        $availability = HomeCareProviderAvailability::where(
            'id',
            $id
        )
            ->where(
                'home_care_profile_id',
                $profile->id
            )
            ->firstOrFail();

        /*
        |--------------------------------------------------------------------------
        | If type is not provided, keep the existing type.
        |--------------------------------------------------------------------------
        */

        $type = $data['type'] ?? $availability->type;

        /*
        |--------------------------------------------------------------------------
        | Common fields
        |--------------------------------------------------------------------------
        */

        $updateData = [
            'type' => $type,

            'start_time' => $data['start_time']
                ?? $availability->start_time,

            'end_time' => $data['end_time']
                ?? $availability->end_time,

            'is_available' => $data['is_available']
                ?? $availability->is_available,
        ];

        /*
        |--------------------------------------------------------------------------
        | Weekly
        |--------------------------------------------------------------------------
        */

        if ($type === 'weekly') {

            if (! isset($data['day_of_week'])) {
                throw new \InvalidArgumentException(
                    'day_of_week is required for weekly availability.'
                );
            }

            $updateData['day_of_week'] =
                $data['day_of_week'];

            $updateData['start_date'] = null;

            $updateData['end_date'] = null;

            $updateData['excluded_dates'] = null;
        }

        /*
        |--------------------------------------------------------------------------
        | Monthly
        |--------------------------------------------------------------------------
        */

        elseif ($type === 'monthly') {

            if (! isset($data['month'])) {
                throw new \InvalidArgumentException(
                    'month is required for monthly availability.'
                );
            }

            $month = Carbon::createFromFormat(
                'Y-m',
                $data['month']
            );

            $monthStart = $month
                ->copy()
                ->startOfMonth();

            $monthEnd = $month
                ->copy()
                ->endOfMonth();

            $excludedDates =
                $data['excluded_dates'] ?? [];

            $excludedDates = $this->validateExcludedDates(
                $excludedDates,
                $monthStart,
                $monthEnd
            );

            $updateData['day_of_week'] = null;

            $updateData['start_date'] =
                $monthStart->toDateString();

            $updateData['end_date'] =
                $monthEnd->toDateString();

            $updateData['excluded_dates'] =
                $excludedDates;
        }

        /*
        |--------------------------------------------------------------------------
        | Daily
        |--------------------------------------------------------------------------
        */

        elseif ($type === 'daily') {

            if (! isset($data['date'])) {
                throw new \InvalidArgumentException(
                    'date is required for daily availability.'
                );
            }

            $date = Carbon::parse(
                $data['date']
            )->toDateString();

            $updateData['day_of_week'] = null;

            $updateData['start_date'] = $date;

            $updateData['end_date'] = $date;

            $updateData['excluded_dates'] = null;
        }

        /*
        |--------------------------------------------------------------------------
        | Invalid type
        |--------------------------------------------------------------------------
        */

        else {

            throw new \InvalidArgumentException(
                'Invalid availability type.'
            );
        }

        $availability->update($updateData);

        return $availability->fresh();
    }

    public function deleteAvailability(int $id): void
    {
        $user = Auth::user();

        if (! $user) {
            throw new \RuntimeException('Unauthenticated.');
        }

        $profile = HomeCareProfile::where('user_id', $user->id)->firstOrFail();

        $availability = HomeCareProviderAvailability::where('id', $id)
            ->where('home_care_profile_id', $profile->id)
            ->firstOrFail();

        $availability->delete();
    }

    /**
     * Validate monthly excluded dates.
     *
     * All excluded dates must belong to the selected month.
     */
    private function validateExcludedDates(
        array $excludedDates,
        Carbon $monthStart,
        Carbon $monthEnd
    ): array {

        $validatedDates = [];

        foreach ($excludedDates as $date) {

            $date = Carbon::parse($date);

            if (
                $date->lt($monthStart) ||
                $date->gt($monthEnd)
            ) {
                throw new \InvalidArgumentException(
                    "Excluded date {$date->toDateString()} " .
                    "does not belong to the selected month."
                );
            }

            $validatedDates[] = $date->toDateString();
        }

        /*
        |--------------------------------------------------------------------------
        | Remove duplicate dates
        |--------------------------------------------------------------------------
        */

        return array_values(
            array_unique($validatedDates)
        );
    }

    /**
     * Prevent the same hours from being published twice,
     * and reject overlapping ranges on the same day/week/month.
     */
    private function assertUniqueAvailability(
        HomeCareProfile $profile,
        array $data
    ): void {
        $start = Carbon::parse($data['start_time'])->format('H:i');
        $end = Carbon::parse($data['end_time'])->format('H:i');

        if ($end <= $start) {
            throw new \InvalidArgumentException(
                'End time must be after start time.'
            );
        }

        $query = HomeCareProviderAvailability::where(
            'home_care_profile_id',
            $profile->id
        )->where('type', $data['type']);

        if ($data['type'] === 'weekly') {
            $query->where('day_of_week', $data['day_of_week']);
        } elseif ($data['type'] === 'daily') {
            $query->whereDate(
                'start_date',
                Carbon::parse($data['date'])->toDateString()
            );
        } elseif ($data['type'] === 'monthly') {
            $monthStart = Carbon::createFromFormat('Y-m', $data['month'])
                ->startOfMonth()
                ->toDateString();
            $query->whereDate('start_date', $monthStart);
        }

        foreach ($query->get() as $slot) {
            $existingStart = Carbon::parse($slot->start_time)->format('H:i');
            $existingEnd = Carbon::parse($slot->end_time)->format('H:i');

            if ($existingStart === $start && $existingEnd === $end) {
                throw new \InvalidArgumentException(
                    'هذه الساعات مضافة مسبقاً. لا يمكن إضافة نفس الفترة مرتين.'
                );
            }

            if ($existingStart < $end && $start < $existingEnd) {
                throw new \InvalidArgumentException(
                    'هذا الوقت يتداخل مع فترة موجودة.'
                );
            }
        }
    }
}