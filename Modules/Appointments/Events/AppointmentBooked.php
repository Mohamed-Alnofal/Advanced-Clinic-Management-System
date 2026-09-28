<?php
namespace Modules\Appointments\Events;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Modules\Appointments\Models\Appointment;
use Modules\Users\Models\User;

class AppointmentBooked
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public Appointment $appointment,
        public User $patient
    ) {}
}