<?php
// Modules/Drug/Providers/DrugServiceProvider.php

namespace Modules\Drug\Providers;

use Illuminate\Support\ServiceProvider;

class DrugServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // تسجيل الخدمات
        $this->app->bind(\Modules\Drug\Services\DrugService::class, function ($app) {
            return new \Modules\Drug\Services\DrugService();
        });
    }

  public function boot(): void
{
    // تحميل الـ Routes API
    if (file_exists(__DIR__ . '/../routes/api.php')) {
        $this->loadRoutesFrom(__DIR__ . '/../routes/api.php');
    }

    // ✅ هذا هو الحل الأساسي
    $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');
}
}