<?php

namespace Modules\Doctors\Services;

use Carbon\Carbon;
use Illuminate\Validation\ValidationException;
use Modules\Appointments\Models\Appointment;
use Modules\Doctors\Models\AppointmentReschedule;

class AppointmentRescheduleService
{
    public function requestReschedule(Appointment $appointment, array $data): AppointmentReschedule
    {
        $this->assertCallerIsDoctorOf($appointment);
        $this->assertAppointmentCanMove($appointment);

        $alreadyPending = AppointmentReschedule::query()
            ->where('appointment_id', $appointment->id)
            ->where('status', 'pending')
            ->exists();

        if ($alreadyPending) {
            throw ValidationException::withMessages([
                'appointment' => 'A reschedule request is already waiting for the patient.',
            ]);
        }

        $appointment->loadMissing('schedule');

        $newDate = Carbon::parse($data['new_date'])->toDateString();
        $newTime = Carbon::parse($data['new_time'])->format('H:i');

        return AppointmentReschedule::create([
            'appointment_id' => $appointment->id,
            'old_date' => optional($appointment->appointment_date)?->toDateString(),
            'old_time' => $appointment->schedule?->start_time,
            'new_date' => $newDate,
            'new_time' => $newTime,
            'reason' => $data['reason'] ?? null,
            'status' => 'pending',
            'created_by' => auth()->id(),
        ]);
    }

    public function accept(AppointmentReschedule $reschedule): array
    {
        $appointment = $reschedule->appointment;
        abort_if(! $appointment, 404);
        abort_if((int) $appointment->user_id !== (int) auth()->id(), 403);

        if ($reschedule->status !== 'pending') {
            throw ValidationException::withMessages([
                'reschedule' => 'This reschedule request is no longer pending.',
            ]);
        }

        $appointment->update([
            'appointment_date' => Carbon::parse($reschedule->new_date)->toDateString(),
        ]);

        $reschedule->update(['status' => 'accepted']);

        return [
            'status' => true,
            'message' => 'Reschedule accepted. Your appointment was moved to the new time.',
            'reschedule' => $reschedule->fresh()?->toApiArray(),
            'appointment' => $appointment->fresh(['user', 'schedule', 'consultation', 'reschedules']),
        ];
    }

    public function reject(AppointmentReschedule $reschedule): array
    {
        $appointment = $reschedule->appointment;
        abort_if(! $appointment, 404);
        abort_if((int) $appointment->user_id !== (int) auth()->id(), 403);

        if ($reschedule->status !== 'pending') {
            throw ValidationException::withMessages([
                'reschedule' => 'This reschedule request is no longer pending.',
            ]);
        }

        $reschedule->update(['status' => 'rejected']);

        return [
            'status' => true,
            'message' => 'Reschedule rejected. Your original appointment time is unchanged.',
            'reschedule' => $reschedule->fresh()?->toApiArray(),
        ];
    }

    private function assertCallerIsDoctorOf(Appointment $appointment): void
    {
        $doctor = auth()->user()?->doctor;
        abort_if(! $doctor, 403);
        abort_if((int) $appointment->doctor_id !== (int) $doctor->id, 403);
    }

    private function assertAppointmentCanMove(Appointment $appointment): void
    {
        if (in_array($appointment->status, ['cancelled', 'completed', 'rejected'], true)) {
            throw ValidationException::withMessages([
                'appointment' => 'This appointment can no longer be rescheduled.',
            ]);
        }
    }
}
