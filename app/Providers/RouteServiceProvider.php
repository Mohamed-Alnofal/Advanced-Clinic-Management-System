<?php

namespace App\Providers;

use Illuminate\Support\Facades\Route;
use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;

class RouteServiceProvider extends ServiceProvider
{
    public const HOME = '/home';

    public function boot(): void
    {
        $this->routes(function () {

            // ================= MAIN API =================
            Route::middleware('api')
                ->prefix('api')
                ->group(base_path('routes/api.php'));

            // ================= MAIN WEB =================
            Route::middleware('web')
                ->group(base_path('routes/web.php'));

            // ================= MODULES API =================
            $modules = glob(base_path('Modules/*'), GLOB_ONLYDIR);

            foreach ($modules as $module) {
                $api = $this->resolveModuleRouteFile($module, 'api.php');

                if ($api !== null) {
                    Route::middleware('api')
                        ->prefix('api')
                        ->group($api);
                }
            }

            // ================= MODULES WEB =================
            foreach ($modules as $module) {
                $web = $this->resolveModuleRouteFile($module, 'web.php');

                if ($web !== null) {
                    Route::middleware('web')
                        ->group($web);
                }
            }
        });
    }

    private function resolveModuleRouteFile(string $modulePath, string $filename): ?string
    {
        foreach (['Routes', 'routes'] as $directory) {
            $path = $modulePath.DIRECTORY_SEPARATOR.$directory.DIRECTORY_SEPARATOR.$filename;

            if (file_exists($path)) {
                return $path;
            }
        }

        return null;
    }
}