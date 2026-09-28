<?php

namespace Modules\Prescriptions\Http\Controllers;

use Modules\Core\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Modules\Prescriptions\Services\PrescriptionExportService;
use Modules\Prescriptions\Services\PrescriptionService;

class PrescriptionsController extends Controller
{
   public function __construct(
        private PrescriptionService $service,
        private PrescriptionExportService $export
    ) {}

    public function index()
    {
        return response()->json([
            'data' => $this->service->index()
        ]);
    }

    public function show($id)
    {
        return response()->json([
            'data' => $this->service->show($id)
        ]);
    }
    public function store(Request $request)
    {
        $data = $request->validate([
            'medical_visit_id' => 'required|exists:medical_visits,id',
            'notes' => 'nullable|string',

            'items' => 'required|array|min:1',

            'items.*.medicine_name' => 'required|string',
            'items.*.dose' => 'required|string',
            'items.*.frequency' => 'required|string',
            'items.*.duration' => 'required|string',
            'items.*.instructions' => 'nullable|string',
        ]);

        return response()->json([
            'data' => $this->service->create($data)
        ]);
    }

    public function update(Request $request,$id)
    {
        $data = $request->validate([

            'notes' => 'nullable|string',

            'items' => 'required|array|min:1',

            'items.*.medicine_name' => 'required|string',
            'items.*.dose' => 'required|string',
            'items.*.frequency' => 'required|string',
            'items.*.duration' => 'required|string',
            'items.*.instructions' => 'nullable|string',
        ]);

        return response()->json([
            'data' => $this->service->update(
                $id,
                $data
            )
        ]);
    }

    public function patientPrescriptions(
    $patientId
    )
    {
        return response()->json([
            'data' => $this->service
                ->patientPrescriptions(
                    $patientId
                )
        ]);
    }
    public function destroy($id)
    {
        $this->service->destroy($id);

        return response()->json([
            'message' => 'Prescription deleted successfully'
        ]);

    }


    public function pdf(int $id)
    {
        return $this->export->exportPdf($id);
    }


    public function myPrescriptions()
    {
        return response()->json([
            'data' =>
                $this->service->myPrescriptions()
        ]);
    }
    
}
