<?php

namespace Modules\Audit\Providers;

use Illuminate\Support\Facades\Route;
use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;

class RouteServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        parent::boot();
    }

    public function map(): void
    {
        $this->mapApiRoutes();
        $this->mapWebRoutes();
    }

    protected function mapWebRoutes(): void
    {
        if (file_exists(module_path('Audit', 'Routes/web.php'))) {
            Route::middleware('web')
                ->group(module_path('Audit', 'Routes/web.php'));
        }
    }

    protected function mapApiRoutes(): void
    {
        if (file_exists(module_path('Audit', 'Routes/api.php'))) {
            Route::prefix('api')
                ->middleware('api')
                ->group(module_path('Audit', 'Routes/api.php'));
        }
    }
}