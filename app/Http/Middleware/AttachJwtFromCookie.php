<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Dual-auth helper: if no Bearer header, read JWT from HttpOnly cookie.
 * Existing mobile clients keep sending Authorization headers unchanged.
 */
class AttachJwtFromCookie
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->bearerToken()) {
            $cookieName = config('jwt.cookie_name', 'acms_token');
            $token = $request->cookie($cookieName);
            if ($token) {
                $request->headers->set('Authorization', 'Bearer '.$token);
            }
        }

        return $next($request);
    }
}
