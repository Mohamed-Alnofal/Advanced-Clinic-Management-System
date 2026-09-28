<?php
// Modules/Drug/Providers/RouteServiceProvider.php

namespace Modules\Drug\Providers;

use Illuminate\Support\Facades\Route;
use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;

class RouteServiceProvider extends ServiceProvider
{
   public function boot(): void
{
    $this->routes(function () {
        Route::middleware('api')
            ->prefix('api')
            ->group(module_path('Drug', 'routes/api.php'));
    });
}
}