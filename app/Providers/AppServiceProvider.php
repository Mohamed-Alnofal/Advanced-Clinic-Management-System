<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use App\Support\PublicMedia;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    //  Appointment::observe(AppointmentsObserver::class);

    public function boot(): void
    {
        foreach (PublicMedia::publicRoots() as $root) {
            if (! is_dir($root)) {
                @mkdir($root, 0755, true);
            }
        }

        RateLimiter::for('consultation-token', function (Request $request) {
            return Limit::perMinute(30)->by($request->user()?->id ?: $request->ip());
        });

        RateLimiter::for('consultation-start', function (Request $request) {
            return Limit::perHour(60)->by($request->user()?->id ?: $request->ip());
        });

        RateLimiter::for('consultation-join', function (Request $request) {
            return Limit::perMinute(20)->by($request->user()?->id ?: $request->ip());
        });

        RateLimiter::for('api-admin', function (Request $request) {
            return Limit::perMinute(300)->by($request->user()?->id ?: $request->ip());
        });

        RateLimiter::for('api-pharmacy', function (Request $request) {
            return Limit::perMinute(180)->by($request->user()?->id ?: $request->ip());
        });

        RateLimiter::for('api-ambulance', function (Request $request) {
            return Limit::perMinute(120)->by($request->user()?->id ?: $request->ip());
        });

        // Global API ceiling — used if routes attach throttle:api
        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(240)->by($request->user()?->id ?: $request->ip());
        });

        RateLimiter::for('api-ambulance-sse', function (Request $request) {
            return Limit::perMinute(10)->by($request->user()?->id ?: $request->ip());
        });

        RateLimiter::for('api-pharmacy-sse', function (Request $request) {
            return Limit::perMinute(10)->by($request->user()?->id ?: $request->ip());
        });

        // Opt-in only: writing SQL into a multi-hundred-MB laravel.log makes every request crawl.
        if (filter_var(env('LOG_SLOW_QUERIES', false), FILTER_VALIDATE_BOOL)) {
            DB::listen(function (QueryExecuted $query) {
                if ($query->time < 200) {
                    return;
                }

                Log::warning('Slow query detected', [
                    'time_ms' => $query->time,
                    'sql' => $query->sql,
                    'connection' => $query->connectionName,
                ]);
            });
        }

        // PatientAccessRequest::observe(PatientAccessRequestObserver::class);
    }
}
