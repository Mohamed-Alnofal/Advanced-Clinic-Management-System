<?php

namespace Modules\Appointments\Services;

use Illuminate\Support\Facades\Schema;
use Modules\Appointments\Models\Appointment;
use Modules\Users\Models\User;
use Carbon\Carbon;

class DoctorAppointmentService
{
    /**
     * عرض الطلبات المعلقة للطبيب
     */
    public function getPendingAppointments($doctorId, ?int $clinicId = null)
    {
        $query = Appointment::with($this->relations())
            ->where('doctor_id', $doctorId)
            ->where('status', 'pending')
            ->where(function ($q) {
                $q->where('appointment_type', 'clinic')
                    ->orWhereNull('appointment_type');
            });
        if ($clinicId) {
            $query->where('clinic_id', $clinicId);
        }

        return $query->orderBy('created_at', 'desc')
            ->get()
            ->map(fn($appointment) => $this->formatForDoctor($appointment));
    }
    

public function getUpcomingAppointments($doctorId, ?int $clinicId = null)
{
    $query = Appointment::with($this->relations())
        ->where('doctor_id', $doctorId)
        ->where('status', 'accepted');
    if ($clinicId) {
        $query->where('clinic_id', $clinicId);
    }

    return $query->get()
        ->filter(fn ($appointment) => $appointment->isStillUpcoming())
        ->sortBy(function ($appointment) {
            return optional($appointment->scheduledStartAt())?->timestamp ?? PHP_INT_MAX;
        })
        ->values()
        ->map(fn($appointment) => $this->formatForDoctor($appointment));
}
    
    /**
     * عرض جميع مواعيد الطبيب
     */
    public function getAllAppointments($doctorId, $status = null, ?int $clinicId = null)
    {
        $query = Appointment::with($this->relations())
            ->where('doctor_id', $doctorId);
        
        if ($status) {
            $query->where('status', $status);
        }
        if ($clinicId) {
            $query->where('clinic_id', $clinicId);
        }
        
        return $query->orderBy('created_at', 'desc')
            ->get()
            ->map(fn($appointment) => $this->formatForDoctor($appointment));
    }
    
    /**
     * عرض طلبات الفيديو فقط للطبيب
     */
    public function getVideoPendingAppointments($doctorId)
    {
        return Appointment::with($this->relations())
            ->where('doctor_id', $doctorId)
            ->where('appointment_type', 'video')
            ->where('status', 'pending')
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(fn ($appointment) => array_merge(
                $this->formatForDoctor($appointment),
                ['appointment_id' => $appointment->id]
            ));
    }
    
    /**
     * Compact home feed for doctor dashboard (pending + accepted), newest first.
     *
     * @return list<array<string, mixed>>
     */
    public function getHomeFeed(int $doctorId, int $limit = 20): array
    {
        return Appointment::with($this->relations())
            ->where('doctor_id', $doctorId)
            ->whereIn('status', ['pending', 'accepted'])
            ->orderByRaw("CASE status WHEN 'pending' THEN 0 WHEN 'accepted' THEN 1 ELSE 2 END")
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get()
            ->map(fn ($appointment) => $this->formatForDoctor($appointment))
            ->values()
            ->all();
    }

    /**
     * تنسيق البيانات للطبيب
     */
    public function formatForDoctor($appointment)
    {
        $schedule = $appointment->schedule;
        $pending = $this->pendingReschedule($appointment);
        $accepted = $this->latestAcceptedReschedule($appointment);
        $patient = $appointment->user;
        $clinic = $appointment->clinic;
        $center = $clinic?->medicalCenter;

        return [
            'id' => $appointment->id,
            'schedule_id' => $appointment->schedule_id,
            'clinic_id' => $appointment->clinic_id,
            'patient_name' => $patient?->name ?? 'Patient',
            'patient_phone' => $patient?->phone,
            'patient_age' => $patient?->age,
            'patient_gender' => $patient?->gender,
            'day' => optional($appointment->appointment_date)?->toDateString() ?? $schedule?->day,
            'time' => $accepted?->new_time ?? $schedule?->start_time,
            'appointment_date' => optional($appointment->appointment_date)?->toDateString(),
            'consultation_duration' => $schedule?->consultation_duration,
            'appointment_type' => $appointment->appointment_type,
            'booking_for' => $appointment->booking_for,
            'status' => $appointment->status,
            'created_at' => $appointment->created_at,
            'consultation_id' => $appointment->consultation?->id,
            'consultation_status' => $appointment->consultation?->status,
            'room_name' => $appointment->consultation?->room_name,
            'pending_reschedule' => $pending?->toApiArray(),
            'clinic' => $clinic ? [
                'id' => (int) $clinic->id,
                'name' => $center?->name ?? $center?->clinic_name ?? ('Clinic #'.$clinic->id),
                'address' => $center?->address ?? $center?->location,
            ] : null,
            'clinic_name' => $center?->name ?? $center?->clinic_name,
        ];
    }

    private function pendingReschedule($appointment)
    {
        return $appointment->reschedules
            ?->where('status', 'pending')
            ->sortByDesc('id')
            ->first();
    }

    private function latestAcceptedReschedule($appointment)
    {
        return $appointment->reschedules
            ?->where('status', 'accepted')
            ->sortByDesc('id')
            ->first();
    }

    /** @return list<string> */
    private function relations(): array
    {
        $with = ['user', 'schedule', 'consultation', 'clinic.medicalCenter'];
        if (Schema::hasTable('appointment_reschedules')) {
            $with[] = 'reschedules';
        }

        return $with;
    }
}