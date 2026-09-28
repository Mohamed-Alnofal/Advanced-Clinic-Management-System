<?php

namespace Modules\Appointments\Services;

use Illuminate\Support\Facades\Schema;
use Modules\Appointments\Models\Appointment;
use Modules\Users\Models\User;
use Carbon\Carbon;

class PatientAppointmentService
{
    /**
     * عرض المواعيد القادمة للمريض (المقبولة فقط)
     */
   public function getUpcomingAppointments(User $user)
    {
        return Appointment::with($this->relations())
            ->where('user_id', $user->id)
            ->where('status', 'accepted')
            ->get()
            ->filter(fn ($appointment) => $appointment->isStillUpcoming())
            ->sortBy(function ($appointment) {
                return optional($appointment->scheduledStartAt())?->timestamp ?? PHP_INT_MAX;
            })
            ->values()
            ->map(fn($appointment) => $this->formatForPatient($appointment));
    }
    
    /**
     * عرض جميع مواعيد المريض
     */
    public function getAllAppointments(User $user)
    {
        return Appointment::with($this->relations())
            ->where('user_id', $user->id)
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(fn($appointment) => $this->formatForPatient($appointment));
    }
    
    /**
     * عرض استشارات الفيديو فقط للمريض
     */
    public function getVideoAppointments(User $user)
    {
        return Appointment::with($this->relations())
            ->where('user_id', $user->id)
            ->where('appointment_type', 'video')
            ->whereIn('status', ['pending', 'accepted'])
            ->where(function ($query) {
                $query->whereDoesntHave('consultation')
                    ->orWhereHas('consultation', function ($q) {
                        $q->whereNotIn('status', ['completed', 'cancelled']);
                    });
            })
            ->latest()
            ->get()
            ->filter(fn ($appointment) => $appointment->schedule !== null)
            ->map(fn ($appointment) => array_merge($this->formatForPatient($appointment), [
                'appointment_id' => $appointment->id,
            ]))
            ->values();
    }
    
    /**
     * التحقق من المواعيد القريبة (للتذكير)
     */
    public function checkNearbyAppointments(User $user)
    {
        $now = Carbon::now();
        
        return Appointment::with($this->relations())
            ->where('user_id', $user->id)
            ->where('status', 'accepted')
            ->whereHas('schedule', function ($q) use ($now) {
                $q->whereBetween('start_time', [
                    $now->copy()->addMinutes(5),
                    $now->copy()->addHours(2)
                ]);
            })
            ->get()
            ->map(fn($appointment) => [
                'id' => $appointment->id,
                'doctor_name' => $appointment->doctor?->user?->name ?? 'Doctor',
                'time' => $appointment->schedule?->start_time,
                'minutes_left' => $appointment->schedule?->start_time
                    ? Carbon::parse($appointment->schedule->start_time)->diffInMinutes($now)
                    : null,
            ]);
    }
    
    /**
     * تنسيق البيانات للمريض
     */
    private function formatForPatient($appointment)
    {
        $schedule = $appointment->schedule;
        $pending = $appointment->reschedules
            ?->where('status', 'pending')
            ->sortByDesc('id')
            ->first();
        $accepted = $appointment->reschedules
            ?->where('status', 'accepted')
            ->sortByDesc('id')
            ->first();
        $clinic = $appointment->clinic;
        $center = $clinic?->medicalCenter;

        return [
            'id' => $appointment->id,
            'doctor_id' => $appointment->doctor_id,
            'schedule_id' => $appointment->schedule_id,
            'clinic_id' => $appointment->clinic_id,
            'doctor_name' => $appointment->doctor?->user?->name ?? 'Doctor',
            'doctor_phone' => $appointment->doctor?->user?->phone,
            'day' => $schedule?->day,
            'time' => $accepted?->new_time ?? $schedule?->start_time,
            'appointment_date' => optional($appointment->appointment_date)?->toDateString(),
            'consultation_duration' => $schedule?->consultation_duration,
            'appointment_type' => $appointment->appointment_type,
            'status' => $appointment->status,
            'consultation_id' => $appointment->consultation?->id,
            'consultation_status' => $appointment->consultation?->status,
            'room_name' => $appointment->consultation?->room_name,
            'created_at' => $appointment->created_at,
            'pending_reschedule' => $pending?->toApiArray(),
            'clinic' => $clinic ? [
                'id' => (int) $clinic->id,
                'name' => $center?->name ?? $center?->clinic_name ?? ('Clinic #'.$clinic->id),
                'address' => $center?->address ?? $center?->location,
            ] : null,
            'clinic_name' => $center?->name ?? $center?->clinic_name,
        ];
    }

    /** @return list<string> */
    private function relations(): array
    {
        $with = ['doctor.user', 'schedule', 'consultation', 'clinic.medicalCenter'];
        if (Schema::hasTable('appointment_reschedules')) {
            $with[] = 'reschedules';
        }

        return $with;
    }
}
