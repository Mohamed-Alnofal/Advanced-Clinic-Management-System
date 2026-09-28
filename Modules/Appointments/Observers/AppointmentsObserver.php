<?php

namespace Modules\Appointments\Observers;

use Modules\Appointments\Models\Appointment;
// use Modules\Appointments\Services\FirebaseService;
use Modules\Notifications\Services\NotificationService;
// use Modules\Appointments\Services\NotificationService;
use Modules\Users\Models\FcmToken;
use Modules\Users\Models\User;

class AppointmentsObserver
{
     public function created(Appointment $appointment)
    {
        app(NotificationService::class)->sendToUser(
            $appointment->doctor->user,
            'New Appointment 📅',
            'You have a new appointment booking',
            [
                'appointment_id' => $appointment->id
            ]
        );
    }
 

    public function updated(Appointment $appointment): void
{
    if ($appointment->wasChanged('status')) {

        if ($appointment->status === 'pending') {
            $this->sendUpdatedAppointmentNotification($appointment);
        }

        if ($appointment->status === 'cancelled') {
            $this->sendCancelledAppointmentNotification($appointment);
        }

        if ($appointment->status === 'accepted') {
            $this->notifyUserAppointmentConfirmed($appointment);
            $this->notifyDoctorAppointmentAccepted($appointment);
        }

        if ($appointment->status === 'rejected') {
            $this->notifyUserAppointmentRejected($appointment);
        }
    }

    if ($appointment->isDirty('date') || $appointment->isDirty('time')) {
        $this->sendRescheduledNotification($appointment);
    }
}
private function sendRescheduledNotification(Appointment $appointment): void
{
    app(NotificationService::class)->sendToUser(
        $appointment->doctor->user,
        'Appointment Updated 🔄',
        'An appointment schedule has been updated.',
        [
            'appointment_id' => $appointment->id,
            'date' => $appointment->date,
            'time' => $appointment->time
        ]
    );
}
private function sendUpdatedAppointmentNotification(Appointment $appointment): void
{
    app(NotificationService::class)->sendToUser(
        $appointment->doctor->user,
        'Appointment Confirmed ✅',
        'An appointment has been confirmed.',
        [
            'appointment_id' => $appointment->id
        ]
    );
}
private function sendCancelledAppointmentNotification(Appointment $appointment): void
{
    app(NotificationService::class)->sendToUser(
        $appointment->doctor->user,
        'Appointment Cancelled ❌',
        'An appointment has been cancelled.',
        [
            'appointment_id' => $appointment->id
        ]
    );
}
private function notifyUserAppointmentConfirmed(Appointment $appointment): void
{
    $appointment->loadMissing(['doctor.user', 'schedule']);
    $start = $appointment->scheduledStartAt();
    $when = $start ? $start->format('Y-m-d H:i') : '';
    $isVideo = $appointment->isVideoCall();

    app(NotificationService::class)->sendToUser(
        $appointment->user,
        $isVideo ? 'Video visit confirmed' : 'Appointment Confirmed ✅',
        $isVideo
            ? 'Dr. '.($appointment->doctor?->user?->name ?? 'your doctor').' accepted your video visit'.($when ? " on {$when}" : '').'. You can join 15 minutes before the appointment.'
            : 'Your appointment has been accepted by the doctor.'.($when ? " It is scheduled for {$when}." : ''),
        [
            'appointment_id' => (string) $appointment->id,
            'type' => $isVideo ? 'video_call_reminder' : 'appointment_accepted',
        ],
        $isVideo ? 'video_call_reminder' : 'appointment_accepted'
    );
}

private function notifyDoctorAppointmentAccepted(Appointment $appointment): void
{
    $appointment->loadMissing(['user', 'doctor.user', 'schedule']);
    $start = $appointment->scheduledStartAt();
    $when = $start ? $start->format('Y-m-d H:i') : '';
    $patient = $appointment->user->name ?? 'the patient';
    $isVideo = $appointment->isVideoCall();

    app(NotificationService::class)->sendToUser(
        $appointment->doctor?->user,
        $isVideo ? 'Video visit is on your calendar' : 'Appointment accepted',
        $isVideo
            ? "Video visit with {$patient}".($when ? " at {$when}" : '').'. We will remind you 15 minutes before so you can start the call.'
            : "Clinic visit with {$patient}".($when ? " at {$when}" : '').' is confirmed.',
        [
            'appointment_id' => (string) $appointment->id,
            'type' => $isVideo ? 'video_call_reminder' : 'appointment_accepted',
        ],
        $isVideo ? 'video_call_reminder' : 'appointment_accepted'
    );
}
private function notifyUserAppointmentCancelled(Appointment $appointment): void
{
    app(NotificationService::class)->sendToUser(
        $appointment->user,
        'Appointment Cancelled ❌',
        'Your appointment has been cancelled by the doctor.',
        [
            'appointment_id' => $appointment->id
        ]
    );
}
private function notifyUserAppointmentRejected(Appointment $appointment): void
{
    $reason = $appointment->reject_reason ?? 'No reason provided';

    app(NotificationService::class)->sendToUser(
        $appointment->user,
        'Appointment Rejected ❌',
        "Your appointment has been rejected. Reason: {$reason}",
        [
            'appointment_id' => $appointment->id,
            'doctor_name' => $appointment->doctor->user->name,
            'reason' => $reason
        ]
    );
}
}