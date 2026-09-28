<?php

namespace Modules\HomeCare\Services;

use Modules\HomeCare\Models\HomeCareProfile;
use Modules\HomeCare\Models\HomeCareRequest;
use Carbon\Carbon;
use Spatie\Permission\Models\Role;

class AdminHomeCareService
{
    public function getPendingProfiles()
    {
        return HomeCareProfile::with(['user', 'specialty'])
            ->where('status', 'pending')
            ->orderBy('created_at', 'asc')
            ->get();
    }

    public function approveProfile($id, $notes = null)
    {
        $profile = HomeCareProfile::findOrFail($id);

        if ($profile->status === 'approved') {
            throw new \RuntimeException('هذا الطلب تمت الموافقة عليه مسبقاً');
        }

        $profile->update([
            'status' => 'approved',
            'approved_at' => Carbon::now(),
            'notes' => $notes ?? $profile->notes,
        ]);

        $user = $profile->user;
        if ($user && ! $user->hasRole('provider')) {
            Role::findOrCreate('provider', 'api');
            $user->assignRole('provider');
        }

        return $profile->fresh(['user', 'specialty']);
    }

    public function rejectProfile($id, $reason)
    {
        $profile = HomeCareProfile::findOrFail($id);

        if ($profile->status === 'approved') {
            throw new \RuntimeException('لا يمكن رفض طلب تمت الموافقة عليه مسبقاً');
        }

        $profile->update([
            'status' => 'suspended',
            'notes' => $reason,
        ]);

        return $profile->fresh(['user', 'specialty']);
    }
}