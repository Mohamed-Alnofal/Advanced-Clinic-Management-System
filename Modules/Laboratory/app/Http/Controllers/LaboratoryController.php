<?php

namespace Modules\Laboratory\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Modules\Core\Http\Controllers\Controller;
use Modules\Laboratory\Http\Requests\AnalyzeLaboratoryAssistRequest;
use Modules\Laboratory\Http\Requests\UploadLaboratoryResultRequest;
use Modules\Laboratory\Models\LaboratoryOrder;
use Modules\Laboratory\Models\LaboratoryResult;
use Modules\Laboratory\Services\LaboratoryOrderService;
use Modules\MedicalRecords\Services\MedicalAnalysisService;
use Symfony\Component\HttpFoundation\StreamedResponse;

class LaboratoryController extends Controller
{
    public function __construct(
        protected LaboratoryOrderService $service
    ) {
    }

    private function requireLaboratoryId(): int|JsonResponse
    {
        $laboratory = auth()->user()?->laboratory;
        if (! $laboratory) {
            return response()->json([
                'success' => false,
                'message' => 'Laboratory profile not found for this account.',
            ], 403);
        }

        return (int) $laboratory->id;
    }

    /**
     * عرض طلبات المخبر
     */
    public function orders(): JsonResponse
    {
        $laboratoryId = $this->requireLaboratoryId();
        if ($laboratoryId instanceof JsonResponse) {
            return $laboratoryId;
        }

        $orders = $this->service->getLaboratoryOrders(
            $laboratoryId,
            request('status')
        );

        return response()->json([
            'success' => true,
            'data' => $orders,
        ]);
    }

    /**
     * عرض تفاصيل طلب
     */
    public function showOrder(LaboratoryOrder $laboratoryOrder): JsonResponse
    {
        $laboratoryId = $this->requireLaboratoryId();
        if ($laboratoryId instanceof JsonResponse) {
            return $laboratoryId;
        }

        $order = $this->service->getLaboratoryOrder(
            $laboratoryOrder,
            $laboratoryId
        );

        return response()->json([
            'success' => true,
            'data' => $order,
        ]);
    }

    /**
     * بدء العمل على الطلب
     */
    public function startOrder(LaboratoryOrder $laboratoryOrder): JsonResponse
    {
        $laboratoryId = $this->requireLaboratoryId();
        if ($laboratoryId instanceof JsonResponse) {
            return $laboratoryId;
        }

        $order = $this->service->startOrder(
            $laboratoryOrder,
            $laboratoryId
        );

        return response()->json([
            'success' => true,
            'message' => 'Laboratory order started successfully.',
            'data' => $order,
        ]);
    }

    public function uploadResult(
        UploadLaboratoryResultRequest $request,
        LaboratoryOrder $laboratoryOrder
    ): JsonResponse {
        $laboratoryId = $this->requireLaboratoryId();
        if ($laboratoryId instanceof JsonResponse) {
            return $laboratoryId;
        }

        $result = $this->service->uploadResult(
            order: $laboratoryOrder,
            laboratoryId: $laboratoryId,
            file: $request->file('result_file'),
        );

        return response()->json([
            'success' => true,
            'message' => 'Laboratory result uploaded successfully.',
            'data' => $result,
        ], 201);
    }

    /**
     * Download laboratory result
     */
    public function download(LaboratoryResult $laboratoryResult): StreamedResponse|JsonResponse
    {
        try {
            $order = $laboratoryResult->laboratoryOrder;
            if (! $order || ! $this->userCanAccessOrder($order)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized to download this result.',
                ], 403);
            }

            return $this->service->download($laboratoryResult);
        } catch (\Throwable $e) {
            report($e);

            return response()->json([
                'success' => false,
                'message' => $e->getMessage() ?: 'Could not download laboratory result.',
            ], 404);
        }
    }

    public function getResults(LaboratoryOrder $laboratoryOrder): JsonResponse
    {
        if (! $this->userCanAccessOrder($laboratoryOrder)) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized to view these results.',
            ], 403);
        }

        return response()->json([
            'success' => true,
            'data' => $this->service->getResults($laboratoryOrder),
        ]);
    }

    /**
     * Doctor who ordered, patient of the order, owning laboratory, or admin.
     */
    private function userCanAccessOrder(LaboratoryOrder $order): bool
    {
        $user = auth()->user();
        if (! $user) {
            return false;
        }

        if (method_exists($user, 'hasRole') && $user->hasRole('admin')) {
            return true;
        }

        $laboratory = $user->laboratory ?? null;
        if ($laboratory && (int) $laboratory->id === (int) $order->laboratory_id) {
            return true;
        }

        $doctor = $user->doctor ?? null;
        if ($doctor && (int) $doctor->id === (int) $order->doctor_id) {
            return true;
        }

        $order->loadMissing('patient');
        $patient = $order->patient;
        if (
            $patient
            && (int) ($patient->patientable_id ?? 0) === (int) $user->id
        ) {
            return true;
        }

        return false;
    }

    /**
     * Smart lab assistant: extract & evaluate values from a PDF/TXT without attaching to an order.
     */
    public function analyzeAssist(AnalyzeLaboratoryAssistRequest $request): JsonResponse
    {
        $laboratoryId = $this->requireLaboratoryId();
        if ($laboratoryId instanceof JsonResponse) {
            return $laboratoryId;
        }

        $file = $request->file('result_file');
        $tmpPath = $file->getRealPath();
        if (! is_string($tmpPath) || $tmpPath === '' || ! file_exists($tmpPath)) {
            return response()->json([
                'success' => false,
                'message' => 'Could not read the uploaded file.',
            ], 422);
        }

        $extension = strtolower($file->getClientOriginalExtension() ?: $file->extension() ?: 'pdf');
        $workPath = $tmpPath;
        $copied = false;

        // PdfParser needs a file with a known extension sometimes.
        if (! str_ends_with(strtolower($tmpPath), '.'.$extension)) {
            $workPath = sys_get_temp_dir().DIRECTORY_SEPARATOR.'lab_assist_'.uniqid('', true).'.'.$extension;
            if (! @copy($tmpPath, $workPath)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Could not prepare the file for analysis.',
                ], 422);
            }
            $copied = true;
        }

        try {
            $analysis = app(MedicalAnalysisService::class)->analyzeFilePath(
                $workPath,
                $request->input('gender'),
                $request->filled('age') ? (int) $request->input('age') : null,
            );

            return response()->json([
                'success' => true,
                'message' => 'Laboratory report analyzed successfully.',
                'data' => $analysis,
            ]);
        } catch (\Throwable $e) {
            report($e);

            return response()->json([
                'success' => false,
                'message' => $e->getMessage() !== ''
                    ? $e->getMessage()
                    : 'Analysis failed. Please try a text-based PDF.',
            ], 422);
        } finally {
            if ($copied && is_string($workPath) && file_exists($workPath)) {
                @unlink($workPath);
            }
        }
    }
}
