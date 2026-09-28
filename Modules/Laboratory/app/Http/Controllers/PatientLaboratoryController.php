<?php

namespace Modules\Laboratory\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Modules\Core\Http\Controllers\Controller;
use Modules\Laboratory\Models\LaboratoryOrder;
use Modules\Laboratory\Services\LaboratoryOrderService;
use Modules\Patients\Models\Patients;
use Modules\Patients\Services\PatientService;
use Modules\Users\Models\User;

class PatientLaboratoryController extends Controller
{
    public function __construct(
        protected LaboratoryOrderService $service,
        protected PatientService $patientService,
    ) {
    }

    private function requirePatientId(): int|JsonResponse
    {
        $user = auth()->user();
        if (! $user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated.',
            ], 401);
        }

        $patient = $user->patient;

        if (! $patient) {
            // Morph type may differ across older seeds; resolve then ensure.
            $patient = Patients::query()
                ->where('patientable_id', $user->id)
                ->where(function ($q) {
                    $q->where('patientable_type', User::class)
                        ->orWhere('patientable_type', 'Modules\\Users\\Models\\User')
                        ->orWhere('patientable_type', 'App\\Models\\User');
                })
                ->first();
        }

        if (! $patient) {
            $patient = $this->patientService->createForUser($user);
        }

        return (int) $patient->id;
    }

    public function orders(): JsonResponse
    {
        $patientId = $this->requirePatientId();
        if ($patientId instanceof JsonResponse) {
            return $patientId;
        }

        $orders = $this->service->getPatientOrders(
            $patientId,
            request('status')
        );

        return response()->json([
            'success' => true,
            'data' => $orders,
        ]);
    }

    public function show(LaboratoryOrder $laboratoryOrder): JsonResponse
    {
        $patientId = $this->requirePatientId();
        if ($patientId instanceof JsonResponse) {
            return $patientId;
        }

        $order = $this->service->getPatientOrder(
            $laboratoryOrder,
            $patientId
        );

        return response()->json([
            'success' => true,
            'data' => $order,
        ]);
    }
}
