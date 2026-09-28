<?php

namespace App\Services;

use App\Contracts\Subscribable;
use Illuminate\Validation\ValidationException;

class SubscribableResolver
{
    public function resolve($user): Subscribable
    {
        $user->loadMissing(['doctor', 'pharmacist', 'homeCareProfile']);

        // Doctor app is the primary subscriber. Patients are not eligible.
        if ($user->doctor) {
            return $user->doctor;
        }

        if ($user->pharmacist) {
            return $user->pharmacist;
        }

        if ($user->homeCareProfile && $user->homeCareProfile->status === 'approved') {
            return $user->homeCareProfile;
        }

        throw ValidationException::withMessages([
            'user' => 'هذا المستخدم غير مؤهل للاشتراك.',
        ]);
    }
}