<?php

namespace Modules\Ambulance\Services;

use Illuminate\Support\Facades\Hash;
use Tymon\JWTAuth\Facades\JWTAuth;
use Modules\Ambulance\Models\AmbulanceDriver;

class AmbulanceDriverAuthService
{
    public function login(string $email, string $password)
    {
        $driver = AmbulanceDriver::with('unit')
            ->where('email', $email)
            ->first();

        if (!$driver) {
            return response()->json([
                'message' => 'Driver not found.'
            ], 404);
        }

        if (! Hash::check($password, $driver->password)) {
            return response()->json([
                'message' => 'Invalid credentials.'
            ], 401);
        }

        if (! $driver->is_active) {
            return response()->json([
                'message' => 'Driver account is inactive.'
            ], 403);
        }

        if (! $driver->unit) {
            return response()->json([
                'message' => 'No ambulance assigned to this driver.'
            ], 403);
        }

        $token = JWTAuth::fromUser($driver);

        return [
            'message' => 'Login successful.',
            'access_token' => $token,
            'token_type' => 'bearer',
            'expires_in' => auth('api')->factory()->getTTL() * 60,
            'driver' => $driver,
        ];
    }

    public function logout()
    {
        JWTAuth::invalidate(JWTAuth::getToken());

        return [
            'message' => 'Logged out successfully.'
        ];
    }
}