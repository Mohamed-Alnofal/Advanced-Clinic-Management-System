<?php

namespace Modules\Users\Providers;

use Illuminate\Support\ServiceProvider;
use Modules\Doctors\Models\Doctor;
use Modules\Doctors\Observers\DoctorObserver;
use Modules\Users\Models\User;
use Modules\Users\Observers\UserObserver;

class UsersServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // تسجيل EventServiceProvider
        // $this->app->register(
        //     \Modules\Users\Providers\EventServiceProvider::class
        // );
    }

    public function boot(): void
    {
        User::observe(UserObserver::class);
    }
}