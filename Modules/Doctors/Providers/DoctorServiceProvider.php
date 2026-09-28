<?php

namespace Modules\Doctors\Providers;

use Illuminate\Support\ServiceProvider;
use Modules\Appointments\Observers\AppointmentsObserver;
use Modules\Doctors\Models\AppointmentReschedule;
use Modules\Doctors\Models\Doctor;
use Modules\Doctors\Models\DoctorDayAction;
use Modules\Doctors\Observers\DoctorObserver;
use Modules\Doctors\Models\DoctorSchedule;
use Modules\Doctors\Observers\AppointmentRescheduleObserver;
use Modules\Doctors\Observers\DoctorDayActionObserver;
use Modules\Doctors\Observers\DoctorScheduleObserver;
class DoctorServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // تسجيل EventServiceProvider
        $this->app->register(
            \Modules\Doctors\Providers\EventServiceProvider::class
        );
    }

 public function boot(): void
{
    $this->loadMigrationsFrom(module_path('Doctors', 'database/migrations'));

    // Doctor::observe(DoctorObserver::class);
    // Doctor::observe(AppointmentsObserver::class);
        AppointmentReschedule::observe(AppointmentRescheduleObserver::class);
            DoctorDayAction::observe(
        DoctorDayActionObserver::class
    );

}
}