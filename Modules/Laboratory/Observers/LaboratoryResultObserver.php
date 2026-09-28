<?php

namespace Modules\Laboratory\Observers;

use Modules\Laboratory\Models\LaboratoryResult;
use Modules\Notifications\Services\NotificationService;
use Modules\Users\Models\User;
use Throwable;

class LaboratoryResultObserver
{
    public function created(LaboratoryResult $result): void
    {
        try {
            $result->loadMissing([
                'laboratoryOrder.doctor.user',
                'laboratoryOrder.patient.patientable',
                'laboratoryOrder.laboratory.user',
            ]);

            $order = $result->laboratoryOrder;
            if (! $order) {
                return;
            }

            $notificationService = app(NotificationService::class);
            $payload = [
                'type' => 'laboratory_result',
                'laboratory_result_id' => $result->id,
                'laboratory_order_id' => $order->id,
                'medical_visit_id' => $order->medical_visit_id,
                'patient_id' => $order->patient_id,
                'file_name' => $result->original_file_name,
            ];

            $doctorUser = $order->doctor?->user;
            if ($doctorUser instanceof User) {
                $notificationService->sendToUser(
                    $doctorUser,
                    'Laboratory Result Available',
                    'The laboratory has uploaded the test result for your patient.',
                    $payload
                );
            }

            // patientable may be User or Relatives — only notify real users.
            $patientable = $order->patient?->patientable;
            if ($patientable instanceof User) {
                $notificationService->sendToUser(
                    $patientable,
                    'Laboratory Result Available',
                    'Your laboratory test result is now available.',
                    $payload
                );
            }
        } catch (Throwable $e) {
            report($e);
        }
    }
}
