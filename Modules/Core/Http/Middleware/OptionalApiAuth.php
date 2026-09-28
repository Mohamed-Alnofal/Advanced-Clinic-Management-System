<?php

namespace Modules\Core\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Tymon\JWTAuth\Facades\JWTAuth;

class OptionalApiAuth
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->bearerToken()) {
            try {
                $user = JWTAuth::parseToken()->authenticate();
                auth('api')->setUser($user);
            } catch (\Throwable) {
                // Continue as guest when token is missing, expired, or invalid.
            }
        }

        return $next($request);
    }
}
