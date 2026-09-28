<?php

namespace Modules\Patients\Http\Controllers;

use Illuminate\Http\Request;
use Modules\Core\Http\Controllers\Controller;
use Modules\MedicalVisits\Services\MedicalVisitService;
use Modules\Patients\Models\PatientFile;
use Modules\Patients\Services\PatientFileService;

class PatientFileController extends Controller
{
    public function __construct(
        private PatientFileService $service,
        private MedicalVisitService $visits,
    ) {}

    public function store(Request $request)
    {
        $data = $request->validate([
            'patient_id' => 'required|exists:patients,id',
        ]);

        $file = $this->service->create($data);

        return response()->json([
            'message' => 'Unified patient chart ready.',
            'data' => $file,
        ], 201);
    }

    public function show(PatientFile $file)
    {
        $user = auth()->user();
        $doctor = $user?->doctor;
        $isOwnerPatient = $user?->patient && (int) $user->patient->id === (int) $file->patient_id;

        if (! $isOwnerPatient) {
            if (! $doctor || ! app(\Modules\Patients\Services\PatientChartAccessService::class)->canAccessFile($file, $user)) {
                return response()->json(['message' => 'Unauthorized.'], 403);
            }
        }

        return $file->load([
            'medicalVisits' => function ($query) use ($doctor, $isOwnerPatient) {
                if ($doctor && ! $isOwnerPatient) {
                    $this->visits->scopeVisibleToDoctor($query, $doctor->id);
                }

                $query->with([
                    'doctor.user:id,name,phone',
                    'prescriptions.items',
                    'attachments',
                    'laboratoryOrders.result',
                ])->latest('visit_date');
            },
            'surgeries',
            'familyHistories',
            'vitalSigns',
            'ocularHistories',
        ]);
    }
}
