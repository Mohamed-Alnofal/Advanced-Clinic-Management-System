<?php

namespace Modules\Doctors\Observers;

use Modules\Doctors\Models\DoctorDayAction;
use Modules\Appointments\Models\Appointment;
use Modules\Notifications\Services\NotificationService;

class DoctorDayActionObserver
{
    /**
     * عند إنشاء إجراء على يوم الطبيب
     */
    public function created(DoctorDayAction $action): void
    {
        /*
        |--------------------------------------------------------------------------
        | نحن نريد إشعار المرضى فقط عند إلغاء اليوم
        |--------------------------------------------------------------------------
        */

        if ($action->action !== 'cancel') {
            return;
        }


        /*
        |--------------------------------------------------------------------------
        | جلب جميع الحجوزات الخاصة بالطبيب في هذا اليوم
        |--------------------------------------------------------------------------
        */

        $appointments = Appointment::where(
                'doctor_id',
                $action->doctor_id
            )
            ->whereDate('appointment_date', $action->date)
            ->whereNotIn('status', [
                'cancelled',
                'completed',
                'rejected',
            ])
            ->with('user')
            ->get();


        /*
        |--------------------------------------------------------------------------
        | إرسال إشعار لكل مريض
        |--------------------------------------------------------------------------
        */

        foreach ($appointments as $appointment) {

            if (!$appointment->user) {
                continue;
            }


            app(NotificationService::class)->sendToUser(
                $appointment->user,

                'Appointment Cancelled ❌',

                'Your appointment has been cancelled because the doctor is unavailable.',

                [
                    'appointment_id' => $appointment->id,

                    'doctor_day_action_id' => $action->id,

                    'date' => $action->date,

                    'reason' => $action->reason,

                    'action' => 'day_cancel',
                ]
            );
        }
    }
}