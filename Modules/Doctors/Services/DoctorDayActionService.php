<?php

namespace Modules\Doctors\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Modules\Appointments\Models\Appointment;
use Modules\Appointments\Services\AppointmentEscrowService;
use Modules\Consultation\Models\Consultation;
use Modules\Doctors\Models\AppointmentReschedule;
use Modules\Doctors\Models\DoctorDayAction;

class DoctorDayActionService
{
    public function __construct(
        protected AppointmentEscrowService $escrow
    ) {
    }

    public function cancelDay($doctorId, $date, $reason = null): array
    {
        $day = Carbon::parse($date)->toDateString();

        return DB::transaction(function () use ($doctorId, $day, $reason) {
            $action = DoctorDayAction::create([
                'doctor_id' => $doctorId,
                'date' => $day,
                'action' => 'cancel',
                'reason' => $reason,
            ]);

            $appointments = Appointment::query()
                ->where('doctor_id', $doctorId)
                ->whereDate('appointment_date', $day)
                ->whereNotIn('status', ['cancelled', 'completed'])
                ->get();

            foreach ($appointments as $appointment) {
                $appointment->update([
                    'status' => 'cancelled',
                    'cancel_reason' => $reason ?: 'Cancelled by doctor for this day',
                ]);

                $this->escrow->releaseIfHeld($appointment->fresh());

                if ($appointment->appointment_type === 'video') {
                    Consultation::where('appointment_id', $appointment->id)
                        ->update(['status' => 'cancelled']);
                }

                AppointmentReschedule::query()
                    ->where('appointment_id', $appointment->id)
                    ->where('status', 'pending')
                    ->update(['status' => 'rejected']);
            }

            return [
                'action' => $action,
                'cancelled_count' => $appointments->count(),
            ];
        });
    }

    public function delayDay($doctorId, $date, $minutes, $reason = null): array
    {
        $day = Carbon::parse($date)->toDateString();
        $minutes = (int) $minutes;

        return DB::transaction(function () use ($doctorId, $day, $minutes, $reason) {
            $appointments = Appointment::query()
                ->with(['schedule', 'user'])
                ->where('doctor_id', $doctorId)
                ->whereDate('appointment_date', $day)
                ->whereNotIn('status', ['cancelled', 'completed', 'rejected'])
                ->get();

            $created = [];

            foreach ($appointments as $appointment) {
                $pendingExists = AppointmentReschedule::query()
                    ->where('appointment_id', $appointment->id)
                    ->where('status', 'pending')
                    ->exists();

                if ($pendingExists) {
                    continue;
                }

                $oldTime = $appointment->schedule?->start_time ?? '00:00:00';
                $newTime = Carbon::parse($oldTime)->addMinutes($minutes)->format('H:i');

                $created[] = AppointmentReschedule::create([
                    'appointment_id' => $appointment->id,
                    'old_date' => $day,
                    'old_time' => $oldTime,
                    'new_date' => $day,
                    'new_time' => $newTime,
                    'reason' => $reason ?: "Doctor delayed {$minutes} minutes",
                    'created_by' => auth()->id(),
                    'status' => 'pending',
                ]);
            }

            DoctorDayAction::create([
                'doctor_id' => $doctorId,
                'date' => $day,
                'action' => 'delay',
                'delay_minutes' => $minutes,
                'reason' => $reason,
            ]);

            return [
                'message' => 'Delay requests created successfully',
                'appointments_count' => count($created),
                'requests' => collect($created)->map->toApiArray()->values()->all(),
            ];
        });
    }
}
