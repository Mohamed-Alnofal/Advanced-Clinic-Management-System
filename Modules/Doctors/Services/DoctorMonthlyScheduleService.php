<?php

namespace Modules\Doctors\Services;

use Carbon\Carbon;
use Illuminate\Validation\ValidationException;
use Modules\Appointments\Models\Appointment;
use Modules\Appointments\Services\AppointmentSlotResolver;
use Modules\Doctors\Models\Doctor;
use Modules\Doctors\Models\DoctorHoliday;
use Modules\Doctors\Models\DoctorMonthlySchedule;
use Modules\Doctors\Models\DoctorSchedule;

class DoctorMonthlyScheduleService
{
    public function __construct(
        protected AppointmentSlotResolver $slotResolver
    ) {
    }

    public function generate($doctorId, $clinicId, $month)
    {
        $weeklySchedules = DoctorSchedule::where('doctor_id', $doctorId)
            ->where('clinic_id', $clinicId)
            ->where('consultation_type', 'clinic')
            ->get();

        if ($weeklySchedules->isEmpty()) {
            throw ValidationException::withMessages([
                'clinic_id' => 'Publish weekly clinic hours for this facility before generating the month.',
            ]);
        }

        $start = Carbon::parse($month.'-01')->startOfDay();
        $end = $start->copy()->endOfMonth();
        $generated = 0;

        for ($date = $start->copy(); $date->lte($end); $date->addDay()) {
            $dayName = strtolower($date->format('l'));
            $isHoliday = DoctorHoliday::coversDate(
                (int) $doctorId,
                $date->toDateString(),
                $clinicId ? (int) $clinicId : null,
                'clinic'
            );

            foreach ($weeklySchedules as $schedule) {
                if (strtolower((string) $schedule->day) !== $dayName) {
                    continue;
                }

                $hasBooking = $this->slotResolver->isBookedForDate(
                    (int) $schedule->id,
                    $date->toDateString()
                );

                $status = $hasBooking ? 'available' : ($isHoliday ? 'holiday' : 'available');

                DoctorMonthlySchedule::updateOrCreate(
                    [
                        'doctor_id' => $doctorId,
                        'clinic_id' => $clinicId,
                        'date' => $date->toDateString(),
                        'start_time' => $schedule->start_time,
                    ],
                    [
                        'doctor_schedule_id' => $schedule->id,
                        'end_time' => $schedule->end_time,
                        'consultation_duration' => $schedule->consultation_duration,
                        'price' => $schedule->price,
                        'status' => $status,
                    ]
                );
                $generated++;
            }
        }

        return [
            'status' => true,
            'month' => $month,
            'clinic_id' => (int) $clinicId,
            'generated' => $generated,
        ];
    }

    public function getMonth($doctorId, $month)
    {
        return DoctorMonthlySchedule::where('doctor_id', $doctorId)
            ->whereMonth('date', Carbon::parse($month)->month)
            ->whereYear('date', Carbon::parse($month)->year)
            ->orderBy('date')
            ->orderBy('start_time')
            ->get();
    }

    public function patientCalendar($doctorId, $month, ?int $clinicId = null)
    {
        if (! Doctor::query()->whereKey($doctorId)->listedForPatients()->exists()) {
            return [
                'month' => $month,
                'days' => [],
                'holidays' => [],
            ];
        }

        $start = Carbon::parse($month.'-01')->startOfDay();
        $end = $start->copy()->endOfMonth();
        $holidayDates = $this->holidayDatesForRange((int) $doctorId, $start, $end);

        $counts = [];
        $monthQuery = $this->monthRows((int) $doctorId, $month);
        if ($clinicId) {
            $monthQuery = $monthQuery->where('clinic_id', $clinicId);
        }
        foreach ($monthQuery as $row) {
            $day = $row->date->toDateString();
            if (($row->status ?? '') !== 'available') {
                continue;
            }
            if (in_array($day, $holidayDates, true)) {
                continue;
            }
            if ($this->isSlotBooked($row, $day)) {
                continue;
            }
            $counts[$day] = ($counts[$day] ?? 0) + 1;
        }

        if ($counts === []) {
            // Only show days the doctor explicitly published for this month.
            // Weekly templates alone must not light calendar dots (false "available" Sundays).
            $counts = [];
        }

        $days = [];
        foreach ($counts as $date => $count) {
            $days[] = [
                'date' => $date,
                'available_count' => $count,
            ];
        }

        return [
            'month' => $month,
            'days' => $days,
            'holidays' => $holidayDates,
        ];
    }

    public function availableSlots($doctorId, $date, ?string $type = null, ?int $clinicId = null)
    {
        if (! Doctor::query()->whereKey($doctorId)->listedForPatients()->exists()) {
            return [];
        }

        $day = Carbon::parse($date)->toDateString();
        $wantedType = $this->normalizeType($type);

        if (DoctorHoliday::coversDate((int) $doctorId, $day, $clinicId, $wantedType)) {
            return [];
        }

        $monthlyQuery = DoctorMonthlySchedule::where('doctor_id', $doctorId)
            ->whereDate('date', $day)
            ->where('status', 'available')
            ->orderBy('start_time');

        if ($clinicId) {
            $monthlyQuery->where('clinic_id', $clinicId);
        }

        $monthly = $monthlyQuery->get();

        if ($wantedType !== 'video' && $monthly->isNotEmpty()) {
            return $monthly
                ->map(fn ($row) => $this->presentSlot(
                    scheduleId: (int) $row->doctor_schedule_id,
                    clinicId: $row->clinic_id ? (int) $row->clinic_id : null,
                    startTime: (string) $row->start_time,
                    endTime: (string) $row->end_time,
                    duration: (int) $row->consultation_duration,
                    price: $row->price,
                    date: $day,
                    consultationType: 'clinic',
                ))
                ->filter()
                ->values()
                ->all();
        }

        return $this->weeklySlotsForDate((int) $doctorId, $day, $wantedType, $clinicId);
    }

