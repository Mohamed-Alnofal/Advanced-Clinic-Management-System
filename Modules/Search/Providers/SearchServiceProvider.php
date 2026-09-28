<?php

namespace Modules\Search\Providers;

use Illuminate\Support\ServiceProvider;

class SearchServiceProvider extends ServiceProvider
{
    /**
     * اسم الموديول (يجب أن يطابق اسم المجلد تماماً)
     */
    protected string $moduleName = 'Search';

    /**
     * يتم تشغيله عند بدء تحميل التطبيق
     */
    public function boot(): void
    {
        $this->registerConfig();
        
        // تحميل ملفات قاعدة البيانات (Migrations) الخاصة بالبحث إن وجدت
        if (is_dir(base_path("Modules/{$this->moduleName}/Database/Migrations"))) {
            $this->loadMigrationsFrom(base_path("Modules/{$this->moduleName}/Database/Migrations"));
        }
    }

    /**
     * تسجيل الخدمات والـ RouteServiceProvider الخاص بالبحث
     */
    public function register(): void
    {
        // تسجيل ملف المسارات (Routes) الخاص بموديول البحث تلقائياً لكي تفتح الـ APIs للـ Flutter
        if (class_exists(\Modules\Search\Providers\RouteServiceProvider::class)) {
            $this->app->register(\Modules\Search\Providers\RouteServiceProvider::class);
        }
    }

    /**
     * دمج ملف إعدادات الموديول
     */
    protected function registerConfig(): void
    {
        if (file_exists(base_path("Modules/{$this->moduleName}/Config/config.php"))) {
            $this->mergeConfigFrom(
                base_path("Modules/{$this->moduleName}/Config/config.php"), 'search'
            );
        }
    }
}