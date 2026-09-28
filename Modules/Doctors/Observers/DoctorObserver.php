<?php
namespace Modules\Doctors\Observers;

// use Modules\Doctor\Models\Doctor;
// use Modules\Doctor\Events\DoctorRegistered;

use Modules\Doctors\Events\DoctorApproved;
use Modules\Doctors\Events\DoctorRegistered;
use Modules\Doctors\Events\DoctorRejected;
use Modules\Doctors\Models\Doctor;
use Modules\Notifications\Services\NotificationService;
use Modules\Users\Models\User;

class DoctorObserver
{
    public function created(Doctor $doctor): void
    {
        // الطبيب سجل → إشعار للأدمن
        event(new DoctorRegistered($doctor));
    }

    //  public function updated(User $doctor): void
    // {
    //     // إذا تم تغيير الحالة فقط
    //     if ($doctor->isDirty('status')) {

    //         // إذا أصبح Approved

    //         if ($doctor->status === 'accepted') {

    //             app(NotificationService::class)->send(
    //                 $doctor->user,
    //                 'Account Approved 🎉',
    //                 'Your doctor account has been approved by admin.'
    //             );
    //         }
    //     }
    // }
}