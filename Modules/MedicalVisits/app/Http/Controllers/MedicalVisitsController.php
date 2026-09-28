<?php

namespace Modules\MedicalVisits\Http\Controllers;

use Illuminate\Http\Request;
use Modules\Core\Http\Controllers\Controller;
use Modules\MedicalVisits\Services\MedicalVisitService;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class MedicalVisitsController extends Controller
{
    public function __construct(private MedicalVisitService $service) {}

    public function index()
    {
        return response()->json([
            'data' => $this->service->indexForUser(auth()->user()),
        ]);
    }

    public function show($id)
    {
        return response()->json([
            'data' => $this->service->showForUser((int) $id, auth()->user()),
        ]);
    }

    public function store(Request $request)
    {
        $doctor = auth()->user()?->doctor;
        if (! $doctor) {
            return response()->json(['message' => 'Only doctors can create medical visits'], 403);
        }

        $data = $request->validate([
            'patient_file_id' => 'required|exists:patient_files,id',
            'chief_complaint' => 'nullable|string',
            'diagnosis' => 'required|string',
            'icd_code' => 'nullable|string',
            'notes' => 'nullable|string',
            'treatment_plan' => 'nullable|string',
            'visit_date' => 'required|date',
            'category' => 'required|in:general,cardiology,dermatology,psychiatry,ophthalmology,urology,orthopedic,other',
            'speciality_data' => 'nullable|array',
        ]);

        return response()->json([
            'data' => $this->service->store($data, $doctor),
        ], 201);
    }

    public function update(Request $request, $id)
    {
        $data = $request->validate([
            'chief_complaint' => 'nullable|string',
            'diagnosis' => 'sometimes|string',
            'icd_code' => 'nullable|string',
            'notes' => 'nullable|string',
            'treatment_plan' => 'nullable|string',
            'visit_date' => 'sometimes|date',
            'category' => 'sometimes|in:general,cardiology,dermatology,psychiatry,ophthalmology,urology,orthopedic,other',
            'speciality_data' => 'nullable|array',
        ]);

        return response()->json([
            'data' => $this->service->updateForUser((int) $id, $data, auth()->user()),
        ]);
    }

    /**
     * Patient-owned privacy control for a visit inside the unified chart.
     */
    public function updateVisibility(Request $request, $id)
    {
        $data = $request->validate([
            'visibility' => 'required|in:public,approved_doctors,private',
        ]);

        try {
            $visit = $this->service->updateVisibilityForPatient(
                (int) $id,
                $data['visibility'],
                auth()->user()
            );

            return response()->json([
                'message' => 'Visit privacy updated.',
                'data' => $visit,
            ]);
        } catch (AccessDeniedHttpException $e) {
            return response()->json(['message' => $e->getMessage()], 403);
        }
    }

    public function destroy($id)
    {
        $this->service->deleteForUser((int) $id, auth()->user());

        return response()->json([
            'message' => 'Medical visit deleted successfully',
        ]);
    }
}
