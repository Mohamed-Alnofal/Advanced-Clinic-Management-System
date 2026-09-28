<?php

namespace Modules\Ambulance\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Modules\Ambulance\Models\AmbulanceCompany;
use Modules\Ambulance\Models\AmbulanceUnit;
use Modules\Core\Http\Controllers\Controller;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AmbulanceUnitStreamController extends Controller
{
    private const MAX_CONNECTIONS_PER_COMPANY = 2;

    /** Keep short: php artisan serve is single-threaded; long SSE starves all APIs. */
    private const POLL_SECONDS = 5;

    private const MAX_ITERATIONS = 24;

    public function stream(Request $request): StreamedResponse
    {
        $user = $this->resolveStreamUser($request);
        if (! $user) {
            abort(401, 'Unauthenticated');
        }

        $hasAmbulanceRole = method_exists($user, 'hasRole')
            ? $user->hasRole('ambulance')
            : (string) ($user->role ?? '') === 'ambulance';

        if (! $hasAmbulanceRole) {
            abort(403, 'Ambulance role required.');
        }

        $companyId = AmbulanceCompany::where('user_id', $user->id)->value('id');
        if (! $companyId) {
            abort(403, 'Ambulance company not found for this user.');
        }

        $connectionKey = "sse:ambulance:company:{$companyId}:connections";
        $connectionId = uniqid('sse_', true);

        if (! $this->acquireConnectionSlot($connectionKey, $connectionId)) {
            abort(429, 'Too many live unit streams for this company. Close another dashboard tab.');
        }

        return response()->stream(function () use ($companyId, $connectionKey, $connectionId) {
            $lastFingerprint = null;

            try {
                for ($i = 0; $i < self::MAX_ITERATIONS; $i++) {
                    if (connection_aborted()) {
                        break;
                    }

                    $fingerprint = (string) AmbulanceUnit::query()
                        ->where('ambulance_company_id', $companyId)
                        ->max('updated_at');

                    if ($fingerprint && $fingerprint !== $lastFingerprint) {
                        $lastFingerprint = $fingerprint;
                        echo "event: units_updated\n";
                        echo 'data: '.json_encode(['fingerprint' => $fingerprint])."\n\n";
                    } else {
                        echo ": heartbeat\n\n";
                    }

                    if (ob_get_level() > 0) {
                        ob_flush();
                    }
                    flush();

                    sleep(self::POLL_SECONDS);
                }
            } finally {
                $this->releaseConnectionSlot($connectionKey, $connectionId);
            }
        }, 200, [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache',
            'Connection' => 'keep-alive',
            'X-Accel-Buffering' => 'no',
        ]);
    }

    private function acquireConnectionSlot(string $connectionKey, string $connectionId): bool
    {
        try {
            $slots = Cache::get($connectionKey, []);
            if (! is_array($slots)) {
                $slots = [];
            }

            $slots = array_filter(
                $slots,
                fn ($expiresAt) => is_numeric($expiresAt) && (int) $expiresAt > time()
            );

            if (count($slots) >= self::MAX_CONNECTIONS_PER_COMPANY) {
                return false;
            }

            $slots[$connectionId] = time() + (self::MAX_ITERATIONS * self::POLL_SECONDS) + 60;
            Cache::put($connectionKey, $slots, now()->addMinutes(15));

            return true;
        } catch (\Throwable $e) {
            Log::warning('Ambulance SSE: connection limiter unavailable', [
                'error' => $e->getMessage(),
            ]);

            return true;
        }
    }

    private function releaseConnectionSlot(string $connectionKey, string $connectionId): void
    {
        try {
            $slots = Cache::get($connectionKey, []);
            if (! is_array($slots)) {
                return;
            }

            unset($slots[$connectionId]);
            Cache::put($connectionKey, $slots, now()->addMinutes(15));
        } catch (\Throwable $e) {
            Log::warning('Ambulance SSE: failed releasing connection slot', [
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function resolveStreamUser(Request $request)
    {
        if ($request->user()) {
            return $request->user();
        }

        $token = $request->query('token');
        if (! $token) {
            return null;
        }

        try {
            return \Tymon\JWTAuth\Facades\JWTAuth::setToken($token)->authenticate();
        } catch (\Throwable) {
            return null;
        }
    }
}
