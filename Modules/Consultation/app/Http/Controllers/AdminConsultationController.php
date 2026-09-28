<?php

namespace Modules\Consultation\Http\Controllers;

use Modules\Core\Http\Controllers\Controller;
use Modules\Consultation\Services\ConsultationService;
use Illuminate\Http\Request;

class AdminConsultationController extends Controller
{
    protected $service;

    public function __construct(ConsultationService $service)
    {
        $this->service = $service;
    }

    /**
     * عرض جميع الاستشارات
     */
    public function index(Request $request)
    {
        $filters = $request->only(['status', 'date_from', 'date_to', 'rating_min']);
        $perPage = $request->get('per_page', 15);

        $consultations = $this->service->getConsultations($perPage, $filters);

        return $this->successResponse($consultations);
    }

    /**
     * عرض استشارة محددة
     */
    public function show($id)
    {
        $consultation = $this->service->getConsultation($id);

        return $this->successResponse($consultation);
    }

    /**
     * إلغاء استشارة
     */
    public function cancel($id)
    {
        try {
            $consultation = $this->service->cancelConsultation(
                $id,
                auth()->id(),
                auth()->user()->name
            );

            return $this->successResponse($consultation, 'Consultation cancelled successfully');
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), 422);
        }
    }

    /**
     * حذف استشارة
     */
    public function destroy($id)
    {
        $this->service->deleteConsultation($id);

        return $this->successResponse(null, 'Consultation deleted successfully');
    }

    /**
     * عرض سجلات المكالمات
     */
    public function audits(Request $request)
    {
        $filters = $request->only(['event', 'user_role', 'date_from', 'date_to']);
        $perPage = $request->get('per_page', 20);

        $audits = $this->service->getAudits($perPage, $filters);

        return $this->successResponse($audits);
    }

    /**
     * عرض سجلات مكالمة محددة
     */
    public function consultationAudits($consultationId)
    {
        $audits = $this->service->getConsultationAudits($consultationId);

        return $this->successResponse($audits);
    }

    /**
     * إحصائيات المكالمات
     */
    public function stats(Request $request)
    {
        $startDate = $request->get('start_date', now()->subDays(30));
        $endDate = $request->get('end_date', now());

        $stats = $this->service->getStats($startDate, $endDate);

        return $this->successResponse($stats);
    }

    /**
     * تقارير المكالمات
     */
    public function reports(Request $request)
    {
        $consultations = $this->service->getConsultations(9999, [])->items();

        if ($request->get('format') === 'csv') {
            return $this->exportCSV($consultations);
        }

        return $this->successResponse($consultations);
    }

    /**
     * عرض المشتركين
     */
    public function subscribers(Request $request)
    {
        $perPage = $request->get('per_page', 20);

        $subscribers = $this->service->getSubscribers($perPage);

        return $this->successResponse($subscribers);
    }

    /**
     * عرض مشتركي طبيب معين
     */
    public function doctorSubscribers($doctorId)
    {
        $subscribers = $this->service->getDoctorSubscribers($doctorId);

        return $this->successResponse($subscribers, null, ['count' => $subscribers->count()]);
    }

    // ============================================
    // دوال مساعدة (Helper Methods)
    // ============================================

    private function successResponse($data, $message = null, $extra = [])
    {
        return response()->json(array_merge([
            'success' => true,
            'message' => $message,
            'data' => $data
        ], $extra));
    }

    private function errorResponse($message, $code = 400)
    {
        return response()->json([
            'success' => false,
            'message' => $message
        ], $code);
    }

    private function exportCSV($consultations)
    {
        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="consultations_report.csv"',
        ];

        $callback = function () use ($consultations) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['ID', 'Patient', 'Doctor', 'Status', 'Started At', 'Ended At', 'Duration', 'Rating']);

            foreach ($consultations as $c) {
                fputcsv($handle, [
                    $c->id,
                    $c->appointment->patient->name ?? 'N/A',
                    $c->appointment->doctor->name ?? 'N/A',
                    $c->status,
                    $c->started_at,
                    $c->ended_at,
                    $c->started_at && $c->ended_at ? $c->started_at->diffInSeconds($c->ended_at) : 0,
                    $c->rating ?? 'Not rated',
                ]);
            }
            fclose($handle);
        };

        return response()->stream($callback, 200, $headers);
    }
}