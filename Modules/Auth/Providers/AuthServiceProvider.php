<?php

namespace Modules\Auth\Providers;

use Illuminate\Support\ServiceProvider;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * اسم الموديول
     */
    protected string $moduleName = 'Auth';

    /**
     * يتم تشغيله عند بدء تحميل التطبيق
     */
    public function boot(): void
    {
        $this->registerConfig();
        $this->loadMigrationsFrom(base_path('Modules/Auth/Database/Migrations'));
    }

    /**
     * تسجيل الـ RouteServiceProvider الخاص بالـ Auth تلقائياً
     */
    public function register(): void
    {
        // هذا السطر يقوم بتشغيل ملف الـ RouteServiceProvider الذي أرسلته أنت تلقائياً
        $this->app->register(RouteServiceProvider::class);
    }

    /**
     * دمج ملف الإعدادات الخاص بالموديول إن وجد
     */
    protected function registerConfig(): void
    {
        if (file_exists(base_path('Modules/Auth/Config/config.php'))) {
            $this->mergeConfigFrom(
                base_path('Modules/Auth/Config/config.php'), 'auth_module'
            );
        }
    }
}