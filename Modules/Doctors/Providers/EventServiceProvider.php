<?php

namespace Modules\Doctors\Providers;

use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;

class EventServiceProvider extends ServiceProvider
{
    protected $listen = [

        \Modules\Doctors\Events\DoctorRegistered::class => [
            \Modules\Doctors\Listeners\DispatchDoctorNotification::class,
        ],

        \Modules\Doctors\Events\DoctorApproved::class => [
            \Modules\Doctors\Listeners\DispatchDoctorNotification::class,
        ],

        \Modules\Doctors\Events\DoctorRejected::class => [
            \Modules\Doctors\Listeners\DispatchDoctorNotification::class,
        ],
    ];
}