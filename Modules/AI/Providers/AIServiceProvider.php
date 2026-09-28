<?php

namespace Modules\AI\Providers;

use Illuminate\Support\ServiceProvider;
use Modules\AI\Services\GroqMedicalAiService;
use Modules\AI\Services\UnifiedMedicalAiService;

class AIServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(UnifiedMedicalAiService::class);
        $this->app->singleton(GroqMedicalAiService::class);
        $this->app->register(RouteServiceProvider::class);
    }

    public function boot(): void
    {
        //
    }
}
