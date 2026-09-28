<?php

namespace Modules\Core\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class HealthController extends Controller
{
    /**
     * Lightweight availability probe for dashboards / load balancers.
     */
    public function __invoke(): JsonResponse
    {
        $checks = [
            'database' => false,
            'cache' => false,
        ];

        try {
            DB::select('select 1');
            $checks['database'] = true;
        } catch (\Throwable $e) {
            Log::error('Health check: database unavailable', ['error' => $e->getMessage()]);
        }

        try {
            $probeKey = 'health:probe:'.uniqid('', true);
            Cache::put($probeKey, 1, 10);
            $checks['cache'] = Cache::pull($probeKey) === 1;
        } catch (\Throwable $e) {
            Log::warning('Health check: cache unavailable', ['error' => $e->getMessage()]);
        }

        $ok = $checks['database'] && $checks['cache'];

        return response()->json([
            'status' => $ok ? 'ok' : 'degraded',
            'checks' => $checks,
            'timestamp' => now()->toIso8601String(),
        ], $ok ? 200 : 503);
    }
}
