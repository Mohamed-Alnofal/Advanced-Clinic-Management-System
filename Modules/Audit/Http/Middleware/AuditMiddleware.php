<?php

namespace Modules\Audit\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Modules\Audit\Models\AuditLog;
use Symfony\Component\HttpFoundation\Response;

class AuditMiddleware
{
    /**
     * Log admin write actions (POST/PUT/PATCH/DELETE) after the request succeeds.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        try {
            if (! auth()->check()) {
                return $response;
            }

            $user = auth()->user();
            if (! method_exists($user, 'hasRole') || ! $user->hasRole('admin')) {
                return $response;
            }

            if (! in_array($request->method(), ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
                return $response;
            }

            // Do not log failed attempts as successful admin mutations.
            if ($response->getStatusCode() >= 400) {
                return $response;
            }

            // Skip reading audit endpoints themselves (no writes there anyway).
            if ($request->is('api/admin/audit/*')) {
                return $response;
            }

            AuditLog::create([
                'user_id' => $user->id,
                'user_email' => $user->email,
                'user_name' => $user->name,
                'role' => 'admin',
                'action' => $this->getActionFromMethod($request->method()),
                'entity_type' => $this->getEntityTypeFromUrl($request->path()),
                'entity_id' => $this->getEntityIdFromUrl($request->path()),
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'url' => $request->fullUrl(),
                'method' => $request->method(),
                'new_values' => $request->except([
                    'password',
                    'password_confirmation',
                    'token',
                    'otp',
                    'current_password',
                ]),
            ]);
        } catch (\Throwable $e) {
            // Never break the real admin action if auditing fails.
            Log::warning('AuditMiddleware failed to write audit log', [
                'error' => $e->getMessage(),
                'url' => $request->fullUrl(),
            ]);
        }

        return $response;
    }

    private function getActionFromMethod(string $method): string
    {
        return match ($method) {
            'POST' => 'create',
            'PUT', 'PATCH' => 'update',
            'DELETE' => 'delete',
            default => 'access',
        };
    }

    private function getEntityTypeFromUrl(string $path): string
    {
        $segments = array_values(array_filter(explode('/', $path)));

        // Prefer a meaningful resource name before a numeric id.
        for ($i = count($segments) - 1; $i >= 0; $i--) {
            if (! ctype_digit((string) $segments[$i])) {
                return $segments[$i];
            }
        }

        return $segments[count($segments) - 1] ?? 'unknown';
    }

    private function getEntityIdFromUrl(string $path): ?int
    {
        $segments = array_values(array_filter(explode('/', $path)));
        $last = end($segments);

        if ($last !== false && ctype_digit((string) $last)) {
            return (int) $last;
        }

        return null;
    }
}
