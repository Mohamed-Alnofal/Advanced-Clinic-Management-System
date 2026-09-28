<?php

namespace Modules\Appointments\Services;

use Carbon\Carbon;
use Modules\Appointments\Models\Appointment;
use Modules\Doctors\Models\DoctorSchedule;

/**
 * Weekly doctor_schedules rows are reusable templates.
 * Availability is enforced per calendar date on appointments.appointment_date.
 */
class AppointmentSlotResolver
{
    private const DAY_MAP = [
        'sunday' => Carbon::SUNDAY,
        'monday' => Carbon::MONDAY,
        'tuesday' => Carbon::TUESDAY,
        'wednesday' => Carbon::WEDNESDAY,
        'thursday' => Carbon::THURSDAY,
        'friday' => Carbon::FRIDAY,
        'saturday' => Carbon::SATURDAY,
    ];

    public function resolveDate(DoctorSchedule $schedule, ?string $requestedDate = null): string
    {
        if ($requestedDate !== null && $requestedDate !== '') {
            return Carbon::parse($requestedDate)->toDateString();
        }

        $targetDay = self::DAY_MAP[strtolower((string) $schedule->day)] ?? null;

        if ($targetDay === null) {
            return Carbon::today()->toDateString();
        }

        return Carbon::now()->next($targetDay)->toDateString();
    }

    /**
     * Prefer explicit appointment_date; otherwise take the date from appointment_time
     * payloads like "2026-08-18 10:00".
     */
    public function resolveDateFromBooking(DoctorSchedule $schedule, array $data): string
    {
        $requested = $data['appointment_date'] ?? null;
        if (! is_string($requested) || trim($requested) === '') {
            $time = $data['appointment_time'] ?? null;
            if (is_string($time) && preg_match('/^\d{4}-\d{2}-\d{2}/', $time)) {
                $requested = substr($time, 0, 10);
            }
        }

        return $this->resolveDate($schedule, is_string($requested) ? $requested : null);
    }

    public function dateMatchesScheduleDay(DoctorSchedule $schedule, string $appointmentDate): bool
    {
        $targetDay = self::DAY_MAP[strtolower((string) $schedule->day)] ?? null;
        if ($targetDay === null) {
            return true;
        }

        return Carbon::parse($appointmentDate)->dayOfWeek === $targetDay;
    }

    public function isBookedForDate(int $scheduleId, string $appointmentDate, ?int $ignoreAwaitingForUserId = null): bool
    {
        return Appointment::query()
            ->where('schedule_id', $scheduleId)
            ->whereDate('appointment_date', $appointmentDate)
            ->whereNotIn('status', ['rejected', 'cancelled'])
            ->when($ignoreAwaitingForUserId !== null, function ($query) use ($ignoreAwaitingForUserId) {
                // Same user may retry payment on their own awaiting_payment hold.
                $query->where(function ($inner) use ($ignoreAwaitingForUserId) {
                    $inner->where('status', '!=', 'awaiting_payment')
                        ->orWhere('user_id', '!=', $ignoreAwaitingForUserId);
                });
            })
            ->exists();
    }
}
