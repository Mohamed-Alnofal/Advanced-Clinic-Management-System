<?php

namespace Modules\Laboratory\Observers;

use Modules\Laboratory\Models\LaboratoryOrder;
use Modules\Notifications\Services\NotificationService;
use Modules\Users\Models\User;
use Throwable;

class LaboratoryOrderObserver
{
    public function created(LaboratoryOrder $order): void
    {
        try {
            $order->loadMissing([
                'doctor.user',
                'patient',
                'laboratory.user',
            ]);

            $laboratoryUser = $order->laboratory?->user;
            if (! $laboratoryUser instanceof User) {
                return;
            }

            app(NotificationService::class)->sendToUser(
                $laboratoryUser,
                'New Laboratory Order',
                'You have received a new laboratory order.',
                [
                    'type' => 'laboratory_order',
                    'laboratory_order_id' => $order->id,
                    'medical_visit_id' => $order->medical_visit_id,
                    'doctor_id' => $order->doctor_id,
                    'patient_id' => $order->patient_id,
                ]
            );
        } catch (Throwable $e) {
            report($e);
        }
    }
}
