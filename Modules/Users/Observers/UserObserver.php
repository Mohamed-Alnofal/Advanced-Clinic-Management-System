<?php

namespace Modules\Users\Observers;

use Modules\Users\Models\User;
use Modules\Notifications\Services\NotificationService;

class UserObserver
{
    public function updated(User $user): void
    {
        // نراقب تغيير status فقط
        if ($user->isDirty('status')) {

            // حالة قبول الطبيب
            if ($user->status === 'accepted') {

                $this->sendDoctorApprovedNotification($user);
            }

            // حالة الرفض (اختياري)
            if ($user->status === 'rejected') {

                $this->sendDoctorRejectedNotification($user);
            }
        }
                    // 🔴 تجميد الحساب
            if ($user->status === 'suspended') {
                $this->sendSuspendedNotification($user);
            }
    }

    private function sendDoctorApprovedNotification(User $user): void
    {
        app(NotificationService::class)->send(
            $user,
            'doctor_status',
            'Account Approved 🎉',
            'Your doctor account has been approved by admin.'
        );
    }

    private function sendDoctorRejectedNotification(User $user): void
    {
        $reason = $user->suspension_reason ?? 'No reason provided';

        app(NotificationService::class)->send(
            $user,
            'doctor_status',
            'Account Rejected ❌',
            'Your doctor account has been rejected by admin.',
            [
                'reason' => $reason
            ]
        );
    }

        private function sendSuspendedNotification(User $user): void
    {
        $reason = $user->suspension_reason ?? 'No reason provided';

        app(NotificationService::class)->send(
            $user,
            'user_status',
            'Account Suspended 🚫',
            "Your account has been suspended. Reason: {$reason}"
        );
    }

    
}