    public function monthlyAvailableSlots($doctorId, $month)
    {
        if (! Doctor::query()->whereKey($doctorId)->listedForPatients()->exists()) {
            return [];
        }

        $start = Carbon::parse($month.'-01')->startOfDay();
        $end = $start->copy()->endOfMonth();
        $grouped = [];

        for ($date = $start->copy(); $date->lte($end); $date->addDay()) {
            $day = $date->toDateString();
            $slots = $this->availableSlots($doctorId, $day, 'clinic');
            if ($slots === []) {
                continue;
            }
            $grouped[] = [
                'date' => $day,
                'slots' => $slots,
            ];
        }

        return $grouped;
    }

    private function monthRows(int $doctorId, string $month)
    {
        return DoctorMonthlySchedule::where('doctor_id', $doctorId)
            ->whereMonth('date', Carbon::parse($month)->month)
            ->whereYear('date', Carbon::parse($month)->year)
            ->orderBy('date')
            ->orderBy('start_time')
            ->get();
    }

    private function weeklySlotsForDate(int $doctorId, string $day, ?string $wantedType, ?int $clinicId = null): array
    {
        if (DoctorHoliday::coversDate($doctorId, $day, $clinicId, $wantedType)) {
            return [];
        }

        $weekday = strtolower(Carbon::parse($day)->format('l'));
        $query = DoctorSchedule::where('doctor_id', $doctorId)
            ->whereRaw('LOWER(day) = ?', [$weekday])
            ->orderBy('start_time');

        if ($wantedType) {
            $query->where('consultation_type', $wantedType);
        }

        if ($clinicId && $wantedType !== 'video') {
            $query->where('clinic_id', $clinicId);
        }

        return $query->get()
            ->map(fn ($schedule) => $this->presentSlot(
                scheduleId: (int) $schedule->id,
                clinicId: $schedule->clinic_id ? (int) $schedule->clinic_id : null,
                startTime: (string) $schedule->start_time,
                endTime: (string) $schedule->end_time,
                duration: (int) $schedule->consultation_duration,
                price: $schedule->price,
                date: $day,
                consultationType: (string) ($schedule->consultation_type ?? 'clinic'),
            ))
            ->filter()
            ->values()
            ->all();
    }

    private function weeklyFallbackCounts(
        int $doctorId,
        Carbon $start,
        Carbon $end,
        array $holidayDates,
        ?int $clinicId = null
    ): array {
        $templatesQuery = DoctorSchedule::where('doctor_id', $doctorId)
            ->where('consultation_type', 'clinic');
        if ($clinicId) {
            $templatesQuery->where('clinic_id', $clinicId);
        }
        $templates = $templatesQuery
            ->get()
            ->groupBy(fn ($row) => strtolower((string) $row->day));

        $counts = [];
        for ($date = $start->copy(); $date->lte($end); $date->addDay()) {
            $day = $date->toDateString();
            if (in_array($day, $holidayDates, true)) {
                continue;
            }
            $weekday = strtolower($date->format('l'));
            $rows = $templates->get($weekday, collect());
            $open = 0;
            foreach ($rows as $schedule) {
                if (! $this->slotResolver->isBookedForDate((int) $schedule->id, $day)) {
                    $open++;
                }
            }
            if ($open > 0) {
                $counts[$day] = $open;
            }
        }

        return $counts;
    }

    private function holidayDatesForRange(int $doctorId, Carbon $start, Carbon $end): array
    {
        $holidays = DoctorHoliday::where('doctor_id', $doctorId)->get();
        $dates = [];
        for ($date = $start->copy(); $date->lte($end); $date->addDay()) {
            $day = $date->toDateString();
            foreach ($holidays as $holiday) {
                // Facility off-days must not hide video hours on the shared calendar.
                if ($holiday->clinic_id) {
                    continue;
                }
                if ($day >= $holiday->start_date->toDateString()
                    && $day <= $holiday->end_date->toDateString()) {
                    $dates[] = $day;
                    break;
                }
            }
        }

        return $dates;
    }

    private function presentSlot(
        int $scheduleId,
        ?int $clinicId,
        string $startTime,
        string $endTime,
        int $duration,
        mixed $price,
        string $date,
        string $consultationType,
    ): ?array {
        if ($scheduleId < 1) {
            return null;
        }

        $booked = $this->slotResolver->isBookedForDate($scheduleId, $date);

        return [
            'schedule_id' => $scheduleId,
            'clinic_id' => $clinicId,
            'consultation_type' => $consultationType,
            'start_time' => substr($startTime, 0, 5),
            'end_time' => substr($endTime, 0, 5),
            'consultation_duration' => $duration,
            'price' => $price,
            'available' => ! $booked,
            'is_booked' => $booked,
            'status' => $booked ? 'booked' : 'available',
        ];
    }

    private function isSlotBooked(DoctorMonthlySchedule $row, string $date): bool
    {
        if (! $row->doctor_schedule_id) {
            return false;
        }

        return $this->slotResolver->isBookedForDate((int) $row->doctor_schedule_id, $date);
    }

    private function normalizeType(?string $type): ?string
    {
        if ($type === null || $type === '') {
            return null;
        }

        $normalized = strtolower($type);
        if (str_contains($normalized, 'video')) {
            return 'video';
        }
        if (in_array($normalized, ['clinic', 'in_clinic', 'in-clinic'], true)) {
            return 'clinic';
        }

        return null;
    }
}
