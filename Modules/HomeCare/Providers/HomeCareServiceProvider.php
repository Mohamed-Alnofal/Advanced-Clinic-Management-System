<?php

namespace Modules\HomeCare\Providers;

use Illuminate\Support\ServiceProvider;

class HomeCareServiceProvider extends ServiceProvider
{
    protected string $moduleName = 'HomeCare';

    protected string $moduleNameLower = 'homecare';

    public function boot(): void
    {
        $this->loadMigrationsFrom(module_path($this->moduleName, 'database/migrations'));
    }

    public function register(): void
    {
        $this->app->register(RouteServiceProvider::class);
    }
}
