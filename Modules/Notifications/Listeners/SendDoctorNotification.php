<?php

namespace Modules\Notifications\Listeners;

use App\Events\AppointmentBooked;
use App\Services\FcmService;

class SendDoctorNotification
{
    public function __construct(
        private FcmService $fcmService
    ) {}

    public function handle(AppointmentBooked $event)
    {
        $doctor = $event->appointment->doctor->user;

        $this->fcmService->sendToUser(
            $doctor->id,
            'New Appointment',
            'You have a new booking from ' . $event->patient->name,
            [
                'appointment_id' => $event->appointment->id,
                'type' => 'appointment'
            ]
        );
    }
}
