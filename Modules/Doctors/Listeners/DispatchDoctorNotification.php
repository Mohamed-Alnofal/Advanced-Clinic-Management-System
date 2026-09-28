<?php
namespace Modules\Doctors\Listeners;

use Modules\Doctors\Jobs\SendDoctorNotificationJob;

class DispatchDoctorNotification
{
    public function handle($event): void
    {
        dispatch(new SendDoctorNotificationJob($event));
    }
}