<?php

namespace Modules\Clinics\Providers;

use Illuminate\Support\ServiceProvider;

class ClinicsServiceProvider extends ServiceProvider
{
    protected string $moduleName = 'Clinics';

    public function boot(): void
    {
        if (file_exists(base_path("Modules/{$this->moduleName}/Database/Migrations"))) {
            $this->loadMigrationsFrom(base_path("Modules/{$this->moduleName}/Database/Migrations"));
        }
    }

    public function register(): void
    {
        if (file_exists(base_path("Modules/{$this->moduleName}/Providers/RouteServiceProvider.php"))) {
            $this->app->register(RouteServiceProvider::class);
        }
    }
}