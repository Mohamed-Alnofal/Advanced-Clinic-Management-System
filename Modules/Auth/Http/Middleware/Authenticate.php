<?php

namespace Modules\Auth\Http\Middleware;

use Illuminate\Auth\Middleware\Authenticate as Middleware;
use Illuminate\Http\Request;

class Authenticate extends Middleware
{
    /**
     * API routes must never redirect to a named web "login" route
     * (it is not registered) — that throws RouteNotFoundException → HTTP 500.
     */
    protected function redirectTo(Request $request): ?string
    {
        if ($request->expectsJson() || $request->is('api/*') || $request->is('api')) {
            return null;
        }

        // No web login named route in this API-first app.
        return null;
    }
}
