<?php

namespace Modules\Laboratory\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Modules\Core\Http\Controllers\Controller;
use Modules\Laboratory\Http\Requests\CreateLaboratoryOrderRequest;
use Modules\Laboratory\Models\LaboratoryOrder;
use Modules\Laboratory\Services\LaboratoryOrderService;

class DoctorLabRequestController extends Controller
{
    public function __construct(
        protected LaboratoryOrderService $service
    ) {
    }

    private function requireDoctor(): \Modules\Doctors\Models\Doctor|JsonResponse
    {
        $doctor = auth()->user()?->doctor;
        if (! $doctor) {
            return response()->json([
                'success' => false,
                'message' => 'Doctor profile not found for this account.',
            ], 403);
        }

        return $doctor;
    }

    /**
     * قائمة المخابر المتاحة للطلب
     */
    public function available(): JsonResponse
    {
        $doctor = $this->requireDoctor();
        if ($doctor instanceof JsonResponse) {
            return $doctor;
        }

        return response()->json([
            'success' => true,
            'data' => $this->service->listAvailableLaboratories(),
        ]);
    }

    /**
     * إنشاء طلب تحاليل
     */
    public function store(
        CreateLaboratoryOrderRequest $request
    ): JsonResponse {

        $doctor = $this->requireDoctor();
        if ($doctor instanceof JsonResponse) {
            return $doctor;
        }

        $order = $this->service->createOrder(
            doctor: $doctor,
            medicalVisitId: $request->medical_visit_id,
            laboratoryId: $request->laboratory_id,
            tests: $request->tests,
            doctorNotes: $request->doctor_notes,
        );

        return response()->json([
            'success' => true,
            'message' => 'Laboratory order created successfully.',
            'data' => $order,
        ], 201);
    }

    /**
     * طلبات الطبيب
     */
    public function index(): JsonResponse
    {
        $doctor = $this->requireDoctor();
        if ($doctor instanceof JsonResponse) {
            return $doctor;
        }

        $orders = $this->service->getDoctorOrders(
            $doctor,
            request('status'),
            request()->filled('medical_visit_id')
                ? (int) request('medical_visit_id')
                : null
        );

        return response()->json([
            'success' => true,
            'data' => $orders,
        ]);
    }

    /**
     * تفاصيل طلب معين
     */
    public function show(
        LaboratoryOrder $laboratoryOrder
    ): JsonResponse {

        $doctor = $this->requireDoctor();
        if ($doctor instanceof JsonResponse) {
            return $doctor;
        }

        $order = $this->service->getDoctorOrder(
            $laboratoryOrder,
            $doctor
        );

        return response()->json([
            'success' => true,
            'data' => $order,
        ]);
    }

    public function update(
        CreateLaboratoryOrderRequest $request,
        int $orderId
    ): JsonResponse {

        $doctor = $this->requireDoctor();
        if ($doctor instanceof JsonResponse) {
            return $doctor;
        }

        $order = $this->service->updateOrder(
            doctor: $doctor,
            orderId: $orderId,
            laboratoryId: $request->laboratory_id,
            tests: $request->tests,
            doctorNotes: $request->doctor_notes,
        );

        return response()->json([
            'success' => true,
            'message' => 'Laboratory order updated successfully.',
            'data' => $order,
        ]);
    }

    public function destroy(
        int $orderId
    ): JsonResponse {

        $doctor = $this->requireDoctor();
        if ($doctor instanceof JsonResponse) {
            return $doctor;
        }

        $this->service->deleteOrder(
            doctor: $doctor,
            orderId: $orderId
        );

        return response()->json([
            'success' => true,
            'message' => 'Laboratory order deleted successfully.',
        ]);
    }
}