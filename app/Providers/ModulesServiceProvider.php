<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class ModulesServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        foreach (glob(base_path('Modules/*'), GLOB_ONLYDIR) as $module) {
            // nwidart loads providers from module.json — avoid double registration
            if (file_exists($module . '/module.json')) {
                continue;
            }

            $moduleName = basename($module);

            $provider = "Modules\\$moduleName\\Providers\\{$moduleName}ServiceProvider";

            if (class_exists($provider)) {
                $this->app->register($provider);
            }
        }
    }

    public function boot(): void
    {
        //
    }
}