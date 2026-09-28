<?php

namespace Modules\Audit\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

class AuditServiceProvider extends ServiceProvider
{
    /**
     * اسم الموديول (يجب أن يطابق اسم المجلد)
     */
    protected string $moduleName = 'Audit';

    /**
     * يتم تشغيله عند بدء تحميل التطبيق (Bootstrapping)
     */
    public function boot(): void
    {
        $this->registerTranslations();
        $this->registerConfig();
        $this->registerViews();
        
        if (is_dir(module_path($this->moduleName, 'Database/Migrations'))) {
            $this->loadMigrationsFrom(module_path($this->moduleName, 'Database/Migrations'));
        }
    }

    /**
     * تسجيل الخدمات داخل الـ Container
     */
    public function register(): void
    {
        if (class_exists(\Modules\Audit\Providers\RouteServiceProvider::class)) {
            $this->app->register(\Modules\Audit\Providers\RouteServiceProvider::class);
        }
    }

    /**
     * تسجيل ملفات الإعدادات (Config)
     */
    protected function registerConfig(): void
    {
        $lowerName = Str::lower($this->moduleName);

        if (file_exists(module_path($this->moduleName, 'Config/config.php'))) {
            $this->publishes([
                module_path($this->moduleName, 'Config/config.php') => config_path($lowerName . '.php'),
            ], 'config');
            
            $this->mergeConfigFrom(
                module_path($this->moduleName, 'Config/config.php'), $lowerName
            );
        }
    }

    /**
     * تسجيل ملفات الـ Views (الواجهات إن وجدت)
     */
    public function registerViews(): void
    {
        $lowerName = Str::lower($this->moduleName);
        $viewPath = resource_path('views/modules/' . $lowerName);
        $sourcePath = module_path($this->moduleName, 'Resources/views');

        if (is_dir($sourcePath)) {
            $this->publishes([
                $sourcePath => $viewPath
            ], ['views', $lowerName . '-module-views']);

            $this->loadViewsFrom(array_merge(($this->app->newValueObject ?? []), [$sourcePath]), $lowerName);
        }
    }

    /**
     * تسجيل ملفات الترجمة (Localization)
     */
    public function registerTranslations(): void
    {
        $lowerName = Str::lower($this->moduleName);
        $langPath = resource_path('lang/modules/' . $lowerName);

        if (is_dir($langPath)) {
            $this->loadTranslationsFrom($langPath, $lowerName);
        } else {
            $sourceLangPath = module_path($this->moduleName, 'Resources/lang');
            if (is_dir($sourceLangPath)) {
                $this->loadTranslationsFrom($sourceLangPath, $lowerName);
            }
        }
    }
}