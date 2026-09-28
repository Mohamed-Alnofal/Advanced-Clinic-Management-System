<?php

namespace Modules\Appointments\Providers;

use Illuminate\Support\ServiceProvider;
use Modules\Appointments\Models\Appointment;
use Modules\Appointments\Observers\AppointmentsObserver;

// use Modules\Appointments\Observers\AppointmenstObserver;

class AppointmentsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // تسجيل EventServiceProvider
        // $this->app->register(
        //     \Modules\Appointments\Providers\EventServiceProvider::class
        // );
    }


public function boot(): void
{
    $this->loadMigrationsFrom(module_path('Appointments', 'database/migrations'));
    Appointment::observe(AppointmentsObserver::class);
}
}